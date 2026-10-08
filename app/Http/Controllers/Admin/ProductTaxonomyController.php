<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Http\AdminController;
use App\Services\Slugs;

/** Product categories and brands. Removing one never removes a product (ON DELETE SET NULL). */
final class ProductTaxonomyController extends AdminController
{
    private const TABS = [['Categories', '/admin/products/categories'], ['Brands', '/admin/products/brands']];

    public function categories(): Response
    {
        $this->authorize('products.edit');
        $rows = DB::all('SELECT c.*, m.url AS image_url, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS n FROM product_categories c LEFT JOIN media m ON m.id = c.image_id WHERE c.deleted_at IS NULL ORDER BY c.sort_order, c.name');
        return $this->list('Product categories', '/admin/products/categories', $rows, ['description', 'image']);
    }

    public function brands(): Response
    {
        $this->authorize('products.edit');
        $rows = DB::all('SELECT b.*, m.url AS image_url, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.deleted_at IS NULL) AS n FROM brands b LEFT JOIN media m ON m.id = b.logo_id WHERE b.deleted_at IS NULL ORDER BY b.sort_order, b.name');
        return $this->list('Brands', '/admin/products/brands', $rows, ['description', 'image', 'website_url']);
    }

    private function list(string $heading, string $action, array $rows, array $fields): Response
    {
        $editId = $this->request->int('edit');
        $edit = null;
        foreach ($rows as $r) {
            if ((int) $r['id'] === $editId) {
                $edit = $r;
            }
        }
        return $this->screen('taxonomy', [
            'title' => $heading, 'heading' => $heading, 'rows' => $rows, 'edit' => $edit, 'action' => $action,
            'countLabel' => 'Products', 'countKey' => 'n', 'fields' => $fields, 'tabs' => self::TABS,
            'crumbs' => [['Products', '/admin/products'], [$heading, null]],
        ]);
    }

    public function saveCategory(): Response
    {
        $this->authorize('products.edit');
        $this->saveRow('product_categories', 'image_id', '/admin/products/categories');
        return $this->redirect('/admin/products/categories');
    }

    public function saveBrand(): Response
    {
        $this->authorize('products.edit');
        $this->saveRow('brands', 'logo_id', '/admin/products/brands');
        return $this->redirect('/admin/products/brands');
    }

    private function saveRow(string $table, string $imageColumn, string $back): void
    {
        $d = $this->validate(['id' => 'nullable|int', 'name' => 'required|max:160', 'slug' => 'nullable|max:160', 'description' => 'nullable|max:2000', 'image' => 'nullable|max:1000', 'website_url' => 'nullable|absurl|max:500', 'sort_order' => 'nullable|int']);
        $id = $d['id'] ?: null;
        $row = [
            'name' => Sanitizer::line((string) $d['name'], 160),
            'slug' => Slugs::unique($table, Slugs::make((string) ($d['slug'] ?: $d['name'])), $id),
            'description' => $d['description'] ?: null,
            $imageColumn => $d['image'] ? (DB::value('SELECT id FROM media WHERE url = ?', [$d['image']]) ?: null) : null,
            'sort_order' => (int) $d['sort_order'],
        ];
        if ($table === 'brands') {
            $row['website_url'] = $d['website_url'] ?: null;
        }
        if ($id) {
            DB::update($table, $row, ['id' => $id]);
        } else {
            $id = DB::insert($table, $row);
        }
        Audit::record('saved', $table === 'brands' ? 'Brand' : 'ProductCategory', $id, $row['name']);
        $this->flash('Saved.');
    }

    public function deleteCategory(string $id): Response
    {
        $this->authorize('products.delete');
        $row = DB::first('SELECT * FROM product_categories WHERE id = ?', [$this->id($id)]);
        if ($row) {
            DB::update('products', ['category_id' => null], ['category_id' => $row['id']]);
            DB::update('product_categories', ['deleted_at' => now(), 'slug' => Slugs::trashed($row['slug'], (int) $row['id'])], ['id' => $row['id']]);
            Audit::record('deleted', 'ProductCategory', $row['id'], $row['name']);
        }
        $this->flash('Category moved to the recycle bin.');
        return $this->redirect('/admin/products/categories');
    }

    public function deleteBrand(string $id): Response
    {
        $this->authorize('products.delete');
        $row = DB::first('SELECT * FROM brands WHERE id = ?', [$this->id($id)]);
        if ($row) {
            DB::update('products', ['brand_id' => null], ['brand_id' => $row['id']]);
            DB::update('brands', ['deleted_at' => now(), 'slug' => Slugs::trashed($row['slug'], (int) $row['id'])], ['id' => $row['id']]);
            Audit::record('deleted', 'Brand', $row['id'], $row['name']);
        }
        $this->flash('Brand moved to the recycle bin.');
        return $this->redirect('/admin/products/brands');
    }
}
