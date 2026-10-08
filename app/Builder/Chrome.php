<?php

declare(strict_types=1);

namespace App\Builder;

use App\Core\DB;
use Throwable;

/**
 * The site header and footer built with the builder.
 *
 * The active header (or footer) template replaces the built-in one on every
 * page; with none active the built-in partials are used. Rendered output is
 * kept for the rest of the request.
 */
final class Chrome
{
    /** @var array<string, ?string> */
    private static array $cache = [];

    public static function header(?array $page = null): ?string
    {
        return self::part('header', $page);
    }

    public static function footer(?array $page = null): ?string
    {
        return self::part('footer', $page);
    }

    public static function reset(): void
    {
        self::$cache = [];
    }

    private static function part(string $kind, ?array $page): ?string
    {
        if (array_key_exists($kind, self::$cache)) {
            return self::$cache[$kind];
        }
        try {
            $json = DB::value("SELECT layout FROM builder_templates WHERE kind = ? AND is_default = 1 ORDER BY id LIMIT 1", [$kind]);
        } catch (Throwable) {
            $json = null; // not installed or migrated yet
        }
        $layout = is_string($json) ? Layout::decode($json) : null;
        if ($layout === null || Layout::isEmpty($layout)) {
            return self::$cache[$kind] = null;
        }
        $out = Renderer::render($layout, ['page' => $page]);
        $html = '<div class="bx-root bx-chrome bx-site-' . $kind . '">' . $out['html'] . '</div>';
        if ($out['css'] !== '') {
            $html = '<style nonce="' . e(nonce()) . '">' . $out['css'] . '</style>' . $html;
        }
        return self::$cache[$kind] = $html;
    }
}
