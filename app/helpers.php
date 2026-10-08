<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;

/**
 * Global helpers. Kept deliberately small: escaping, configuration and a few
 * view conveniences. Anything with logic lives in a class.
 */

function env(string $key, mixed $default = null): mixed
{
    $value = Env::get($key, $default);
    if (!is_string($value)) {
        return $value;
    }
    return match (strtolower($value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        default => $value,
    };
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/** HTML-escape for text and attribute context. The only way views print data. */
function e(mixed $value): string
{
    if ($value === null || $value === false) {
        return '';
    }
    if ($value === true) {
        return '1';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** JSON for embedding inside a <script> element or a data attribute. */
function js(mixed $value): string
{
    return json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?: 'null';
}

function base_url(): string
{
    return rtrim((string) config('app.url', ''), '/');
}

/** An absolute URL on this site for a path. */
function absolute_url(string $path = '/'): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return base_url() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return '/assets/' . ltrim($path, '/') . '?v=' . $version;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

/** The value a field had in the request that just failed validation. */
function old(string $key, mixed $default = ''): mixed
{
    $old = Session::getFlash('_old', []);
    return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
}

/** @return array<string, string> */
function errors(): array
{
    $errors = Session::getFlash('_errors', []);
    return is_array($errors) ? $errors : [];
}

function nonce(): string
{
    return App\Core\Security::nonce();
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function str_limit(?string $value, int $limit = 100, string $end = '…'): string
{
    $value = (string) $value;
    return mb_strlen($value) <= $limit ? $value : rtrim(mb_substr($value, 0, $limit)) . $end;
}

/** Array access by dot path: data_get($a, 'seo.title'). */
function data_get(mixed $target, string $path, mixed $default = null): mixed
{
    foreach (explode('.', $path) as $segment) {
        if (is_array($target) && array_key_exists($segment, $target)) {
            $target = $target[$segment];
        } else {
            return $default;
        }
    }
    return $target;
}

function can(string $permission): bool
{
    return App\Core\Auth::can($permission);
}

function format_date(?string $value, string $format = 'd M Y'): string
{
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '';
}

function format_datetime(?string $value): string
{
    return format_date($value, 'd M Y, H:i');
}

function time_ago(?string $value): string
{
    if (!$value) {
        return '';
    }
    $diff = time() - (int) strtotime($value);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' h ago';
    if ($diff < 86400 * 30) return floor($diff / 86400) . ' d ago';
    return format_date($value);
}
