<?php

declare(strict_types=1);

namespace App\Builder;

/**
 * Turns element settings into CSS scoped to each element's class (.el-<id>).
 *
 * Desktop values are plain rules; tablet and mobile values go into one media
 * query each and only contain what was set for that screen size. Every value
 * reaching this class has already been cleaned by its control (numbers,
 * whitelisted units and keywords, validated colours and URLs), so nothing
 * here can break out of its declaration.
 */
final class Style
{
    public const MEDIA = ['tablet' => '(max-width:1024px)', 'mobile' => '(max-width:767px)'];

    private const SHADOWS = [
        'sm' => '0 1px 3px rgba(11,27,52,.12)',
        'md' => '0 6px 20px rgba(11,27,52,.12)',
        'lg' => '0 16px 40px rgba(11,27,52,.16)',
        'xl' => '0 28px 70px rgba(11,27,52,.22)',
    ];

    /** CSS for a whole layout. */
    public static function layout(array $layout): string
    {
        $buckets = ['desktop' => '', 'tablet' => '', 'mobile' => '', 'custom' => ''];
        self::walk($layout['elements'] ?? [], $buckets);
        $css = $buckets['desktop'];
        foreach (self::MEDIA as $device => $query) {
            if ($buckets[$device] !== '') {
                $css .= '@media ' . $query . '{' . $buckets[$device] . '}';
            }
        }
        $page = (string) ($layout['settings']['custom_css'] ?? '');
        return $css . $buckets['custom'] . ($page !== '' ? Code::css($page, '.bx-root') : '');
    }

    /** @param array<string, string> $buckets */
    private static function walk(array $elements, array &$buckets): void
    {
        foreach ($elements as $el) {
            foreach (self::element($el) as $device => $rules) {
                $buckets[$device] .= $rules;
            }
            if (!empty($el['children'])) {
                self::walk($el['children'], $buckets);
            }
        }
    }

    /** @return array<string, string> device => rules */
    public static function element(array $el): array
    {
        $type = (string) $el['type'];
        $s = $el['settings'] ?? [];
        $w = '.el-' . $el['id'];
        $rules = ['desktop' => [], 'tablet' => [], 'mobile' => []];
        $add = static function (string $device, string $selector, string $decl) use (&$rules): void {
            if ($decl !== '') {
                $rules[$device][$selector][] = $decl;
            }
        };

        foreach (Elements::controls($type) as $control) {
            if (!isset($control['css']) && !isset($control['dims'])) {
                continue;
            }
            foreach (Controls::DEVICES as $device => $suffix) {
                if ($suffix !== '' && empty($control['responsive'])) {
                    continue;
                }
                $key = $control['key'] . $suffix;
                if (!array_key_exists($key, $s)) {
                    if ($suffix !== '') {
                        continue;
                    }
                    $value = Controls::defaultOf($control);
                } else {
                    $value = $s[$key];
                }
                if (isset($control['dims'])) {
                    [$sel, $prop] = $control['dims'];
                    foreach (Controls::cssDims($value) as $side => $len) {
                        $add($device, str_replace('{{W}}', $w, $sel), self::sideProp($prop, $side) . ':' . $len);
                    }
                    continue;
                }
                $css = match ($control['type']) {
                    'slider' => Controls::cssSlider($value),
                    'switch' => $value ? '1' : '',
                    default => is_scalar($value) ? (string) $value : '',
                };
                if ($css === '') {
                    continue;
                }
                foreach ($control['css'] as $sel => $decl) {
                    $add($device, str_replace('{{W}}', $w, $sel), str_replace('{{V}}', $css, $decl));
                }
            }
        }

        // Groups that combine several settings.
        self::background($s, $w, $add);
        if (($s['_border_style'] ?? '') !== '') {
            $add('desktop', $w, 'border-style:' . $s['_border_style']);
            if (Controls::cssDims($s['_border_width'] ?? null) === []) {
                $add('desktop', $w, 'border-width:1px');
            }
        }
        if (isset(self::SHADOWS[$s['_shadow'] ?? ''])) {
            $add('desktop', $w, 'box-shadow:' . self::SHADOWS[$s['_shadow']]);
        }
        if (isset(self::SHADOWS[$s['img_shadow'] ?? ''])) {
            $add('desktop', $w . ' img', 'box-shadow:' . self::SHADOWS[$s['img_shadow']]);
        }
        $width = $s['_width'] ?? '';
        if ($width === 'full') {
            // flex-basis auto + width 100%: full width in a row, natural height in a column
            // (a 100% basis would mean 100% of the height there).
            $add('desktop', $w, 'width:100%;flex:0 0 auto;max-width:100%;align-self:stretch');
        } elseif ($width === 'inline') {
            $add('desktop', $w, 'width:auto;flex:0 0 auto');
        } elseif ($width === 'custom') {
            $add('desktop', $w, 'flex:0 0 auto');
        }
        if (($s['_animation'] ?? '') !== '' && (int) ($s['_animation_delay'] ?? 0) > 0) {
            $add('desktop', $w, 'animation-delay:' . (int) $s['_animation_delay'] . 'ms;transition-delay:' . (int) $s['_animation_delay'] . 'ms');
        }

        $position = $s['_position'] ?? '';
        if ($position === 'sticky') {
            $top = Controls::cssSlider($s['_sticky_top'] ?? null) ?: '0px';
            $add('desktop', $w, 'position:sticky;top:' . $top . ';z-index:' . (isset($s['_z']) && $s['_z'] !== '' ? (int) $s['_z'] : 50));
        } elseif ($position === 'relative') {
            $add('desktop', $w, 'position:relative');
        }

        $out = [];
        foreach ($rules as $device => $selectors) {
            $css = '';
            foreach ($selectors as $sel => $decls) {
                $css .= $sel . '{' . implode(';', $decls) . '}';
            }
            if ($css !== '') {
                $out[$device] = $css;
            }
        }
        // Custom CSS goes last so it can override the generated rules.
        $custom = '';
        foreach (['_custom_css', 'css'] as $key) {
            if ($key === 'css' && $type !== 'html') {
                continue;
            }
            if (is_string($s[$key] ?? null) && trim($s[$key]) !== '') {
                $custom .= Code::css($s[$key], $w);
            }
        }
        if ($custom !== '') {
            $out['custom'] = $custom;
        }
        return $out;
    }

    private static function sideProp(string $prop, string $side): string
    {
        if ($prop === 'border-radius') {
            return ['top' => 'border-top-left-radius', 'right' => 'border-top-right-radius', 'bottom' => 'border-bottom-right-radius', 'left' => 'border-bottom-left-radius'][$side];
        }
        if ($prop === 'border-width') {
            return 'border-' . $side . '-width';
        }
        return $prop . '-' . $side;
    }

    /** @param array<string, mixed> $s */
    private static function background(array $s, string $w, callable $add): void
    {
        $type = $s['_bg_type'] ?? 'none';
        if ($type === 'color' && ($s['_bg_color'] ?? '') !== '') {
            $add('desktop', $w, 'background-color:' . $s['_bg_color']);
        } elseif ($type === 'gradient' && ($s['_bg_from'] ?? '') !== '' && ($s['_bg_to'] ?? '') !== '') {
            $angle = (int) ($s['_bg_angle'] ?? 135);
            $add('desktop', $w, "background-image:linear-gradient({$angle}deg,{$s['_bg_from']},{$s['_bg_to']})");
        } elseif ($type === 'image' && ($s['_bg_image'] ?? '') !== '') {
            $url = str_replace(['"', '\\', "\n", "\r", '(', ')', ' '], ['%22', '', '', '', '%28', '%29', '%20'], (string) $s['_bg_image']);
            $add('desktop', $w, 'background-image:url("' . $url . '")');
            $add('desktop', $w, 'background-position:' . ($s['_bg_position'] ?? 'center center'));
            $add('desktop', $w, 'background-size:' . ($s['_bg_size'] ?? 'cover'));
            $add('desktop', $w, 'background-repeat:no-repeat');
            if (!empty($s['_bg_fixed'])) {
                $add('desktop', $w, 'background-attachment:fixed');
            }
        }
        $opacity = (int) ($s['_overlay_opacity'] ?? 50);
        if ($type !== 'none' && ($s['_overlay_color'] ?? '') !== '' && $opacity > 0) {
            $add('desktop', $w, 'position:relative;isolation:isolate');
            $add('desktop', $w . '::before', "content:'';position:absolute;inset:0;background:" . $s['_overlay_color'] . ';opacity:' . round($opacity / 100, 2) . ';border-radius:inherit;pointer-events:none;z-index:-1');
        }
    }
}
