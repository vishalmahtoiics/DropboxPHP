<?php

declare(strict_types=1);

namespace App\Cms;

use App\Core\Sanitizer;
use App\Services\Countries;
use App\Services\Money;

/** Small render helpers shared by block templates. */
final class Ui
{
    /** A link target for the current market: "/contact" → "/ae/contact". */
    public static function href(string $url): string
    {
        $url = Sanitizer::url($url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '/')) {
            return Countries::href(null, $url);
        }
        return $url;
    }

    /**
     * A button. "#popup-12" opens popup 12; "#form" opens the section's form;
     * anything else is an ordinary link.
     */
    public static function button(string $label, string $url, string $variant = 'primary', string $extra = ''): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }
        $href = self::href($url) ?: '#';
        $attrs = '';
        if (preg_match('/^#popup-(\d+)$/', $href, $m)) {
            $attrs = ' data-popup-open="' . (int) $m[1] . '"';
        }
        $external = preg_match('#^https?://#i', $href) && !str_starts_with($href, base_url());
        return '<a class="btn btn-' . e($variant) . ($extra ? ' ' . e($extra) : '') . '" href="' . e($href) . '"' . $attrs
            . ($external ? ' target="_blank" rel="noopener"' : '') . '>' . e($label) . '</a>';
    }

    /** @param array<string,mixed> $c */
    public static function buttons(array $c, string $class = 'btn-row'): string
    {
        $html = self::button((string) ($c['primary_label'] ?? ''), (string) ($c['primary_url'] ?? ''), 'primary', 'btn-lg')
            . self::button((string) ($c['secondary_label'] ?? ''), (string) ($c['secondary_url'] ?? ''), 'secondary', 'btn-lg');
        return $html === '' ? '' : '<div class="' . e($class) . '">' . $html . '</div>';
    }

    /** Eyebrow + heading + intro, the common section header. */
    public static function head(array $c, string $h = 'h2', string $align = 'center'): string
    {
        $eyebrow = trim((string) ($c['eyebrow'] ?? ''));
        $heading = trim((string) ($c['heading'] ?? ''));
        $sub = trim((string) ($c['subheading'] ?? ''));
        if ($eyebrow === '' && $heading === '' && $sub === '') {
            return '';
        }
        $tag = in_array($h, ['h1', 'h2', 'h3'], true) ? $h : 'h2';
        return '<header class="section-head align-' . e($align) . '">'
            . ($eyebrow !== '' ? '<p class="eyebrow">' . e($eyebrow) . '</p>' : '')
            . ($heading !== '' ? "<$tag class=\"section-title\">" . e($heading) . "</$tag>" : '')
            . ($sub !== '' ? '<p class="section-intro">' . nl2br(e($sub)) . '</p>' : '')
            . '</header>';
    }

    public static function img(string $src, string $alt = '', string $class = '', bool $lazy = true, string $extra = ''): string
    {
        $src = Sanitizer::url($src, true);
        if ($src === '') {
            return '';
        }
        return '<img src="' . e($src) . '" alt="' . e($alt) . '"' . ($class ? ' class="' . e($class) . '"' : '')
            . ($lazy ? ' loading="lazy" decoding="async"' : ' fetchpriority="high"') . $extra . '>';
    }

    /** Price line for a product card / page. */
    public static function price(array $p, string $period): array
    {
        $amount = $period === 'monthly' ? $p['monthly_price'] : ($p['annual_price'] ?? $p['monthly_price']);
        if ($amount === null || $amount === '') {
            return ['amount' => '', 'note' => (string) ($p['price_suffix'] ?: 'Contact us for pricing')];
        }
        return [
            'amount' => Money::format($amount, (string) $p['symbol'], (string) $p['currency']),
            'note' => trim((string) ($p['price_suffix'] ?? '') . ($period === 'annual' && $p['annual_price'] !== null ? ', billed annually' : '')),
        ];
    }

    /** "yes" / "no" / "partial" become icons in comparison cells. */
    public static function cell(string $value): string
    {
        $v = strtolower(trim($value));
        return match ($v) {
            'yes', 'y', '✓', 'true', 'included' => '<span class="cell-yes" aria-label="Included">' . Icons::svg('check', 'icon', 18) . '</span>',
            'no', 'n', '✗', 'x', 'false', '-', '—' => '<span class="cell-no" aria-label="Not included">' . Icons::svg('x', 'icon', 18) . '</span>',
            'partial', 'limited' => '<span class="cell-partial">Limited</span>',
            '' => '<span class="cell-empty">—</span>',
            default => e($value),
        };
    }

    /** YouTube / Vimeo embed URL (privacy-enhanced), or ''. */
    public static function videoEmbed(string $url): string
    {
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0';
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d{6,12})~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&dnt=1';
        }
        return '';
    }

    public static function youtubeThumb(string $url): string
    {
        return preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)
            ? 'https://i.ytimg.com/vi/' . $m[1] . '/hqdefault.jpg' : '';
    }
}
