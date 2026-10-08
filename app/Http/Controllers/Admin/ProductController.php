<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\Countries;
use App\Services\Money;
use App\Services\Options;
use App\Services\Redirects;
use App\Services\Settings;
use App\Services\Slugs;

final class ProductController extends AdminController
{
    private const STATUSES = ['DRAFT' => 'Draft', 'PUBLISHED' => 'Published', 'ARCHIVED' => 'Archived'];

    public function index(): Response
    {
        $this->authorize('products.view');
        $country = Countries::adminCurrent();
        $q = $this->request->queryStr('q');
        $where = 'p.deleted_at IS NULL';
        $params = [(int) $country['id']];
        if ($q !== '') {
            $where .= ' AND (p.name LIKE ? OR p.slug LIKE ? OR p.sku LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        foreach (['category' => 'p.category_id', 'brand' => 'p.brand_id'] as $key => $col) {
            if ($this->request->int($key)) {
                $where .= " AND $col = ?";
                $params[] = $this->request->int($key);
            }
        }
        $status = $this->request->queryStr('status');
        if (isset(self::STATUSES[$status])) {
            $where .= ' AND p.status = ?';
            $params[] = $status;
        }
        $rows = DB::all(
            "SELECT p.*, c.name AS category_name, b.name AS brand_name, m.url AS image_url,
                    pc.id AS pc_id, pc.status AS pc_status, pc.currency AS pc_currency, pc.monthly_price AS pc_monthly, pc.annual_price AS pc_annual, pc.is_featured AS pc_featured
               FROM products p LEFT JOIN product_categories c ON c.id = p.category_id LEFT JOIN brands b ON b.id = p.brand_id
          LEFT JOIN media m ON m.id = p.image_id LEFT JOIN product_countries pc ON pc.product_id = p.id AND pc.country_id = ?
              WHERE $where ORDER BY p.sort_order, p.name",
            $params
        );
        return $this->screen('products/index', [
            'title' => 'Products', 'rows' => $rows, 'q' => $q, 'status' => $status,
            'categories' => array_column(DB::all('SELECT id, name FROM product_categories WHERE deleted_at IS NULL ORDER BY name'), 'name', 'id'),
            'brands' => array_column(DB::all('SELECT id, name FROM brands WHERE deleted_at IS NULL ORDER BY name'), 'name', 'id'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('products.create');
        return $this->editor(null);
    }

    public function edit(string $id): Response
    {
        $this->authorize('products.view');
        return $this->editor($this->find($this->id($id)));
    }

    private function editor(?array $p): Response
    {
        $markets = [];
        foreach (Countries::accessible() as $c) {
            $row = $p ? DB::first('SELECT * FROM product_countries WHERE product_id = ? AND country_id = ?', [$p['id'], $c['id']]) : null;
            $markets[] = ['country' => $c, 'row' => $row];
        }
        $media = static fn (?int $id) => $id ? (string) DB::value('SELECT url FROM media WHERE id = ?', [$id]) : '';
        $gallery = [];
        if ($p) {
            foreach (json_decode((string) ($p['gallery_ids'] ?? '[]'), true) ?: [] as $gid) {
                if ($u = $media((int) $gid)) {
                    $gallery[] = $u;
                }
            }
        }
        return $this->screen('products/edit', [
            'title' => $p ? $p['name'] : 'New product',
            'p' => $p,
            'markets' => $markets,
            'imageUrl' => $p ? $media($p['image_id'] ? (int) $p['image_id'] : null) : '',
            'ogUrl' => $p ? $media($p['og_image_id'] ? (int) $p['og_image_id'] : null) : '',
            'gallery' => $gallery,
            'variants' => $p ? DB::all('SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort_order', [$p['id']]) : [],
            'categories' => array_column(DB::all('SELECT id, name FROM product_categories WHERE deleted_at IS NULL ORDER BY name'), 'name', 'id'),
            'brands' => array_column(DB::all('SELECT id, name FROM brands WHERE deleted_at IS NULL ORDER BY name'), 'name', 'id'),
            'forms' => Options::formsById(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(): Response
    {
        $this->authorize('products.create');
        $id = $this->save(null);
        $this->flash('Product created.');
        return $this->redirect('/admin/products/' . $id);
    }

    public function update(string $id): Response
    {
        $this->authorize('products.edit');
        $p = $this->find($this->id($id));
        $this->save($p);
        $this->flash('Product saved.');
        return $this->redirect('/admin/products/' . $p['id']);
    }

    private function save(?array $existing): int
    {
        $d = $this->validate([
            'name' => 'required|max:200',
            'slug' => 'nullable|max:160',
            'sku' => 'nullable|max:80',
            'status' => 'required|in:DRAFT,PUBLISHED,ARCHIVED',
            'short_description' => 'nullable|max:1000',
            'description' => 'nullable|max:100000',
            'storage' => 'nullable|max:80',
            'storage_label' => 'nullable|max:80',
            'min_users' => 'nullable|int',
            'max_users' => 'nullable|int',
            'users_label' => 'nullable|max:80',
            'billing_period' => 'required|in:MONTHLY,ANNUAL,BOTH,ONE_TIME',
            'currency' => 'required|max:3',
            'monthly_price' => 'nullable|max:20',
            'annual_price' => 'nullable|max:20',
            'compare_at_price' => 'nullable|max:20',
            'discount_percent' => 'nullable|int|between:0,100',
            'price_suffix' => 'nullable|max:80',
            'price_note' => 'nullable|max:255',
            'features' => 'nullable|max:10000',
            'benefits' => 'nullable|max:10000',
            'cta_label' => 'nullable|max:80',
            'cta_url' => 'nullable|url|max:500',
            'cta_form_id' => 'nullable|int',
            'category_id' => 'nullable|int',
            'brand_id' => 'nullable|int',
            'image' => 'nullable|max:1000',
            'og_image' => 'nullable|max:1000',
            'gallery' => 'nullable|max:10000',
            'is_featured' => 'bool',
            'seo_title' => 'nullable|max:255',
            'seo_description' => 'nullable|max:500',
            'canonical_url' => 'nullable|url|max:500',
            'no_index' => 'bool',
            'primary_keywords' => 'nullable|max:500',
        ]);
        $slug = Slugs::make((string) ($d['slug'] ?: $d['name']));
        if ($slug === '') {
            throw ValidationException::with('slug', 'Enter an address.');
        }
        if (DB::value('SELECT id FROM products WHERE slug = ? AND id <> ?', [$slug, $existing['id'] ?? 0])) {
            throw ValidationException::with('slug', 'Another product uses this address.');
        }
        if ($d['sku'] && DB::value('SELECT id FROM products WHERE sku = ? AND id <> ?', [$d['sku'], $existing['id'] ?? 0])) {
            throw ValidationException::with('sku', 'Another product uses this SKU.');
        }
        $prices = [];
        foreach (['monthly_price', 'annual_price', 'compare_at_price'] as $k) {
            $prices[$k] = Money::parse($d[$k]);
            if ($d[$k] !== null && $d[$k] !== '' && $prices[$k] === null) {
                throw ValidationException::with($k, 'Enter an amount like 1250 or 1250.50.');
            }
        }
        $lines = static fn (?string $v) => array_values(array_filter(array_map(static fn ($l) => Sanitizer::line($l, 300), preg_split('/\R/', (string) $v) ?: [])));
        $specs = [];
        foreach ($this->request->arr('specs') as $s) {
            $label = Sanitizer::line((string) ($s['label'] ?? ''), 120);
            $value = Sanitizer::line((string) ($s['value'] ?? ''), 255);
            if ($label !== '' || $value !== '') {
                $specs[] = ['label' => $label, 'value' => $value];
            }
        }
        $mediaId = static fn (?string $url) => $url ? (DB::value('SELECT id FROM media WHERE url = ? AND deleted_at IS NULL', [$url]) ?: null) : null;
        $galleryIds = array_values(array_filter(array_map(static fn ($u) => $mediaId(trim($u)), preg_split('/\R/', (string) $d['gallery']) ?: [])));
        $row = [
            'name' => Sanitizer::line((string) $d['name'], 200),
            'slug' => $slug,
            'sku' => $d['sku'] ?: null,
            'status' => $d['status'],
            'published_at' => $d['status'] === 'PUBLISHED' ? ($existing['published_at'] ?? now()) : ($existing['published_at'] ?? null),
            'short_description' => Sanitizer::text((string) $d['short_description'], 1000) ?: null,
            'description' => Sanitizer::html((string) $d['description']) ?: null,
            'storage' => $d['storage'] ?: null,
            'storage_label' => $d['storage_label'] ?: null,
            'min_users' => $d['min_users'],
            'max_users' => $d['max_users'],
            'users_label' => $d['users_label'] ?: null,
            'billing_period' => $d['billing_period'],
            'currency' => strtoupper(substr((string) $d['currency'], 0, 3)),
            'discount_percent' => $d['discount_percent'],
            'price_suffix' => $d['price_suffix'] ?: null,
            'price_note' => $d['price_note'] ?: null,
            'features' => $lines($d['features']),
            'benefits' => $lines($d['benefits']),
            'specs' => $specs,
            'cta_label' => $d['cta_label'] ?: null,
            'cta_url' => $d['cta_url'] ?: null,
            'cta_form_id' => $d['cta_form_id'] && DB::value('SELECT id FROM forms WHERE id = ?', [$d['cta_form_id']]) ? $d['cta_form_id'] : null,
            'category_id' => $d['category_id'] ?: null,
            'brand_id' => $d['brand_id'] ?: null,
            'image_id' => $mediaId($d['image']),
            'og_image_id' => $mediaId($d['og_image']),
            'gallery_ids' => $galleryIds,
            'is_featured' => $d['is_featured'] ? 1 : 0,
            'seo_title' => $d['seo_title'] ?: null,
            'seo_description' => $d['seo_description'] ?: null,
            'canonical_url' => $d['canonical_url'] ?: null,
            'no_index' => $d['no_index'] ? 1 : 0,
            'primary_keywords' => $d['primary_keywords'] ?: null,
            'updated_by_id' => $this->me['id'],
        ] + $prices;

        return DB::transaction(function () use ($row, $existing, $slug): int {
            if ($existing) {
                DB::update('products', $row, ['id' => $existing['id']]);
                $id = (int) $existing['id'];
                Audit::record('updated', 'Product', $id, $row['name'], array_intersect_key($existing, $row), $row);
                if ($slug !== $existing['slug'] && $existing['status'] === 'PUBLISHED') {
                    $base = '/' . Settings::get('product', 'base_path', 'products') . '/';
                    foreach (Countries::active() as $c) {
                        Redirects::onSlugChange(Countries::href($c, $base . $existing['slug']), Countries::href($c, $base . $slug));
                    }
                }
            } else {
                $row['created_by_id'] = $this->me['id'];
                $row['sort_order'] = (int) DB::value('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM products');
                $id = DB::insert('products', $row);
                Audit::record('created', 'Product', $id, $row['name']);
            }
            $this->saveMarkets($id, $row);
            $this->saveVariants($id);
            return $id;
        });
    }

    /** Per-market availability and pricing. A market left unticked does not sell the product. */
    private function saveMarkets(int $productId, array $base): void
    {
        $input = $this->request->arr('markets');
        foreach (Countries::accessible() as $c) {
            $cid = (int) $c['id'];
            $m = is_array($input[$cid] ?? null) ? $input[$cid] : [];
            if (empty($m['enabled'])) {
                DB::delete('product_countries', ['product_id' => $productId, 'country_id' => $cid]);
                continue;
            }
            $price = static function (string $k) use ($m, $c): ?string {
                $v = Money::parse($m[$k] ?? '');
                if (($m[$k] ?? '') !== '' && $v === null) {
                    throw ValidationException::with("markets.{$c['id']}.$k", 'Enter a valid amount for ' . $c['name'] . '.');
                }
                return $v;
            };
            $status = in_array($m['status'] ?? '', ['DRAFT', 'PUBLISHED', 'ARCHIVED'], true) ? $m['status'] : 'PUBLISHED';
            $row = [
                'product_id' => $productId,
                'country_id' => $cid,
                'status' => $status,
                'published_at' => $status === 'PUBLISHED' ? now() : null,
                'is_featured' => !empty($m['is_featured']) ? 1 : 0,
                'currency' => preg_match('/^[A-Z]{3}$/', strtoupper((string) ($m['currency'] ?? ''))) ? strtoupper((string) $m['currency']) : $c['currency'],
                'monthly_price' => $price('monthly_price'),
                'annual_price' => $price('annual_price'),
                'compare_at_price' => $price('compare_at_price'),
                'price_suffix' => Sanitizer::line((string) ($m['price_suffix'] ?? ''), 80) ?: null,
                'price_note' => Sanitizer::line((string) ($m['price_note'] ?? ''), 255) ?: null,
                'cta_label' => Sanitizer::line((string) ($m['cta_label'] ?? ''), 80) ?: null,
                'seo_title' => Sanitizer::line((string) ($m['seo_title'] ?? ''), 255) ?: null,
                'seo_description' => Sanitizer::text((string) ($m['seo_description'] ?? ''), 500) ?: null,
            ];
            $existing = DB::first('SELECT id, published_at, sort_order FROM product_countries WHERE product_id = ? AND country_id = ?', [$productId, $cid]);
            if ($existing) {
                if ($status === 'PUBLISHED' && $existing['published_at']) {
                    $row['published_at'] = $existing['published_at'];
                }
                DB::update('product_countries', $row, ['id' => $existing['id']]);
            } else {
                $row['sort_order'] = (int) DB::value('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM product_countries WHERE country_id = ?', [$cid]);
                DB::insert('product_countries', $row);
            }
        }
    }

    private function saveVariants(int $productId): void
    {
        DB::delete('product_variants', ['product_id' => $productId]);
        foreach (array_values($this->request->arr('variants')) as $i => $v) {
            $name = Sanitizer::line((string) ($v['name'] ?? ''), 160);
            if ($name === '') {
                continue;
            }
            DB::insert('product_variants', [
                'product_id' => $productId, 'name' => $name, 'sku' => Sanitizer::line((string) ($v['sku'] ?? ''), 80) ?: null,
                'storage' => Sanitizer::line((string) ($v['storage'] ?? ''), 80) ?: null,
                'users' => is_numeric($v['users'] ?? null) ? (int) $v['users'] : null,
                'monthly_price' => Money::parse($v['monthly_price'] ?? ''), 'annual_price' => Money::parse($v['annual_price'] ?? ''),
                'sort_order' => $i, 'is_default' => !empty($v['is_default']) ? 1 : 0,
            ]);
        }
    }

    public function duplicate(string $id): Response
    {
        $this->authorize('products.create');
        $p = $this->find($this->id($id));
        $newId = DB::transaction(function () use ($p): int {
            $copy = $p;
            unset($copy['id'], $copy['created_at'], $copy['updated_at']);
            $copy['name'] .= ' (copy)';
            $copy['slug'] = Slugs::unique('products', $p['slug'] . '-copy');
            $copy['sku'] = null;
            $copy['status'] = 'DRAFT';
            $copy['published_at'] = null;
            $copy['created_by_id'] = $this->me['id'];
            $newId = DB::insert('products', $copy);
            foreach (DB::all('SELECT * FROM product_countries WHERE product_id = ?', [$p['id']]) as $pc) {
                unset($pc['id'], $pc['created_at'], $pc['updated_at']);
                $pc['product_id'] = $newId;
                $pc['status'] = 'DRAFT';
                DB::insert('product_countries', $pc);
            }
            return $newId;
        });
        Audit::record('duplicated', 'Product', $newId, 'Copied from #' . $p['id']);
        $this->flash('Product duplicated as a draft.');
        return $this->redirect('/admin/products/' . $newId);
    }

    public function destroy(string $id): Response
    {
        $this->authorize('products.delete');
        $p = $this->find($this->id($id));
        DB::update('products', ['deleted_at' => now(), 'slug' => Slugs::trashed($p['slug'], (int) $p['id']), 'sku' => null, 'status' => 'ARCHIVED'], ['id' => $p['id']]);
        Audit::record('deleted', 'Product', $p['id'], 'Moved "' . $p['name'] . '" to the recycle bin', ['sku' => $p['sku']]);
        $this->flash('Product moved to the recycle bin. Leads that reference it keep their history.');
        return $this->redirect('/admin/products');
    }

    /** Catalogue order and featured order, per market. */
    public function order(): Response
    {
        $this->authorize('products.edit');
        $country = Countries::adminCurrent();
        $rows = DB::all('SELECT p.id, p.name, pc.is_featured, pc.status FROM product_countries pc JOIN products p ON p.id = pc.product_id WHERE pc.country_id = ? AND p.deleted_at IS NULL ORDER BY pc.sort_order, p.name', [$country['id']]);
        $featured = DB::all('SELECT p.id, p.name FROM product_countries pc JOIN products p ON p.id = pc.product_id WHERE pc.country_id = ? AND pc.is_featured = 1 AND p.deleted_at IS NULL ORDER BY pc.featured_order, p.name', [$country['id']]);
        return $this->screen('products/order', ['title' => 'Product order', 'rows' => $rows, 'featured' => $featured]);
    }

    public function saveOrder(): Response
    {
        $this->authorize('products.edit');
        $country = Countries::adminCurrent();
        $column = $this->request->str('type') === 'featured' ? 'featured_order' : 'sort_order';
        $ids = array_values(array_filter(array_map('intval', explode(',', $this->request->str('order')))));
        DB::transaction(static function () use ($ids, $country, $column): void {
            foreach ($ids as $i => $pid) {
                DB::update('product_countries', [$column => $i * 10], ['product_id' => $pid, 'country_id' => $country['id']]);
                if ($column === 'sort_order' && (int) $country['is_default'] === 1) {
                    DB::update('products', ['sort_order' => $i * 10], ['id' => $pid]);
                }
            }
        });
        Audit::record('reordered', 'Product', null, ucfirst(str_replace('_', ' ', $column)) . ' in ' . $country['name']);
        return $this->json(['ok' => true]);
    }

    public function settings(): Response
    {
        $this->authorize('settings.manage');
        return $this->screen('products/settings', ['title' => 'Product page settings', 's' => Settings::group('product'), 'forms' => Options::forms(), 'narrow' => true]);
    }

    public function saveSettings(): Response
    {
        $this->authorize('settings.manage');
        $base = Slugs::make($this->request->str('base_path'));
        if ($base === '' || in_array($base, Countries::RESERVED, true)) {
            throw ValidationException::with('base_path', 'Choose a different address prefix.');
        }
        Settings::save('product', ['base_path' => $base] + $this->request->body);
        Audit::record('updated', 'Settings', 'product');
        $this->flash('Product settings saved.');
        return $this->redirect('/admin/products/settings');
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $p = DB::first('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$p) {
            throw HttpException::notFound();
        }
        return $p;
    }
}
