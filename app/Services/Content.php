<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Read models for the public site. Everything public goes through the
 * "published" conditions here, so a draft, a scheduled item whose time has not
 * come, or a soft-deleted row can never be rendered by accident.
 */
final class Content
{
    /** @var array<string, mixed> */
    private static array $memo = [];

    public static function flush(): void
    {
        self::$memo = [];
    }

    public static function publishedSql(string $alias = ''): string
    {
        $a = $alias === '' ? '' : $alias . '.';
        return "{$a}deleted_at IS NULL AND ({$a}status = 'PUBLISHED' OR ({$a}status = 'SCHEDULED' AND {$a}published_at <= NOW()))"
            . " AND ({$a}published_at IS NULL OR {$a}published_at <= NOW())";
    }

    // --- pages -------------------------------------------------------------

    public static function page(int $countryId, string $slug): ?array
    {
        return DB::first('SELECT * FROM pages WHERE country_id = ? AND slug = ? AND ' . self::publishedSql(), [$countryId, $slug]);
    }

    /** @return list<array<string,mixed>> */
    public static function sections(int $pageId, bool $includeHidden = false): array
    {
        $rows = DB::all(
            'SELECT * FROM page_sections WHERE page_id = ?' . ($includeHidden ? '' : ' AND is_visible = 1') . ' ORDER BY sort_order, id',
            [$pageId]
        );
        foreach ($rows as &$row) {
            $row['content'] = json_decode((string) $row['content'], true) ?: [];
            $row['settings'] = json_decode((string) $row['settings'], true) ?: [];
        }
        return $rows;
    }

    public static function pagePath(array $page): string
    {
        $country = Countries::find((int) $page['country_id']);
        return Countries::href($country, '/' . $page['slug']);
    }

    // --- products ------------------------------------------------------------

    private const PRODUCT_SELECT = 'SELECT p.*, pc.currency AS m_currency, pc.monthly_price AS m_monthly, pc.annual_price AS m_annual,
            pc.compare_at_price AS m_compare, pc.discount_percent AS m_discount, pc.price_suffix AS m_suffix, pc.price_note AS m_note,
            pc.short_description AS m_short, pc.cta_label AS m_cta_label, pc.cta_url AS m_cta_url, pc.cta_form_id AS m_cta_form,
            pc.is_featured AS m_featured, pc.sort_order AS m_sort, pc.featured_order AS m_forder, pc.seo_title AS m_seo_title,
            pc.seo_description AS m_seo_desc, pc.no_index AS m_noindex,
            c.name AS category_name, c.slug AS category_slug, b.name AS brand_name, b.slug AS brand_slug,
            m.url AS image_url, m.alt_text AS image_alt
       FROM products p
       JOIN product_countries pc ON pc.product_id = p.id AND pc.country_id = :cid
  LEFT JOIN product_categories c ON c.id = p.category_id
  LEFT JOIN brands b ON b.id = p.brand_id AND b.deleted_at IS NULL
  LEFT JOIN media m ON m.id = p.image_id';

    private static function productVisible(): string
    {
        return ' WHERE ' . self::publishedSql('p') . " AND (pc.status = 'PUBLISHED' OR (pc.status = 'SCHEDULED' AND pc.published_at <= NOW()))";
    }

    /** A product as sold in one market: market price, currency and copy win over the global row. */
    public static function hydrateProduct(array $p, array $country): array
    {
        $p['features'] = json_decode((string) ($p['features'] ?? '[]'), true) ?: [];
        $p['benefits'] = json_decode((string) ($p['benefits'] ?? '[]'), true) ?: [];
        $p['specs'] = json_decode((string) ($p['specs'] ?? '[]'), true) ?: [];
        $p['gallery_ids'] = json_decode((string) ($p['gallery_ids'] ?? '[]'), true) ?: [];
        if (array_key_exists('m_currency', $p)) {
            $p['currency'] = $p['m_currency'] ?: $p['currency'];
            $p['monthly_price'] = $p['m_monthly'] ?? $p['monthly_price'];
            $p['annual_price'] = $p['m_annual'] ?? $p['annual_price'];
            $p['compare_at_price'] = $p['m_compare'] ?? $p['compare_at_price'];
            $p['discount_percent'] = $p['m_discount'] ?? $p['discount_percent'];
            $p['price_suffix'] = $p['m_suffix'] ?: $p['price_suffix'];
            $p['price_note'] = $p['m_note'] ?: $p['price_note'];
            $p['short_description'] = $p['m_short'] ?: $p['short_description'];
            $p['cta_label'] = $p['m_cta_label'] ?: $p['cta_label'];
            $p['cta_url'] = $p['m_cta_url'] ?: $p['cta_url'];
            $p['cta_form_id'] = $p['m_cta_form'] ?: $p['cta_form_id'];
            $p['is_featured'] = $p['m_featured'];
            $p['seo_title'] = $p['m_seo_title'] ?: $p['seo_title'];
            $p['seo_description'] = $p['m_seo_desc'] ?: $p['seo_description'];
            $p['no_index'] = (int) $p['no_index'] || (int) $p['m_noindex'];
        }
        $symbol = $p['currency'] === ($country['currency'] ?? '') ? (string) $country['currency_symbol'] : $p['currency'] . ' ';
        $p['symbol'] = $symbol;
        $p['url'] = Countries::href($country, '/' . Settings::get('product', 'base_path', 'products') . '/' . $p['slug']);
        return $p;
    }

    public static function product(array $country, string $slug): ?array
    {
        $row = DB::first(self::PRODUCT_SELECT . self::productVisible() . ' AND p.slug = :slug', ['cid' => (int) $country['id'], 'slug' => $slug]);
        return $row ? self::hydrateProduct($row, $country) : null;
    }

    /** @return list<array<string,mixed>> */
    public static function productsById(array $country, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $placeholders = [];
        $params = ['cid' => (int) $country['id']];
        foreach ($ids as $i => $id) {
            $placeholders[] = ":id$i";
            $params["id$i"] = $id;
        }
        $rows = DB::all(self::PRODUCT_SELECT . self::productVisible() . ' AND p.id IN (' . implode(',', $placeholders) . ')', $params);
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = self::hydrateProduct($row, $country);
        }
        return array_values(array_filter(array_map(static fn ($id) => $byId[$id] ?? null, $ids)));
    }

    /**
     * Products for a block's source setting.
     * @param array<string,mixed> $c block content
     * @return list<array<string,mixed>>
     */
    public static function productsForBlock(array $country, array $c): array
    {
        $limit = max(1, min(24, (int) ($c['limit'] ?? 6)));
        $source = $c['source'] ?? 'featured';
        if ($source === 'manual') {
            return array_slice(self::productsById($country, (array) ($c['products'] ?? [])), 0, $limit);
        }
        $where = '';
        $params = ['cid' => (int) $country['id']];
        $order = ' ORDER BY pc.sort_order, p.sort_order, p.name';
        if ($source === 'featured') {
            $where = ' AND pc.is_featured = 1';
            $order = ' ORDER BY pc.featured_order, pc.sort_order';
        } elseif ($source === 'category' && ($c['category'] ?? '') !== '') {
            $where = ' AND c.slug = :cat';
            $params['cat'] = $c['category'];
        } elseif ($source === 'brand' && ($c['brand'] ?? '') !== '') {
            $where = ' AND b.slug = :brand';
            $params['brand'] = $c['brand'];
        }
        $key = 'pb:' . md5(serialize([$params, $where, $limit]));
        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }
        $rows = DB::all(self::PRODUCT_SELECT . self::productVisible() . $where . $order . ' LIMIT ' . $limit, $params);
        return self::$memo[$key] = array_map(static fn ($r) => self::hydrateProduct($r, $country), $rows);
    }

    // --- blog ------------------------------------------------------------------

    public static function blogBase(?array $country = null): string
    {
        return Countries::href($country, '/' . trim((string) Settings::get('blog', 'base_path', 'blog'), '/'));
    }

    public static function postUrl(array $post, ?array $country = null): string
    {
        $country ??= Countries::find((int) $post['country_id']);
        return Countries::href($country, '/' . trim((string) Settings::get('blog', 'base_path', 'blog'), '/') . '/' . $post['slug']);
    }

    private const POST_SELECT = 'SELECT bp.*, bc.name AS category_name, bc.slug AS category_slug, u.name AS author_name,
            u.job_title AS author_title, u.bio AS author_bio, u.avatar_url AS author_avatar, m.url AS image_url, m.alt_text AS image_alt
       FROM blog_posts bp
  LEFT JOIN blog_categories bc ON bc.id = bp.category_id
  LEFT JOIN users u ON u.id = bp.author_id
  LEFT JOIN media m ON m.id = bp.featured_image_id';

    public static function post(int $countryId, string $slug): ?array
    {
        return DB::first(self::POST_SELECT . ' WHERE bp.country_id = ? AND bp.slug = ? AND ' . self::publishedSql('bp'), [$countryId, $slug]);
    }

    /**
     * @param array{category?:int, tag?:int, q?:string, exclude?:int} $filter
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public static function posts(int $countryId, array $filter = [], int $page = 1, int $perPage = 9): array
    {
        $where = ' WHERE bp.country_id = :cid AND ' . self::publishedSql('bp');
        $params = ['cid' => $countryId];
        if (!empty($filter['category'])) {
            $where .= ' AND bp.category_id = :cat';
            $params['cat'] = (int) $filter['category'];
        }
        if (!empty($filter['tag'])) {
            $where .= ' AND bp.id IN (SELECT post_id FROM blog_post_tags WHERE tag_id = :tag)';
            $params['tag'] = (int) $filter['tag'];
        }
        if (!empty($filter['exclude'])) {
            $where .= ' AND bp.id <> :ex';
            $params['ex'] = (int) $filter['exclude'];
        }
        if (!empty($filter['q'])) {
            $where .= ' AND (bp.title LIKE :q1 OR bp.excerpt LIKE :q2 OR bp.content LIKE :q3)';
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], (string) $filter['q']) . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        $total = (int) DB::value('SELECT COUNT(*) FROM blog_posts bp' . $where, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $items = DB::all(self::POST_SELECT . $where . ' ORDER BY bp.is_featured DESC, bp.published_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset, $params);
        return ['items' => $items, 'total' => $total];
    }

    /** @return list<array<string,mixed>> */
    public static function tagsForPost(int $postId): array
    {
        return DB::all('SELECT t.* FROM blog_tags t JOIN blog_post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? AND t.is_active = 1 ORDER BY t.name', [$postId]);
    }

    /** @return list<array<string,mixed>> */
    public static function blogCategories(int $countryId): array
    {
        return DB::all(
            'SELECT bc.*, (SELECT COUNT(*) FROM blog_posts bp WHERE bp.category_id = bc.id AND bp.country_id = ? AND ' . self::publishedSql('bp') . ') AS post_count
               FROM blog_categories bc WHERE bc.is_active = 1 ORDER BY bc.sort_order, bc.name',
            [$countryId]
        );
    }

    // --- media -----------------------------------------------------------------

    public static function mediaUrl(?int $id): ?string
    {
        if (!$id) {
            return null;
        }
        return DB::value('SELECT url FROM media WHERE id = ? AND deleted_at IS NULL', [$id]);
    }
}
