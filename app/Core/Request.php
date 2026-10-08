<?php

declare(strict_types=1);

namespace App\Core;

/**
 * The incoming HTTP request, captured once from the superglobals.
 */
final class Request
{
    /** @var array<string, string> */
    public array $params = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     * @param array<string, string> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $files,
        public readonly array $server,
        public readonly array $cookies,
    ) {
    }

    public static function capture(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $body = $_POST;
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if ($method !== 'GET' && str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024);
            $decoded = json_decode((string) $raw, true);
            $body = is_array($decoded) ? $decoded : [];
        }
        // HTML forms can only send GET and POST; _method carries PUT/PATCH/DELETE.
        if ($method === 'POST' && isset($body['_method']) && is_string($body['_method'])) {
            $override = strtoupper($body['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        return new self($method, self::normalisePath($path), $_GET, $body, $_FILES, $_SERVER, $_COOKIE);
    }

    public static function normalisePath(string $path): string
    {
        $path = '/' . ltrim(preg_replace('#/+#', '/', $path) ?? '/', '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key): bool
    {
        $value = $this->input($key);
        return in_array($value, [true, 1, '1', 'on', 'true', 'yes'], true);
    }

    /** @return array<mixed> */
    public function arr(string $key): array
    {
        $value = $this->input($key, []);
        return is_array($value) ? $value : [];
    }

    public function queryStr(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $default;
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        return is_array($file) && isset($file['tmp_name']) ? $file : null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    public function cookie(string $name): ?string
    {
        $value = $this->cookies[$name] ?? null;
        return is_string($value) ? $value : null;
    }

    public function isPost(): bool
    {
        return $this->method !== 'GET' && $this->method !== 'HEAD';
    }

    public function wantsJson(): bool
    {
        return str_contains((string) $this->header('Accept'), 'application/json')
            || $this->header('X-Requested-With') === 'fetch';
    }

    public function isSecure(): bool
    {
        if (($this->server['HTTPS'] ?? '') !== '' && strtolower((string) $this->server['HTTPS']) !== 'off') {
            return true;
        }
        if ($this->fromTrustedProxy()) {
            return strtolower((string) $this->header('X-Forwarded-Proto')) === 'https';
        }
        return (int) ($this->server['SERVER_PORT'] ?? 80) === 443;
    }

    public function host(): string
    {
        $host = null;
        if ($this->fromTrustedProxy()) {
            $host = $this->header('X-Forwarded-Host');
        }
        $host ??= $this->header('Host') ?? (string) ($this->server['SERVER_NAME'] ?? 'localhost');
        $host = strtolower(trim(explode(',', $host)[0]));
        return preg_match('/^[a-z0-9.\-]+(:\d+)?$/', $host) ? $host : 'localhost';
    }

    public function fullUrl(): string
    {
        return ($this->isSecure() ? 'https' : 'http') . '://' . $this->host() . ($this->server['REQUEST_URI'] ?? $this->path);
    }

    public function queryString(): string
    {
        return (string) ($this->server['QUERY_STRING'] ?? '');
    }

    public function userAgent(): string
    {
        return mb_substr((string) $this->header('User-Agent'), 0, 500);
    }

    public function referer(): ?string
    {
        return $this->header('Referer');
    }

    /**
     * The visitor's address. A forwarded header is believed only when the
     * request came from a proxy listed in TRUSTED_PROXIES; otherwise anyone
     * could claim any address and walk around the rate limits.
     */
    public function ip(): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '');
        if ($this->fromTrustedProxy()) {
            $forwarded = $this->header('CF-Connecting-IP') ?? $this->header('X-Forwarded-For');
            if ($forwarded) {
                $candidate = trim(explode(',', $forwarded)[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    private function fromTrustedProxy(): bool
    {
        $trusted = (array) config('app.trusted_proxies', []);
        if ($trusted === []) {
            return false;
        }
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '');
        return in_array('*', $trusted, true) || in_array($remote, $trusted, true);
    }
}
