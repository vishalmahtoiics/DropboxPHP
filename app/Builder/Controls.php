<?php

declare(strict_types=1);

namespace App\Builder;

use App\Cms\Icons;
use App\Core\Sanitizer;

/**
 * Editor controls: the settings an element exposes, how each value is
 * cleaned before it is stored, and the shared groups (typography,
 * background, border, spacing, visibility) every element reuses.
 *
 * A control is an array:
 *   key, type, label, default
 *   responsive  true = separate tablet/mobile values (key_tablet, key_mobile)
 *   choices     value => label (select, choose)
 *   units       allowed units (slider, dimensions)
 *   min, max    numeric bounds; max = text length for text types
 *   css         [selector => declaration] with {{W}} = the element, {{V}} = value
 *   dims        [selector, property] for dimensions (padding, margin, radius, border-width)
 *   showWhen    [key => [values]] — shown only when another control matches
 *   fields      sub-controls of a repeater; limit = maximum items
 *
 * Types: text, textarea, richtext, number, slider, dimensions, select,
 *        choose, switch, color, url, image, icon, repeater, list, form,
 *        magnet, products, category, brand, blogcategory, code (custom
 *        HTML/CSS, only with the builder.code permission), heading (label only)
 */
final class Controls
{
    public const DEVICES = ['desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile'];
    public const UNITS = ['px', '%', 'em', 'rem', 'vh', 'vw'];

    /** @param array<string, mixed> $o */
    public static function c(string $type, string $key, string $label, mixed $default = '', array $o = []): array
    {
        return ['key' => $key, 'type' => $type, 'label' => $label, 'default' => $default] + $o;
    }

    public static function heading(string $label): array
    {
        return ['key' => '', 'type' => 'heading', 'label' => $label, 'default' => ''];
    }

    /** @return array<string, string> */
    public static function alignChoices(bool $justify = false): array
    {
        $c = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'];
        return $justify ? $c + ['justify' => 'Justify'] : $c;
    }

    /**
     * Font size, weight, line height, letter spacing and case for $selector.
     *
     * @return list<array<string, mixed>>
     */
    public static function typography(string $prefix, string $selector): array
    {
        return [
            self::c('slider', $prefix . 'font_size', 'Font size', ['size' => '', 'unit' => 'px'], ['responsive' => true, 'units' => ['px', 'rem', 'em', 'vw'], 'min' => 0, 'max' => 200, 'css' => [$selector => 'font-size:{{V}}']]),
            self::c('select', $prefix . 'font_weight', 'Weight', '', ['choices' => ['' => 'Default', '300' => 'Light', '400' => 'Normal', '500' => 'Medium', '600' => 'Semi-bold', '700' => 'Bold', '800' => 'Extra bold'], 'css' => [$selector => 'font-weight:{{V}}']]),
            self::c('slider', $prefix . 'line_height', 'Line height', ['size' => '', 'unit' => 'em'], ['responsive' => true, 'units' => ['em', 'px'], 'min' => 0, 'max' => 10, 'step' => 0.05, 'css' => [$selector => 'line-height:{{V}}']]),
            self::c('slider', $prefix . 'letter_spacing', 'Letter spacing', ['size' => '', 'unit' => 'px'], ['units' => ['px', 'em'], 'min' => -10, 'max' => 20, 'step' => 0.1, 'css' => [$selector => 'letter-spacing:{{V}}']]),
            self::c('select', $prefix . 'text_transform', 'Case', '', ['choices' => ['' => 'Default', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize', 'none' => 'Normal'], 'css' => [$selector => 'text-transform:{{V}}']]),
        ];
    }

    /** Background, border and shadow for the element box (prefix "_"). */
    public static function boxStyle(): array
    {
        $bg = static fn (string $t) => ['_bg_type' => [$t]];
        return [
            ['label' => 'Background', 'controls' => [
                self::c('choose', '_bg_type', 'Background', 'none', ['choices' => ['none' => 'None', 'color' => 'Colour', 'gradient' => 'Gradient', 'image' => 'Image']]),
                self::c('color', '_bg_color', 'Colour', '', ['showWhen' => $bg('color')]),
                self::c('color', '_bg_from', 'From', '', ['showWhen' => $bg('gradient')]),
                self::c('color', '_bg_to', 'To', '', ['showWhen' => $bg('gradient')]),
                self::c('number', '_bg_angle', 'Angle (deg)', 135, ['min' => 0, 'max' => 360, 'showWhen' => $bg('gradient')]),
                self::c('image', '_bg_image', 'Image', '', ['showWhen' => $bg('image')]),
                self::c('select', '_bg_position', 'Position', 'center center', ['showWhen' => $bg('image'), 'choices' => ['center center' => 'Centre', 'top center' => 'Top', 'bottom center' => 'Bottom', 'center left' => 'Left', 'center right' => 'Right']]),
                self::c('select', '_bg_size', 'Size', 'cover', ['showWhen' => $bg('image'), 'choices' => ['cover' => 'Cover', 'contain' => 'Contain', 'auto' => 'Original']]),
                self::c('switch', '_bg_fixed', 'Fixed (parallax)', false, ['showWhen' => $bg('image')]),
                self::c('color', '_overlay_color', 'Overlay colour', '', ['showWhen' => ['_bg_type' => ['image', 'gradient', 'color']]]),
                self::c('number', '_overlay_opacity', 'Overlay opacity (%)', 50, ['min' => 0, 'max' => 100, 'showWhen' => ['_bg_type' => ['image', 'gradient', 'color']]]),
            ]],
            ['label' => 'Border', 'controls' => [
                self::c('select', '_border_style', 'Border', '', ['choices' => ['' => 'None', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double']]),
                self::c('dimensions', '_border_width', 'Width', self::dims(), ['units' => ['px'], 'showWhen' => ['_border_style' => ['solid', 'dashed', 'dotted', 'double']], 'dims' => ['{{W}}', 'border-width']]),
                self::c('color', '_border_color', 'Colour', '', ['showWhen' => ['_border_style' => ['solid', 'dashed', 'dotted', 'double']], 'css' => ['{{W}}' => 'border-color:{{V}}']]),
                self::c('dimensions', '_radius', 'Corner radius', self::dims(), ['responsive' => true, 'units' => ['px', '%', 'rem'], 'dims' => ['{{W}}', 'border-radius']]),
                self::c('select', '_shadow', 'Shadow', '', ['choices' => ['' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large']]),
            ]],
        ];
    }

    /**
     * The Advanced tab: spacing, size, position, identity, motion, visibility.
     *
     * @return list<array{label:string, controls:list<array<string,mixed>>}>
     */
    public static function advanced(bool $widget): array
    {
        $layout = [
            self::c('dimensions', '_margin', 'Margin', self::dims(), ['responsive' => true, 'negative' => true, 'dims' => ['{{W}}', 'margin']]),
            self::c('dimensions', '_padding', 'Padding', self::dims(), ['responsive' => true, 'dims' => ['{{W}}', 'padding']]),
        ];
        if ($widget) {
            $layout[] = self::c('select', '_width', 'Width', '', ['choices' => ['' => 'Default', 'full' => 'Full width (100%)', 'inline' => 'Inline (auto)', 'custom' => 'Custom']]);
            $layout[] = self::c('slider', '_custom_width', 'Custom width', ['size' => '', 'unit' => '%'], ['responsive' => true, 'units' => ['%', 'px', 'vw'], 'min' => 0, 'max' => 2000, 'showWhen' => ['_width' => ['custom']], 'css' => ['{{W}}' => 'width:{{V}};max-width:{{V}}']]);
        }
        $layout[] = self::c('choose', '_align_self', 'Align self', '', ['responsive' => true, 'choices' => ['' => 'Default', 'flex-start' => 'Start', 'center' => 'Centre', 'flex-end' => 'End', 'stretch' => 'Stretch'], 'css' => ['{{W}}' => 'align-self:{{V}}']]);
        $layout[] = self::c('number', '_order', 'Order', '', ['responsive' => true, 'min' => -20, 'max' => 20, 'css' => ['{{W}}' => 'order:{{V}}']]);
        $layout[] = self::c('number', '_z', 'Z-index', '', ['min' => -10, 'max' => 9999, 'css' => ['{{W}}' => 'z-index:{{V}};position:relative']]);
        $layout[] = self::c('text', '_css_id', 'CSS ID (anchor)', '', ['max' => 64, 'help' => 'Letters, numbers, - and _. Link to it with #id.']);
        $layout[] = self::c('text', '_css_classes', 'CSS classes', '', ['max' => 200]);

        $sections = [['label' => 'Layout', 'controls' => $layout]];
        if ($widget) {
            $sections = array_merge($sections, self::boxStyle());
        }
        $sections[] = ['label' => 'Motion', 'controls' => [
            self::c('select', '_animation', 'Entrance animation', '', ['choices' => ['' => 'None', 'fade-in' => 'Fade in', 'fade-up' => 'Fade up', 'fade-down' => 'Fade down', 'slide-left' => 'Slide from right', 'slide-right' => 'Slide from left', 'zoom-in' => 'Zoom in']]),
            self::c('number', '_animation_delay', 'Delay (ms)', 0, ['min' => 0, 'max' => 3000, 'showWhen' => ['_animation' => ['fade-in', 'fade-up', 'fade-down', 'slide-left', 'slide-right', 'zoom-in']]]),
        ]];
        $sections[] = ['label' => 'Position', 'controls' => [
            self::c('select', '_position', 'Position', '', ['choices' => ['' => 'Default', 'sticky' => 'Sticky (stays on screen while scrolling)', 'relative' => 'Relative']]),
            self::c('slider', '_sticky_top', 'Distance from the top', ['size' => 0, 'unit' => 'px'], ['units' => ['px', 'vh'], 'min' => 0, 'max' => 500, 'showWhen' => ['_position' => ['sticky']]]),
        ]];
        $sections[] = ['label' => 'Custom CSS', 'code' => true, 'controls' => [
            self::c('code', '_custom_css', 'Custom CSS', '', ['code' => true, 'max' => Code::MAX_CSS, 'lang' => 'css', 'help' => 'Write "selector" for this element, e.g. selector:hover { opacity: .8 }']),
        ]];
        $sections[] = ['label' => 'Responsive', 'controls' => [
            self::c('switch', '_hide_desktop', 'Hide on desktop', false),
            self::c('switch', '_hide_tablet', 'Hide on tablet', false),
            self::c('switch', '_hide_mobile', 'Hide on mobile', false),
        ]];
        return $sections;
    }

    /** @return array{top:string,right:string,bottom:string,left:string,unit:string,linked:bool} */
    public static function dims(string $all = '', string $unit = 'px'): array
    {
        return ['top' => $all, 'right' => $all, 'bottom' => $all, 'left' => $all, 'unit' => $unit, 'linked' => true];
    }

    // --- normalising values -----------------------------------------------------------

    /** Clean one stored value against its control. Never throws. */
    public static function clean(array $control, mixed $value): mixed
    {
        $default = $control['default'] ?? '';
        $max = (int) ($control['max'] ?? 0);
        switch ($control['type']) {
            case 'text':
                return Sanitizer::line(is_scalar($value) ? (string) $value : '', $max > 0 ? $max : 500);
            case 'textarea':
                return Sanitizer::text(is_scalar($value) ? (string) $value : '', $max > 0 ? $max : 5000);
            case 'code':
                // Custom code (permission-gated); CSS is cleaned when it is output.
                return str_replace("\0", '', mb_substr(is_string($value) ? $value : '', 0, $max > 0 ? $max : Code::MAX_CSS));
            case 'richtext':
                return Sanitizer::html(mb_substr(is_string($value) ? $value : '', 0, 60000));
            case 'number':
                if ($value === '' || $value === null || !is_numeric($value)) {
                    return $default === '' ? '' : $default;
                }
                $n = $value + 0;
                if (isset($control['min'])) $n = max($control['min'], $n);
                if (isset($control['max'])) $n = min($control['max'], $n);
                return $n;
            case 'slider':
                return self::cleanSlider($control, $value);
            case 'dimensions':
                return self::cleanDims($control, $value);
            case 'select':
            case 'choose':
                $value = is_scalar($value) ? (string) $value : '';
                return array_key_exists($value, $control['choices'] ?? []) ? $value : (string) $default;
            case 'switch':
                return in_array($value, [true, 1, '1', 'on', 'true', 'yes'], true);
            case 'color':
                return Sanitizer::color(is_string($value) ? $value : '');
            case 'url':
                return self::cleanUrl(is_string($value) ? $value : '');
            case 'image':
                return Sanitizer::url(is_string($value) ? $value : '', true);
            case 'icon':
                return is_string($value) && in_array($value, Icons::names(), true) ? $value : '';
            case 'list':
                $lines = is_array($value) ? $value : preg_split('/\R/', is_string($value) ? $value : '');
                $out = [];
                foreach (array_slice($lines ?: [], 0, 60) as $line) {
                    $line = Sanitizer::line(is_scalar($line) ? (string) $line : '', 300);
                    if ($line !== '') {
                        $out[] = $line;
                    }
                }
                return $out;
            case 'repeater':
                $items = [];
                foreach (array_slice(is_array($value) ? array_values($value) : [], 0, (int) ($control['limit'] ?? 50)) as $item) {
                    $item = is_array($item) ? $item : [];
                    $row = [];
                    foreach ($control['fields'] ?? [] as $field) {
                        $row[$field['key']] = array_key_exists($field['key'], $item) ? self::clean($field, $item[$field['key']]) : self::defaultOf($field);
                    }
                    $items[] = $row;
                }
                return $items;
            case 'products':
                $ids = is_array($value) ? $value : explode(',', is_string($value) ? $value : '');
                return array_values(array_unique(array_filter(array_map('intval', array_slice($ids, 0, 24)), static fn ($i) => $i > 0)));
            case 'form':
            case 'magnet':
            case 'category':
            case 'brand':
            case 'blogcategory':
                $value = is_scalar($value) ? (string) $value : '';
                return preg_match('/^[A-Za-z0-9_\-]{0,160}$/', $value) ? $value : '';
            default:
                return $default;
        }
    }

    public static function defaultOf(array $control): mixed
    {
        $d = $control['default'] ?? '';
        return match ($control['type']) {
            'switch' => (bool) $d,
            'list', 'products', 'repeater' => is_array($d) ? $d : [],
            'slider' => is_array($d) ? $d : ['size' => '', 'unit' => ($control['units'] ?? self::UNITS)[0]],
            'dimensions' => is_array($d) ? $d : self::dims(),
            default => $d,
        };
    }

    private static function cleanSlider(array $control, mixed $value): array
    {
        $units = $control['units'] ?? self::UNITS;
        $default = is_array($control['default'] ?? null) ? $control['default'] : ['size' => '', 'unit' => $units[0]];
        if (!is_array($value)) {
            return $default + ['size' => '', 'unit' => $units[0]];
        }
        $unit = in_array($value['unit'] ?? '', $units, true) ? $value['unit'] : $units[0];
        $size = $value['size'] ?? '';
        if ($size === '' || $size === null || !is_numeric($size)) {
            return ['size' => '', 'unit' => $unit];
        }
        $n = round((float) $size, 3);
        $n = max((float) ($control['min'] ?? -5000), min((float) ($control['max'] ?? 5000), $n));
        return ['size' => $n == (int) $n ? (int) $n : $n, 'unit' => $unit];
    }

    private static function cleanDims(array $control, mixed $value): array
    {
        $units = $control['units'] ?? ['px', '%', 'em', 'rem'];
        $out = self::dims('', $units[0]);
        if (!is_array($value)) {
            return $out;
        }
        $neg = !empty($control['negative']);
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $v = $value[$side] ?? '';
            if ($v === '' || $v === null || !is_numeric($v)) {
                continue;
            }
            $n = round((float) $v, 2);
            $n = max($neg ? -2000 : 0, min(2000, $n));
            $out[$side] = (string) ($n == (int) $n ? (int) $n : $n);
        }
        $out['unit'] = in_array($value['unit'] ?? '', $units, true) ? $value['unit'] : $units[0];
        $out['linked'] = !empty($value['linked']);
        return $out;
    }

    /** Links: absolute http(s)/mailto/tel, site paths, #anchors and #popup-ID. */
    public static function cleanUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('/^#[A-Za-z][A-Za-z0-9\-_]{0,80}$/', $url)) {
            return $url;
        }
        return mb_substr(Sanitizer::url($url), 0, 500);
    }

    // --- formatting values for CSS ------------------------------------------------------

    /** A slider value as CSS, or '' when unset. */
    public static function cssSlider(mixed $v): string
    {
        if (!is_array($v) || ($v['size'] ?? '') === '' || !is_numeric($v['size'])) {
            return '';
        }
        return $v['size'] . ($v['unit'] ?? 'px');
    }

    /** @return array<string, string> side => css length (only the sides that are set) */
    public static function cssDims(mixed $v): array
    {
        if (!is_array($v)) {
            return [];
        }
        $out = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            if (isset($v[$side]) && $v[$side] !== '' && is_numeric($v[$side])) {
                $out[$side] = $v[$side] . ($v[$side] === '0' ? '' : ($v['unit'] ?? 'px'));
            }
        }
        return $out;
    }
}
