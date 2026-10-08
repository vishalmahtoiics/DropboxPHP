<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Sanitizer;

/**
 * Menus per market and location. Links to pages, products, posts and blog
 * categories are stored by id and resolved to their current address at
 * render time, so renaming a slug never breaks a menu.
 */
final class Navigation
{
    public const LOCATIONS = [
        'HEADER' => 'Header menu',
        'FOOTER' => 'Footer columns',
        'FOOTER_SECONDARY' => 'Footer secondary links',
        'LEGAL' => 'Legal links (bottom bar)',
        'MOBILE' => 'Mobile menu (blank = header menu)',
    ];

    /** @return list<array<string,mixed>> nested items with 'href' and 'children' */
    public static function tree(int $countryId, string $location, bool $visibleOnly = true): array
    {
        $nav = DB::first('SELECT id FROM navigations WHERE country_id = ? AND location = ?', [$countryId, $location]);
        if (!$nav) {
            return [];
        }
        $rows = DB::all(
            'SELECT ni.*, p.slug AS page_slug, p.country_id AS page_country, p.deleted_at AS page_deleted,
                    pr.slug AS product_slug, pr.deleted_at AS product_deleted,
                    bp.slug AS post_slug, bp.country_id AS post_country, bc.slug AS bcat_slug
               FROM navigation_items ni
          LEFT JOIN pages p ON p.id = ni.page_id
          LEFT JOIN products pr ON pr.id = ni.product_id
          LEFT JOIN blog_posts bp ON bp.id = ni.blog_post_id
          LEFT JOIN blog_categories bc ON bc.id = ni.blog_category_id
              WHERE ni.navigation_id = ?' . ($visibleOnly ? ' AND ni.is_visible = 1' : '') . '
           ORDER BY ni.sort_order, ni.id',
            [$nav['id']]
        );
        $country = Countries::find($countryId);
        $byParent = [];
        foreach ($rows as $row) {
            $row['href'] = self::href($row, $country);
            if ($visibleOnly && $row['href'] === null) {
                continue; // The page or product it pointed at is gone.
            }
            $byParent[(int) ($row['parent_id'] ?? 0)][] = $row;
        }
        $build = static function (int $parent) use (&$build, $byParent): array {
            $out = [];
            foreach ($byParent[$parent] ?? [] as $item) {
                $item['children'] = $build((int) $item['id']);
                $out[] = $item;
            }
            return $out;
        };
        return $build(0);
    }

    private static function href(array $item, ?array $country): ?string
    {
        switch ($item['link_type']) {
            case 'PAGE':
                if (!$item['page_slug'] && $item['page_slug'] !== '' || $item['page_deleted']) {
                    return null;
                }
                return Countries::href(Countries::find((int) $item['page_country']) ?? $country, '/' . $item['page_slug']);
            case 'PRODUCT':
                if (!$item['product_slug'] || $item['product_deleted']) {
                    return null;
                }
                return Countries::href($country, '/' . Settings::get('product', 'base_path', 'products') . '/' . $item['product_slug']);
            case 'BLOG_POST':
                return $item['post_slug'] ? Countries::href(Countries::find((int) $item['post_country']) ?? $country, '/' . Settings::get('blog', 'base_path', 'blog') . '/' . $item['post_slug']) : null;
            case 'BLOG_CATEGORY':
                return $item['bcat_slug'] ? Countries::href($country, '/' . Settings::get('blog', 'base_path', 'blog') . '/category/' . $item['bcat_slug']) : null;
            case 'EXTERNAL':
                return Sanitizer::url((string) $item['url']) ?: '#';
            default:
                $url = Sanitizer::url((string) $item['url']);
                if ($url === '') {
                    return '#';
                }
                return str_starts_with($url, '/') ? Countries::href($country, $url) : $url;
        }
    }
}
