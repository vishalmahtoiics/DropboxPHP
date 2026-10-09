<?php

declare(strict_types=1);

namespace App\Builder;

use App\Core\Sanitizer;
use App\Core\ValidationException;

/**
 * Builder layouts as files: export a page, product or template to a .json
 * file, and import one back into any builder screen or as a new template.
 *
 * The file is
 *   {"format":"builder-layout","version":1,"kind":"page","name":"Home",
 *    "exported_at":"2026-10-09T10:00:00+05:30","layout":{"version":1,"elements":[...]}}
 *
 * A bare layout ({"elements":[...]}) or a plain list of elements is accepted
 * too. An imported file is untrusted: it goes through Layout::normalize like
 * anything the editor sends, gets fresh element ids, and custom code is
 * removed for staff without the builder.code permission.
 */
final class Transfer
{
    public const FORMAT = 'builder-layout';
    public const VERSION = 1;
    public const KINDS = ['page', 'section', 'product', 'header', 'footer'];

    /** The export file for a layout. */
    public static function export(array $layout, string $kind, string $name): string
    {
        return (string) json_encode([
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'kind' => in_array($kind, self::KINDS, true) ? $kind : 'page',
            'name' => $name,
            'exported_at' => date('c'),
            'layout' => Layout::normalize($layout),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** A safe download name: "home-page.json". */
    public static function filename(string $name): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        return ($slug !== '' ? substr($slug, 0, 80) : 'layout') . '.json';
    }

    /**
     * Read an import file.
     *
     * @return array{layout: array, kind: ?string, name: string, codeRemoved: bool, elements: int}
     * @throws ValidationException when it is not a builder layout
     */
    public static function parse(string $json, bool $allowCode): array
    {
        if (strlen($json) > Layout::MAX_BYTES) {
            throw ValidationException::with('file', 'The file is too large (at most 1.9 MB).');
        }
        $json = (string) preg_replace('/^\xEF\xBB\xBF/', '', $json); // byte order mark from some editors
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw ValidationException::with('file', 'This is not a builder layout file (it is not valid JSON).');
        }
        $kind = null;
        $name = '';
        if (array_is_list($data)) {
            $doc = ['elements' => $data];
        } elseif (isset($data['layout']) && is_array($data['layout'])) {
            if (isset($data['format']) && $data['format'] !== self::FORMAT) {
                throw ValidationException::with('file', 'This file is not a builder layout.');
            }
            if ((int) ($data['version'] ?? 1) > self::VERSION) {
                throw ValidationException::with('file', 'This file was made by a newer version of the builder. Update the site first.');
            }
            $doc = $data['layout'];
            $kind = in_array($data['kind'] ?? null, self::KINDS, true) ? $data['kind'] : null;
            $name = Sanitizer::line(is_string($data['name'] ?? null) ? $data['name'] : '', 160);
        } elseif (isset($data['elements']) && is_array($data['elements'])) {
            $doc = $data;
        } else {
            throw ValidationException::with('file', 'This file is not a builder layout.');
        }
        if (isset($doc['elements']) && is_array($doc['elements'])) {
            $doc['elements'] = Layout::regenerateIds(array_values(array_filter($doc['elements'], 'is_array')));
        }
        $layout = Layout::normalize($doc);
        $codeRemoved = false;
        if (!$allowCode) {
            $clean = Layout::keepCode($layout, Layout::empty());
            $codeRemoved = Layout::encode($clean) !== Layout::encode($layout);
            $layout = $clean;
        }
        if (self::widgets($layout['elements']) === 0) {
            throw ValidationException::with('file', $codeRemoved
                ? 'The file only contains custom HTML, which your role cannot add.'
                : 'The file has no widgets this builder knows.');
        }
        return ['layout' => $layout, 'kind' => $kind, 'name' => $name, 'codeRemoved' => $codeRemoved, 'elements' => self::count($layout['elements'])];
    }

    private static function widgets(array $elements): int
    {
        $n = 0;
        foreach ($elements as $el) {
            $n += $el['type'] === 'container' ? self::widgets($el['children'] ?? []) : 1;
        }
        return $n;
    }

    private static function count(array $elements): int
    {
        $n = 0;
        foreach ($elements as $el) {
            $n += 1 + self::count($el['children'] ?? []);
        }
        return $n;
    }
}
