<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader.
 *
 * Real environment variables always win over the file, so a host's panel or a
 * container can override anything without editing it. Values are never
 * evaluated: no interpolation, no command substitution.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                continue;
            }
            $value = self::parseValue(trim(substr($line, $eq + 1)));
            self::$values[$key] = $value;
        }
    }

    private static function parseValue(string $raw): string
    {
        if ($raw === '') {
            return '';
        }
        $quote = $raw[0];
        if ($quote === '"' || $quote === "'") {
            $end = strpos($raw, $quote, 1);
            if ($end !== false) {
                $value = substr($raw, 1, $end - 1);
                return $quote === '"' ? str_replace(['\\n', '\\"'], ["\n", '"'], $value) : $value;
            }
        }
        // Strip a trailing inline comment ("value   # note").
        $hash = strpos($raw, ' #');
        if ($hash !== false) {
            $raw = substr($raw, 0, $hash);
        }
        return trim($raw);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $real = getenv($key);
        if ($real !== false && $real !== '') {
            return $real;
        }
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        if (array_key_exists($key, self::$values) && self::$values[$key] !== '') {
            return self::$values[$key];
        }
        return $default;
    }

    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
    }
}
