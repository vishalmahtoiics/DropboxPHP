<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\ValidationException;

final class Slugs
{
    public static function make(string $text, bool $allowSlash = false): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['&' => ' and ', '@' => ' at ']);
        if (function_exists('transliterator_transliterate')) {
            $text = (string) transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        }
        $pattern = $allowSlash ? '#[^a-z0-9/]+#' : '#[^a-z0-9]+#';
        $text = preg_replace($pattern, '-', $text) ?? '';
        if ($allowSlash) {
            $text = preg_replace('#-*/+-*#', '/', $text) ?? '';
            $text = trim($text, '/');
        }
        return trim(mb_substr(trim($text, '-'), 0, 180), '-/');
    }

    /** Reserved first segments a page slug may never use. */
    public static function assertPageSlug(string $slug, int $countryId, ?int $ignoreId = null): void
    {
        if ($slug === '') {
            return;
        }
        if (!preg_match('#^[a-z0-9]+(?:[-/][a-z0-9]+)*$#', $slug)) {
            throw ValidationException::with('slug', 'Use lowercase letters, numbers, hyphens and slashes only.');
        }
        $first = explode('/', $slug)[0];
        $reserved = array_merge(Countries::RESERVED, [
            trim((string) Settings::get('blog', 'base_path', 'blog'), '/'),
            trim((string) Settings::get('product', 'base_path', 'products'), '/'),
        ]);
        foreach (Countries::active() as $c) {
            if ($c['slug'] !== '') {
                $reserved[] = $c['slug'];
            }
        }
        if (in_array($first, $reserved, true)) {
            throw ValidationException::with('slug', "\"$first\" is reserved by the system. Choose another address.");
        }
        $exists = DB::value('SELECT id FROM pages WHERE country_id = ? AND slug = ? AND id <> ?', [$countryId, $slug, $ignoreId ?? 0]);
        if ($exists) {
            throw ValidationException::with('slug', 'Another page in this market already uses this address.');
        }
    }

    /** First free "slug", "slug-2", "slug-3"… in a table column. */
    public static function unique(string $table, string $slug, ?int $ignoreId = null, array $scope = []): string
    {
        DB::assertIdentifier($table);
        $base = $slug !== '' ? $slug : 'item';
        $candidate = $base;
        for ($i = 2; $i < 500; $i++) {
            $sql = "SELECT id FROM `$table` WHERE slug = ? AND id <> ?";
            $params = [$candidate, $ignoreId ?? 0];
            foreach ($scope as $col => $val) {
                DB::assertIdentifier($col);
                $sql .= " AND `$col` = ?";
                $params[] = $val;
            }
            if (!DB::value($sql, $params)) {
                return $candidate;
            }
            $candidate = $base . '-' . $i;
        }
        return $base . '-' . bin2hex(random_bytes(3));
    }

    /** The value a soft-deleted row's slug becomes, freeing the unique index. */
    public static function trashed(string $slug, int $id): string
    {
        return mb_substr($slug, 0, 150) . '--deleted-' . $id;
    }

    public static function untrashed(string $slug): string
    {
        return (string) preg_replace('/--deleted-\d+$/', '', $slug);
    }
}
