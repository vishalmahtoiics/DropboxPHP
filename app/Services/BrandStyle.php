<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Sanitizer;

/**
 * Turns the brand settings into CSS custom properties, so changing a colour
 * or font in the admin repaints the whole site on the next request — no build.
 * Every value printed here was validated as a colour or a length on save and
 * is validated again here.
 */
final class BrandStyle
{
    public const FONTS = [
        'Inter' => [300, 400, 500, 600, 700, 800], 'Roboto' => [300, 400, 500, 700, 900], 'Open Sans' => [300, 400, 500, 600, 700, 800],
        'Lato' => [300, 400, 700, 900], 'Poppins' => [300, 400, 500, 600, 700, 800], 'Montserrat' => [300, 400, 500, 600, 700, 800],
        'Nunito' => [300, 400, 600, 700, 800], 'Work Sans' => [300, 400, 500, 600, 700], 'DM Sans' => [400, 500, 700],
        'Manrope' => [300, 400, 500, 600, 700, 800], 'Plus Jakarta Sans' => [300, 400, 500, 600, 700, 800], 'Outfit' => [300, 400, 500, 600, 700],
        'Rubik' => [300, 400, 500, 600, 700], 'Source Sans 3' => [300, 400, 600, 700], 'IBM Plex Sans' => [300, 400, 500, 600, 700],
        'Raleway' => [300, 400, 500, 600, 700], 'Mulish' => [300, 400, 600, 700], 'Figtree' => [400, 500, 600, 700],
        'Lora' => [400, 500, 600, 700], 'Merriweather' => [300, 400, 700], 'Playfair Display' => [400, 500, 600, 700],
        'Libre Baskerville' => [400, 700], 'Noto Sans' => [300, 400, 500, 600, 700], 'Space Grotesk' => [300, 400, 500, 600, 700],
        'System UI' => [],
    ];

    public static function fontLinks(array $site): string
    {
        $families = [];
        foreach ([[$site['body_font'], [$site['body_weight'], '500', '600', '700']], [$site['heading_font'], [$site['heading_weight']]]] as [$font, $weights]) {
            if (!isset(self::FONTS[$font]) || self::FONTS[$font] === []) {
                continue;
            }
            $available = self::FONTS[$font];
            $want = array_values(array_unique(array_filter(array_map('intval', $weights), static fn ($w) => in_array($w, $available, true))));
            $families[$font] = array_unique(array_merge($families[$font] ?? [], $want ?: [400]));
        }
        if ($families === []) {
            return '';
        }
        $parts = [];
        foreach ($families as $font => $weights) {
            sort($weights);
            $parts[] = 'family=' . str_replace(' ', '+', $font) . ':wght@' . implode(';', $weights);
        }
        $href = 'https://fonts.googleapis.com/css2?' . implode('&', $parts) . '&display=swap';
        return '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link rel="stylesheet" href="' . e($href) . '">';
    }

    public static function css(array $s): string
    {
        $font = static fn (string $f) => isset(self::FONTS[$f]) && $f !== 'System UI' ? '"' . $f . '", ' : '';
        $stack = 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';
        $vars = [
            '--brand' => self::color($s['color_primary'], '#0061FF'),
            '--brand-rgb' => self::rgb(self::color($s['color_primary'], '#0061FF')),
            '--secondary' => self::color($s['color_secondary'], '#0B1B34'),
            '--accent1' => self::color($s['color_accent1'], '#1AC1A5'),
            '--accent2' => self::color($s['color_accent2'], '#FF8A3D'),
            '--bg' => self::color($s['color_background'], '#FFFFFF'),
            '--text' => self::color($s['color_text'], '#0B1B34'),
            '--muted' => self::color($s['color_muted'], '#5B6B85'),
            '--border' => self::color($s['color_border'], '#E3E8F0'),
            '--font-body' => $font($s['body_font']) . $stack,
            '--font-heading' => $font($s['heading_font']) . $stack,
            '--weight-body' => (string) (int) $s['body_weight'] ?: '400',
            '--weight-heading' => (string) (int) $s['heading_weight'] ?: '700',
            '--font-size' => Sanitizer::length($s['base_font_size']) ?: '16px',
            '--lh-body' => self::number($s['body_line_height'], '1.6'),
            '--lh-heading' => self::number($s['heading_line_height'], '1.15'),
            '--container' => Sanitizer::length($s['container_width']) ?: '72rem',
            '--section-space' => Sanitizer::length($s['section_spacing']) ?: '5rem',
            '--section-space-m' => Sanitizer::length($s['section_spacing_mobile']) ?: '3rem',
            '--radius' => Sanitizer::length($s['border_radius']) ?: '0.75rem',
            '--card-radius' => Sanitizer::length($s['card_radius']) ?: '1rem',
            '--btn-radius' => Sanitizer::length($s['button_radius']) ?: '0.5rem',
        ];
        $css = ':root{';
        foreach ($vars as $k => $v) {
            $css .= $k . ':' . str_replace(['<', '>', '{', '}', ';'], '', $v) . ';';
        }
        return $css . '}';
    }

    private static function color(?string $value, string $fallback): string
    {
        $c = Sanitizer::color((string) $value);
        return $c !== '' ? $c : $fallback;
    }

    private static function number(?string $value, string $fallback): string
    {
        return preg_match('/^\d(\.\d{1,3})?$/', (string) $value) ? (string) $value : $fallback;
    }

    private static function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-f]{6}/i', $hex)) {
            return '0 97 255';
        }
        return hexdec(substr($hex, 0, 2)) . ' ' . hexdec(substr($hex, 2, 2)) . ' ' . hexdec(substr($hex, 4, 2));
    }
}
