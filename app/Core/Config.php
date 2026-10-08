<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Application settings, served by dot path ("app.url").
 *
 * There is no file for anyone to edit. The fixed values live here; the few
 * that differ per site (address, database, encryption key) are written by the
 * web installer and changed later from Admin → Website settings → System.
 * Everything else is a database setting (App\Services\Settings).
 */
final class Config
{
    /** @var array<string, mixed>|null */
    private static ?array $items = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::$items ??= self::load();
        return data_get(self::$items, $key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        self::$items ??= self::load();
        $parts = explode('.', $key);
        $ref = &self::$items;
        foreach ($parts as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        $ref = $value;
    }

    public static function reset(): void
    {
        self::$items = null;
    }

    /** @return array<string, mixed> */
    private static function load(): array
    {
        $dev = PHP_SAPI === 'cli-server';
        $site = Installation::read();
        $db = (array) ($site['database'] ?? []);
        return [
            'app' => [
                'name' => 'Brand CMS',
                'version' => '1.0.0',
                'env' => $dev ? 'development' : 'production',
                'debug' => $dev,
                'url' => rtrim((string) ($site['url'] ?? ''), '/'),
                'key' => (string) ($site['key'] ?? ''),
                'timezone' => (string) ($site['timezone'] ?? 'Asia/Kolkata'),
                'login_path' => '/' . trim((string) ($site['login_path'] ?? Installation::DEFAULT_LOGIN_PATH), '/'),
                'cron_secret' => (string) ($site['cron_secret'] ?? ''),
                'canonical_host_redirect' => true,
                'upload_dir' => BASE_PATH . '/storage/uploads',
                'backup_dir' => BASE_PATH . '/storage/backups',
                'media_path' => '/media',
                'max_upload_bytes' => 10 * 1024 * 1024,
                'session' => [
                    'name' => 'site_session',
                    'lifetime' => 60 * 60 * 12,     // absolute lifetime of a signed-in session
                    'idle' => 60 * 60 * 2,          // signed out after this long without a request
                ],
            ],
            'database' => [
                'host' => (string) ($db['host'] ?? 'localhost'),
                'port' => (int) ($db['port'] ?? 3306),
                'database' => (string) ($db['database'] ?? ''),
                'username' => (string) ($db['username'] ?? ''),
                'password' => (string) ($db['password'] ?? ''),
                'socket' => (string) ($db['socket'] ?? ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
        ];
    }
}
