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

    /** @return array<string, mixed> */
    private static function load(): array
    {
        $items = [];
        foreach (glob(BASE_PATH . '/config/*.php') ?: [] as $file) {
            $items[basename($file, '.php')] = require $file;
        }
        return $items;
    }
}
