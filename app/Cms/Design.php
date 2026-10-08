<?php

declare(strict_types=1);

namespace App\Cms;

use App\Core\Sanitizer;

/**
 * The Design panel shared by every section, whatever its block type.
 *
 * Values are stored as finished CSS (lengths like 40px / 2.5rem / 85%,
 * colours like #0061FF) and emitted as custom properties: desktop values on
 * the <section> itself, tablet and mobile in one scoped <style> with a media
 * query each, containing only what differs. A section left at its defaults
 * emits no CSS at all. Blank tablet/mobile values inherit from the larger
 * breakpoint.
 */
final class Design
{
    public const PRESETS = ['default' => 'Default', 'muted' => 'Muted', 'brand' => 'Brand colour', 'dark' => 'Dark', 'gradient' => 'Brand gradient'];
    public const WIDTHS = ['boxed' => 'Boxed (site width)', 'narrow' => 'Narrow', 'wide' => 'Wide', 'full' => 'Full width'];
    public const BREAKPOINTS = ['desktop', 'tablet', 'mobile'];
    public const SPACING = ['padding_top', 'padding_bottom', 'margin_top', 'margin_bottom', 'min_height', 'gap', 'heading_size', 'text_size'];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $bp = array_fill_keys(self::SPACING, '');
        return [
            'preset' => 'default',
            'width' => 'boxed',
            'text_align' => '',
            'bg_type' => 'none',
            'bg_color' => '',
            'bg_from' => '',
            'bg_to' => '',
            'bg_angle' => 135,
            'bg_image' => '',
            'bg_size' => 'cover',
            'bg_position' => 'center',
            'bg_fixed' => false,
            'overlay_color' => '',
            'overlay_opacity' => 0,
            'text_color' => '',
            'heading_color' => '',
            'accent_color' => '',
            'card_bg' => '',
            'anchor' => '',
            'css_class' => '',
            'mobile_columns' => '1',
            'hide_desktop' => false,
            'hide_tablet' => false,
            'hide_mobile' => false,
            'desktop' => $bp,
            'tablet' => $bp,
            'mobile' => $bp,
        ];
    }

    /** @return array<string, mixed> */
    public static function normalize(mixed $input): array
    {
        $in = is_array($input) ? $input : [];
        $d = self::defaults();
        $out = $d;
        $out['preset'] = isset(self::PRESETS[$in['preset'] ?? '']) ? $in['preset'] : 'default';
        $out['width'] = isset(self::WIDTHS[$in['width'] ?? '']) ? $in['width'] : 'boxed';
        $out['text_align'] = in_array($in['text_align'] ?? '', ['', 'left', 'center', 'right'], true) ? ($in['text_align'] ?? '') : '';
        $out['bg_type'] = in_array($in['bg_type'] ?? '', ['none', 'color', 'gradient', 'image'], true) ? $in['bg_type'] : 'none';
        foreach (['bg_color', 'bg_from', 'bg_to', 'overlay_color', 'text_color', 'heading_color', 'accent_color', 'card_bg'] as $k) {
            $out[$k] = Sanitizer::color((string) ($in[$k] ?? ''));
        }
        $out['bg_angle'] = max(0, min(360, (int) ($in['bg_angle'] ?? 135)));
        $out['bg_image'] = Sanitizer::url((string) ($in['bg_image'] ?? ''), true);
        $out['bg_size'] = in_array($in['bg_size'] ?? '', ['cover', 'contain', 'auto'], true) ? $in['bg_size'] : 'cover';
        $out['bg_position'] = in_array($in['bg_position'] ?? '', ['center', 'top', 'bottom', 'left', 'right'], true) ? $in['bg_position'] : 'center';
        $out['bg_fixed'] = !empty($in['bg_fixed']);
        $out['overlay_opacity'] = max(0, min(100, (int) ($in['overlay_opacity'] ?? 0)));
        $anchor = strtolower(trim((string) ($in['anchor'] ?? '')));
        $out['anchor'] = preg_match('/^[a-z][a-z0-9\-_]{0,63}$/', $anchor) ? $anchor : '';
        $out['css_class'] = trim(preg_replace('/[^a-z0-9\-_ ]/i', '', (string) ($in['css_class'] ?? '')) ?? '');
        $out['mobile_columns'] = (string) ($in['mobile_columns'] ?? '1') === '2' ? '2' : '1';
        foreach (['hide_desktop', 'hide_tablet', 'hide_mobile'] as $k) {
            $out[$k] = !empty($in[$k]);
        }
        foreach (self::BREAKPOINTS as $bp) {
            foreach (self::SPACING as $prop) {
                $out[$bp][$prop] = Sanitizer::length((string) ($in[$bp][$prop] ?? ''));
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $d normalised design
     * @return array{style:string, css:string, classes:string, id:string}
     */
    public static function render(array $d, string $sectionKey): array
    {
        $vars = [];
        $map = [
            'padding_top' => '--s-pt', 'padding_bottom' => '--s-pb', 'margin_top' => '--s-mt', 'margin_bottom' => '--s-mb',
            'min_height' => '--s-minh', 'gap' => '--s-gap', 'heading_size' => '--s-h', 'text_size' => '--s-fs',
        ];
        foreach ($map as $prop => $var) {
            if (($d['desktop'][$prop] ?? '') !== '') {
                $vars[$var] = $d['desktop'][$prop];
            }
        }
        foreach (['text_color' => '--s-text', 'heading_color' => '--s-heading', 'accent_color' => '--s-accent', 'card_bg' => '--s-card'] as $k => $var) {
            if ($d[$k] !== '') {
                $vars[$var] = $d[$k];
            }
        }
        $background = '';
        if ($d['bg_type'] === 'color' && $d['bg_color'] !== '') {
            $background = $d['bg_color'];
        } elseif ($d['bg_type'] === 'gradient' && $d['bg_from'] !== '' && $d['bg_to'] !== '') {
            $background = "linear-gradient({$d['bg_angle']}deg, {$d['bg_from']}, {$d['bg_to']})";
        } elseif ($d['bg_type'] === 'image' && $d['bg_image'] !== '') {
            $url = str_replace(['"', '\\', "\n", "\r", ')'], ['%22', '', '', '', '%29'], $d['bg_image']);
            $background = 'url("' . $url . '") ' . $d['bg_position'] . ' / ' . $d['bg_size'] . ' no-repeat' . ($d['bg_fixed'] ? ' fixed' : '');
        }
        if ($background !== '') {
            $vars['--s-bg'] = $background;
        }
        if ($d['overlay_color'] !== '' && $d['overlay_opacity'] > 0) {
            $vars['--s-overlay'] = $d['overlay_color'];
            $vars['--s-overlay-o'] = (string) round($d['overlay_opacity'] / 100, 2);
        }
        if ($d['text_align'] !== '') {
            $vars['--s-align'] = $d['text_align'];
        }

        $style = '';
        foreach ($vars as $k => $v) {
            $style .= $k . ':' . $v . ';';
        }

        $css = '';
        $sel = '.cms-section[data-s="' . $sectionKey . '"]';
        foreach (['tablet' => 1024, 'mobile' => 640] as $bp => $max) {
            $rules = '';
            foreach ($map as $prop => $var) {
                if (($d[$bp][$prop] ?? '') !== '') {
                    $rules .= $var . ':' . $d[$bp][$prop] . ';';
                }
            }
            if ($rules !== '') {
                $css .= "@media (max-width:{$max}px){{$sel}{{$rules}}}";
            }
        }

        $classes = ['cms-section', 'preset-' . $d['preset'], 'w-' . $d['width']];
        if ($background !== '') $classes[] = 'has-bg';
        if (isset($vars['--s-overlay'])) $classes[] = 'has-overlay';
        if ($d['hide_desktop']) $classes[] = 'hide-desktop';
        if ($d['hide_tablet']) $classes[] = 'hide-tablet';
        if ($d['hide_mobile']) $classes[] = 'hide-mobile';
        if ($d['mobile_columns'] === '2') $classes[] = 'm-cols-2';
        if ($d['css_class'] !== '') $classes[] = $d['css_class'];

        return ['style' => $style, 'css' => $css, 'classes' => implode(' ', $classes), 'id' => $d['anchor']];
    }
}
