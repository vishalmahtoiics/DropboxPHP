<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Finds and rewrites a media file's URL wherever content stores it as text:
 * sections, builder layouts and revisions, templates, products, posts,
 * settings, popups and so on. Used when a file is renamed so nothing breaks.
 *
 * Only these content tables are touched — never logs, sessions or the
 * audit trail — and their text columns are discovered from the schema, so
 * new content columns are covered automatically.
 */
final class MediaReferences
{
    private const TABLES = [
        'blog_categories', 'blog_posts', 'brands', 'builder_revisions', 'builder_templates', 'cities', 'countries',
        'email_templates', 'forms', 'form_fields', 'lead_magnets', 'navigation_items', 'page_categories', 'page_sections',
        'pages', 'popups', 'product_categories', 'product_countries', 'products', 'settings', 'users',
    ];

    /** @return int rows updated */
    public static function replace(string $oldUrl, string $newUrl): int
    {
        if ($oldUrl === '' || $oldUrl === $newUrl) {
            return 0;
        }
        // JSON may store slashes escaped ("\/media\/...").
        $pairs = [[$oldUrl, $newUrl]];
        $escapedOld = str_replace('/', '\/', $oldUrl);
        if ($escapedOld !== $oldUrl) {
            $pairs[] = [$escapedOld, str_replace('/', '\/', $newUrl)];
        }
        $total = 0;
        foreach (self::columns() as [$table, $column]) {
            foreach ($pairs as [$from, $to]) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $from) . '%';
                $total += DB::query(
                    "UPDATE `$table` SET `$column` = REPLACE(`$column`, ?, ?) WHERE `$column` LIKE ?",
                    [$from, $to, $like]
                )->rowCount();
            }
        }
        return $total;
    }

    /** @return list<array{0:string,1:string}> table, column */
    private static function columns(): array
    {
        $in = implode(',', array_fill(0, count(self::TABLES), '?'));
        $rows = DB::all(
            "SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($in)
                AND DATA_TYPE IN ('varchar','char','text','mediumtext','longtext','json')
                AND (DATA_TYPE <> 'varchar' OR CHARACTER_MAXIMUM_LENGTH >= 100)",
            self::TABLES
        );
        $out = [];
        foreach ($rows as $r) {
            if (preg_match('/^[a-z0-9_]+$/', $r['t']) && preg_match('/^[a-z0-9_]+$/', $r['c']) && !in_array($r['c'], ['password_hash', 'two_factor_secret', 'two_factor_pending'], true)) {
                $out[] = [$r['t'], $r['c']];
            }
        }
        return $out;
    }
}
