<?php

declare(strict_types=1);

namespace App\Core;

/**
 * PHP sessions with hardened cookie settings.
 *
 * Strict mode refuses a session id the server did not issue, the cookie is
 * HttpOnly, SameSite=Lax and Secure on HTTPS, and the id is regenerated at
 * every privilege change (sign-in, MFA, sign-out). Public pages never start a
 * session: it is opened only by admin, auth and form requests that need it.
 */
final class Session
{
    private static bool $started = false;
    /** @var array<string, mixed> */
    private static array $memory = [];
    private static bool $useMemory = false;

    public static function useMemory(bool $on = true): void
    {
        self::$useMemory = $on;
        self::$memory = [];
        self::$started = $on;
    }

    public static function start(?Request $request = null): void
    {
        if (self::$started) {
            return;
        }
        if (PHP_SAPI === 'cli') {
            self::useMemory();
            return;
        }
        $secure = $request?->isSecure() ?? false;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.gc_maxlifetime', (string) config('app.session.lifetime'));
        $dir = BASE_PATH . '/storage/sessions';
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        session_name((string) config('app.session.name'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;
        self::ageFlash();
    }

    public static function started(): bool
    {
        return self::$started;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$useMemory) {
            return self::$memory[$key] ?? $default;
        }
        return self::$started ? ($_SESSION[$key] ?? $default) : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        if (self::$useMemory) {
            self::$memory[$key] = $value;
            return;
        }
        if (!self::$started) {
            self::start();
        }
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        if (self::$useMemory) {
            unset(self::$memory[$key]);
            return;
        }
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (!self::$useMemory && self::$started && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (self::$useMemory) {
            self::$memory = [];
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_destroy();
        }
        self::$started = false;
    }

    /** A value that survives exactly one further request. */
    public static function flash(string $key, mixed $value): void
    {
        $flash = self::get('_flash_new', []);
        $flash[$key] = $value;
        self::put('_flash_new', $flash);
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $current = self::get('_flash_now', []);
        return is_array($current) && array_key_exists($key, $current) ? $current[$key] : $default;
    }

    private static function ageFlash(): void
    {
        $_SESSION['_flash_now'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }
}
