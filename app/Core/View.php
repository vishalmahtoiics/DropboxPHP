<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Plain-PHP templates.
 *
 * A template prints data only through e() (or js() inside script). Layouts
 * receive the rendered page as $content plus everything the page set with
 * View::share() or the $data array.
 */
final class View
{
    /** @var array<string, mixed> */
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(string $key, mixed $default = null): mixed
    {
        return self::$shared[$key] ?? $default;
    }

    public static function resetShared(): void
    {
        self::$shared = [];
    }

    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    public static function partial(string $template, array $data = []): string
    {
        if (!preg_match('#^[a-z0-9_\-/]+$#i', $template) || str_contains($template, '..')) {
            throw new RuntimeException('Invalid template name.');
        }
        $file = BASE_PATH . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View [$template] not found.");
        }
        $data += self::$shared;
        return (static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string) ob_get_clean();
        })($file, $data);
    }

    public static function exists(string $template): bool
    {
        return preg_match('#^[a-z0-9_\-/]+$#i', $template) === 1 && is_file(BASE_PATH . '/views/' . $template . '.php');
    }
}
