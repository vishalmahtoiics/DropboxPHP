<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Starts a new market from an existing one: pages (as drafts, with their
 * sections), menus, and product availability with the same prices. Every copy
 * is independent from the moment it exists — nothing syncs afterwards.
 */
final class MarketCopy
{
    public static function copy(int $from, int $to, int $userId): void
    {
        $pageMap = [];
        foreach (DB::all('SELECT * FROM pages WHERE country_id = ? AND deleted_at IS NULL', [$from]) as $p) {
            $old = (int) $p['id'];
            unset($p['id'], $p['created_at'], $p['updated_at']);
            $p['country_id'] = $to;
            $p['status'] = 'DRAFT';
            $p['published_at'] = null;
            $p['created_by_id'] = $userId;
            $p['updated_by_id'] = $userId;
            $new = DB::insert('pages', $p);
            \App\Http\Controllers\Admin\PageController::copySections($old, $new);
            $pageMap[$old] = $new;
        }
        foreach (DB::all('SELECT * FROM navigations WHERE country_id = ?', [$from]) as $nav) {
            $navId = DB::insert('navigations', ['country_id' => $to, 'name' => $nav['name'], 'location' => $nav['location']]);
            $itemMap = [];
            foreach (DB::all('SELECT * FROM navigation_items WHERE navigation_id = ? ORDER BY parent_id IS NOT NULL, sort_order', [$nav['id']]) as $item) {
                $old = (int) $item['id'];
                unset($item['id'], $item['created_at'], $item['updated_at']);
                $item['navigation_id'] = $navId;
                $item['parent_id'] = $item['parent_id'] ? ($itemMap[(int) $item['parent_id']] ?? null) : null;
                $item['page_id'] = $item['page_id'] ? ($pageMap[(int) $item['page_id']] ?? null) : null;
                $item['blog_post_id'] = null;
                $itemMap[$old] = DB::insert('navigation_items', $item);
            }
        }
        $country = DB::first('SELECT currency FROM countries WHERE id = ?', [$to]);
        foreach (DB::all('SELECT * FROM product_countries WHERE country_id = ?', [$from]) as $pc) {
            unset($pc['id'], $pc['created_at'], $pc['updated_at']);
            $pc['country_id'] = $to;
            $pc['status'] = 'DRAFT';
            $pc['currency'] = $country['currency'] ?? $pc['currency'];
            DB::query('INSERT IGNORE INTO product_countries (' . implode(',', array_map(static fn ($k) => "`$k`", array_keys($pc))) . ') VALUES (' . implode(',', array_fill(0, count($pc), '?')) . ')', array_values($pc));
        }
    }
}
