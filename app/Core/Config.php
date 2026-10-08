<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Reads config/*.php once and serves values by dot path ("app.url").
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

    /**
     * Defaults from config/*.php, overridden by the site's own config.php in
     * the project root, then normalised (paths, URLs, sizes).
     *
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        $items = [];
        foreach (glob(BASE_PATH . '/config/*.php') ?: [] as $file) {
            $items[basename($file, '.php')] = require $file;
        }
        $local = BASE_PATH . '/config.php';
        if (is_file($local)) {
            $site = require $local;
            if (is_array($site)) {
                foreach ($site as $group => $values) {
                    $items[$group] = is_array($values) && is_array($items[$group] ?? null)
                        ? array_replace($items[$group], $values)
                        : $values;
                }
            }
        }
        return self::normalise($items);
    }

    /** @param array<string, mixed> $items @return array<string, mixed> */
    private static function normalise(array $items): array
    {
        $app = &$items['app'];
        $path = static function (string $value): string {
            if ($value === '') {
                return $value;
            }
            return str_starts_with($value, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $value)
                ? rtrim($value, '/\\')
                : BASE_PATH . '/' . trim($value, '/\\');
        };
        $app['url'] = rtrim((string) $app['url'], '/');
        $app['login_path'] = '/' . trim((string) $app['login_path'], '/');
        $app['media_path'] = '/' . trim((string) $app['media_path'], '/');
        $app['upload_dir'] = $path((string) $app['upload_dir']);
        $app['backup_dir'] = $path((string) $app['backup_dir']);
        $app['max_upload_bytes'] = (int) round(((float) $app['max_upload_mb']) * 1024 * 1024);
        $proxies = $app['trusted_proxies'];
        $app['trusted_proxies'] = array_values(array_filter(array_map('trim', is_array($proxies) ? $proxies : explode(',', (string) $proxies))));
        $app['debug'] = (bool) $app['debug'];
        unset($app);
        return $items;
    }
}
