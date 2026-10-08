<?php

declare(strict_types=1);

namespace App\Builder;

use App\Cms\Blocks;
use App\Cms\Design;

/**
 * A builder layout: {"version":1,"elements":[...]} where each element is
 * {"id","type","settings",["children"]}.
 *
 * Everything the editor sends goes through normalize() before it is stored or
 * rendered: unknown element types and settings are dropped, every value is
 * cleaned by its control, ids are made unique, and the tree is capped in size
 * and depth. Settings are stored sparsely — only values that differ from the
 * control's default — so improving a default later reaches existing pages.
 */
final class Layout
{
    public const VERSION = 1;
    public const MAX_ELEMENTS = 1500;
    public const MAX_DEPTH = 10;
    public const MAX_BYTES = 1_900_000; // requests carry at most 2 MB of JSON

    public static function empty(): array
    {
        return ['version' => self::VERSION, 'elements' => []];
    }

    /**
     * Custom code stays as it was for staff without the builder.code
     * permission: HTML widgets and custom CSS from $stored are kept by
     * element id, new or changed code from $incoming is discarded.
     */
    public static function keepCode(array $incoming, array $stored): array
    {
        $old = [];
        $index = static function (array $elements) use (&$index, &$old): void {
            foreach ($elements as $el) {
                $old[$el['id']] = $el;
                if (!empty($el['children'])) {
                    $index($el['children']);
                }
            }
        };
        $index($stored['elements'] ?? []);
        $codeKeys = self::codeKeys();
        $walk = static function (array $elements) use (&$walk, $old, $codeKeys): array {
            $out = [];
            foreach ($elements as $el) {
                $was = $old[$el['id']] ?? null;
                if ($was !== null && $was['type'] !== $el['type']) {
                    $was = null;
                }
                if (!empty(Elements::get($el['type'])['code']) && $was === null) {
                    continue; // a new HTML widget
                }
                foreach ($codeKeys[$el['type']] ?? [] as $key) {
                    if ($was !== null && array_key_exists($key, $was['settings'])) {
                        $el['settings'][$key] = $was['settings'][$key];
                    } else {
                        unset($el['settings'][$key]);
                    }
                }
                if (isset($el['children'])) {
                    $el['children'] = $walk($el['children']);
                }
                $out[] = $el;
            }
            return $out;
        };
        $incoming['elements'] = $walk($incoming['elements'] ?? []);
        unset($incoming['settings']['custom_css']);
        if (isset($stored['settings']['custom_css'])) {
            $incoming['settings']['custom_css'] = $stored['settings']['custom_css'];
        }
        if (isset($incoming['settings']) && $incoming['settings'] === []) {
            unset($incoming['settings']);
        }
        return $incoming;
    }

    /** @return array<string, list<string>> element type => its code settings */
    private static function codeKeys(): array
    {
        static $keys = null;
        if ($keys === null) {
            $keys = [];
            foreach (array_keys(Elements::all()) as $type) {
                foreach (Elements::controls($type) as $c) {
                    if (!empty($c['code'])) {
                        $keys[$type][] = $c['key'];
                    }
                }
            }
        }
        return $keys;
    }

    /** A stored JSON layout, normalised; empty for null or broken JSON. */
    public static function decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return self::empty();
        }
        $data = json_decode($json, true);
        return is_array($data) ? self::normalize($data) : self::empty();
    }

    public static function encode(array $layout): string
    {
        return (string) json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function isEmpty(?array $layout): bool
    {
        return $layout === null || ($layout['elements'] ?? []) === [];
    }

    public static function newId(): string
    {
        return substr(bin2hex(random_bytes(5)), 0, 8);
    }

    /** @param array<string, mixed>|mixed $doc */
    public static function normalize(mixed $doc): array
    {
        $elements = is_array($doc) ? ($doc['elements'] ?? (array_is_list($doc) ? $doc : [])) : [];
        $state = ['count' => 0, 'ids' => []];
        $out = [];
        foreach (is_array($elements) ? $elements : [] as $el) {
            $clean = self::element($el, 1, $state);
            if ($clean === null) {
                continue;
            }
            // Widgets live in containers: wrap a loose top-level widget in one.
            if ($clean['type'] !== 'container') {
                $clean = ['id' => self::uniqueId(null, $state), 'type' => 'container', 'settings' => [], 'children' => [$clean]];
            }
            $out[] = $clean;
        }
        $layout = ['version' => self::VERSION, 'elements' => $out];
        $css = is_array($doc) && is_string($doc['settings']['custom_css'] ?? null) ? Controls::clean(['type' => 'code', 'max' => Code::MAX_CSS], $doc['settings']['custom_css']) : '';
        if (trim($css) !== '') {
            $layout['settings'] = ['custom_css' => $css];
        }
        return $layout;
    }

    /** @param array{count:int, ids:array<string,bool>} $state */
    private static function element(mixed $el, int $depth, array &$state): ?array
    {
        if (!is_array($el) || $depth > self::MAX_DEPTH || $state['count'] >= self::MAX_ELEMENTS) {
            return null;
        }
        $type = is_string($el['type'] ?? null) ? $el['type'] : '';
        if (!Elements::exists($type)) {
            return null;
        }
        $state['count']++;
        $out = [
            'id' => self::uniqueId(is_string($el['id'] ?? null) ? $el['id'] : null, $state),
            'type' => $type,
            'settings' => self::settings($type, is_array($el['settings'] ?? null) ? $el['settings'] : []),
        ];
        if ($type === 'container') {
            $children = [];
            foreach (is_array($el['children'] ?? null) ? $el['children'] : [] as $child) {
                $c = self::element($child, $depth + 1, $state);
                if ($c !== null) {
                    $children[] = $c;
                }
            }
            $out['children'] = $children;
        }
        return $out;
    }

    /** @param array{count:int, ids:array<string,bool>} $state */
    private static function uniqueId(?string $id, array &$state): string
    {
        if ($id === null || !preg_match('/^[a-z0-9]{4,16}$/', $id) || isset($state['ids'][$id])) {
            do {
                $id = self::newId();
            } while (isset($state['ids'][$id]));
        }
        $state['ids'][$id] = true;
        return $id;
    }

    /**
     * Clean an element's settings, keeping only values that differ from the
     * defaults (block widgets keep their whole content, cleaned by Blocks).
     *
     * @param array<string, mixed> $in
     * @return array<string, mixed>
     */
    public static function settings(string $type, array $in): array
    {
        $out = [];
        $def = Elements::get($type);
        if (isset($def['block'])) {
            $out = Blocks::normalize($def['block'], $in);
            if (isset($in['_design']) && is_array($in['_design'])) {
                $out['_design'] = Design::normalize($in['_design']);
            }
        }
        foreach (Elements::controls($type) as $control) {
            $key = $control['key'];
            if (isset($def['block']) && !str_starts_with($key, '_')) {
                continue; // block content, already cleaned
            }
            foreach (Controls::DEVICES as $suffix) {
                if ($suffix !== '' && empty($control['responsive'])) {
                    continue;
                }
                $k = $key . $suffix;
                if (!array_key_exists($k, $in)) {
                    continue;
                }
                $value = Controls::clean($control, $in[$k]);
                if ($suffix !== '' && $control['type'] === 'select' || $suffix !== '' && $control['type'] === 'choose') {
                    // '' on a smaller screen means "same as the larger one".
                    $raw = is_scalar($in[$k]) ? (string) $in[$k] : '';
                    $value = $raw === '' ? '' : $value;
                }
                if (!self::isDefault($control, $value, $suffix !== '')) {
                    $out[$k] = $value;
                }
            }
        }
        return $out;
    }

    private static function isDefault(array $control, mixed $value, bool $responsiveVariant): bool
    {
        if ($responsiveVariant) {
            return $value === '' || $value === null
                || (is_array($value) && $control['type'] === 'slider' && ($value['size'] ?? '') === '')
                || (is_array($value) && $control['type'] === 'dimensions' && Controls::cssDims($value) === []);
        }
        $default = Controls::defaultOf($control);
        if ($control['type'] === 'dimensions') {
            return Controls::cssDims($value) === [] && Controls::cssDims($default) === [];
        }
        if ($control['type'] === 'slider') {
            return ($value['size'] ?? '') === ($default['size'] ?? '') && (($value['size'] ?? '') === '' || ($value['unit'] ?? '') === ($default['unit'] ?? ''));
        }
        return $value === $default || (is_numeric($value) && is_numeric($default) && $value == $default && !is_bool($value));
    }

    /**
     * Settings with every desktop default filled in, for rendering.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function withDefaults(string $type, array $settings): array
    {
        $out = $settings;
        foreach (Elements::controls($type) as $control) {
            if (!array_key_exists($control['key'], $out)) {
                $out[$control['key']] = Controls::defaultOf($control);
            }
        }
        $def = Elements::get($type);
        if (isset($def['block'])) {
            $out = Blocks::normalize($def['block'], $settings) + $out;
        }
        return $out;
    }

    /** Fresh ids throughout (pasting, duplicating, inserting a template). */
    public static function regenerateIds(array $elements): array
    {
        foreach ($elements as &$el) {
            $el['id'] = self::newId();
            if (isset($el['children']) && is_array($el['children'])) {
                $el['children'] = self::regenerateIds($el['children']);
            }
        }
        return $elements;
    }

    /**
     * The classic editor's sections as a builder layout, so a page can switch
     * editors without losing anything: each section becomes its block widget,
     * keeping its content and design, in a full-width container.
     *
     * @param list<array<string, mixed>> $sections rows from Content::sections()
     */
    public static function fromSections(array $sections): array
    {
        $elements = [];
        foreach ($sections as $section) {
            $type = 'block.' . $section['block_type'];
            if (!Elements::exists($type)) {
                continue;
            }
            $design = Design::normalize($section['settings']);
            $settings = (is_array($section['content']) ? $section['content'] : []) + [
                '_design' => $design,
                '_preset' => $design['preset'],
                '_section_width' => $design['width'],
            ];
            if (!(int) ($section['is_visible'] ?? 1)) {
                $settings += ['_hide_desktop' => true, '_hide_tablet' => true, '_hide_mobile' => true];
            }
            $elements[] = [
                'type' => 'container',
                'settings' => ['content_width' => 'full', '_padding' => Controls::dims('0'), 'gap' => ['size' => 0, 'unit' => 'px']],
                'children' => [['type' => $type, 'settings' => $settings]],
            ];
        }
        return self::normalize(['elements' => $elements]);
    }

    /** Apply $fn to every string in the layout's settings (city placeholders). */
    public static function mapStrings(array $layout, callable $fn): array
    {
        $walk = static function (mixed $v) use (&$walk, $fn): mixed {
            if (is_string($v)) {
                return $fn($v);
            }
            if (is_array($v)) {
                foreach ($v as $k => $x) {
                    $v[$k] = $walk($x);
                }
            }
            return $v;
        };
        $each = static function (array $elements) use (&$each, $walk): array {
            foreach ($elements as &$el) {
                $el['settings'] = $walk($el['settings'] ?? []);
                if (isset($el['children'])) {
                    $el['children'] = $each($el['children']);
                }
            }
            return $elements;
        };
        $layout['elements'] = $each($layout['elements'] ?? []);
        return self::normalize($layout);
    }
}
