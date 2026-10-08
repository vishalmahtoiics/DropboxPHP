<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @var array<string, string> */
    public array $headers = [];

    public function __construct(
        public string $body = '',
        public int $status = 200,
        array $headers = [],
    ) {
        $this->headers = $headers + ['Content-Type' => 'text/html; charset=utf-8'];
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?: 'null',
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    /** Stream a file from disk instead of holding it in memory. */
    public static function file(string $path, array $headers = []): self
    {
        $r = new self('', 200, $headers);
        $r->file = $path;
        return $r;
    }

    public ?string $file = null;

    public static function text(string $body, int $status = 200, string $type = 'text/plain'): self
    {
        return new self($body, $status, ['Content-Type' => $type . '; charset=utf-8']);
    }

    /**
     * Redirect. Only same-site paths or absolute URLs are accepted, and a
     * protocol-relative "//evil.com" is treated as hostile and replaced.
     */
    public static function redirect(string $to, int $status = 302): self
    {
        if (str_starts_with($to, '//') || preg_match('/[\r\n]/', $to)) {
            $to = '/';
        }
        return new self('', $status, ['Location' => $to]);
    }

    /** Back to the referring admin page, falling back to $fallback. */
    public static function back(Request $request, string $fallback = '/admin'): self
    {
        $ref = $request->referer();
        if ($ref) {
            $parts = parse_url($ref);
            if (($parts['host'] ?? '') === explode(':', $request->host())[0] || ($parts['host'] ?? '') === $request->host()) {
                $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
                return self::redirect($path);
            }
        }
        return self::redirect($fallback);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withCookie(string $name, string $value, array $options = []): self
    {
        $this->cookies[] = [$name, $value, $options];
        return $this;
    }

    /** @var list<array{0:string,1:string,2:array<string,mixed>}> */
    public array $cookies = [];

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . str_replace(["\r", "\n"], '', $value));
            }
            foreach ($this->cookies as [$name, $value, $options]) {
                setcookie($name, $value, $options);
            }
        }
        if ($this->file !== null) {
            readfile($this->file);
            return;
        }
        echo $this->body;
    }
}
