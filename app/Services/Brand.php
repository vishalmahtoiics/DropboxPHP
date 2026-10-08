<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The brand name, set once in Admin → Website settings → Brand name.
 *
 * Wherever text is written — page content, builder widgets, products, SEO
 * settings, emails — "{brand}" stands for it, so renaming the brand in
 * settings changes it everywhere. The replacement happens when a page or
 * email is produced; what is stored keeps the placeholder.
 */
final class Brand
{
    public const TOKEN = '{brand}';
    public const DEFAULT_NAME = 'Brand Name';

    public static function name(): string
    {
        $name = trim((string) Settings::get('website', 'site_name', self::DEFAULT_NAME));
        return $name !== '' ? $name : self::DEFAULT_NAME;
    }

    /** Plain text: replace the token with the name as is (escape it where it is output). */
    public static function text(string $value): string
    {
        return str_contains($value, self::TOKEN) ? str_replace(self::TOKEN, self::name(), $value) : $value;
    }

    /** Rendered HTML: replace the token with the name, HTML-escaped. */
    public static function html(string $html): string
    {
        return str_contains($html, self::TOKEN) ? str_replace(self::TOKEN, e(self::name()), $html) : $html;
    }

    /** A logo stand-in when no logo is uploaded: the brand's first letter. */
    public static function mark(string $class = 'logo-mark'): string
    {
        $letter = mb_strtoupper(mb_substr(self::name(), 0, 1));
        return '<span class="' . e($class) . ' brand-letter" aria-hidden="true">' . e($letter !== '' ? $letter : 'B') . '</span>';
    }
}
