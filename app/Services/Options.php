<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Option lists for admin selects (forms, categories, products …). */
final class Options
{
    /** @return array<string,string> */
    public static function forms(): array
    {
        return array_column(DB::all('SELECT slug, name FROM forms WHERE deleted_at IS NULL ORDER BY name'), 'name', 'slug');
    }

    /** @return array<int,string> */
    public static function formsById(): array
    {
        return array_column(DB::all('SELECT id, name FROM forms WHERE deleted_at IS NULL ORDER BY name'), 'name', 'id');
    }

    /** @return array<string,string> */
    public static function productCategories(): array
    {
        return array_column(DB::all('SELECT slug, name FROM product_categories WHERE deleted_at IS NULL ORDER BY sort_order, name'), 'name', 'slug');
    }

    /** @return array<string,string> */
    public static function brands(): array
    {
        return array_column(DB::all('SELECT slug, name FROM brands WHERE deleted_at IS NULL ORDER BY sort_order, name'), 'name', 'slug');
    }

    /** @return array<int,string> */
    public static function products(): array
    {
        return array_column(DB::all('SELECT id, name FROM products WHERE deleted_at IS NULL ORDER BY sort_order, name'), 'name', 'id');
    }

    /** @return array<string,string> */
    public static function magnets(): array
    {
        return array_column(DB::all('SELECT slug, title FROM lead_magnets WHERE deleted_at IS NULL ORDER BY title'), 'title', 'slug');
    }

    /** @return array<string,string> */
    public static function blogCategories(): array
    {
        return array_column(DB::all('SELECT slug, name FROM blog_categories ORDER BY sort_order, name'), 'name', 'slug');
    }

    /** @return array<int,string> */
    public static function blogCategoriesById(): array
    {
        return array_column(DB::all('SELECT id, name FROM blog_categories ORDER BY sort_order, name'), 'name', 'id');
    }

    /** @return array<int,string> */
    public static function pageCategories(): array
    {
        return array_column(DB::all('SELECT id, name FROM page_categories ORDER BY sort_order, name'), 'name', 'id');
    }

    /** @return array<int,string> */
    public static function staff(): array
    {
        return array_column(DB::all("SELECT id, name FROM users WHERE deleted_at IS NULL AND status = 'ACTIVE' ORDER BY name"), 'name', 'id');
    }

    /** @return array<int,string> */
    public static function pages(int $countryId): array
    {
        $out = [];
        foreach (DB::all('SELECT id, title, slug FROM pages WHERE country_id = ? AND deleted_at IS NULL ORDER BY slug = \'\' DESC, title', [$countryId]) as $p) {
            $out[(int) $p['id']] = $p['title'] . ' (/' . $p['slug'] . ')';
        }
        return $out;
    }
}
