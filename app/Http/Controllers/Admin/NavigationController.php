<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Http\AdminController;
use App\Services\Countries;
use App\Services\Navigation;
use App\Services\Options;

/**
 * Menus per market and location. The editor posts the whole tree as JSON
 * (items with children); it is validated item by item and written in one
 * transaction, so a menu is never half-saved.
 */
final class NavigationController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('navigation.manage');
        $country = Countries::adminCurrent();
        $location = $this->request->queryStr('location', 'HEADER');
        if (!isset(Navigation::LOCATIONS[$location])) {
            $location = 'HEADER';
        }
        $tree = Navigation::tree((int) $country['id'], $location, false);
        return $this->screen('navigation/index', [
            'title' => 'Navigation',
            'location' => $location,
            'tree' => $tree,
            'pages' => Options::pages((int) $country['id']),
            'products' => Options::products(),
            'posts' => array_column(DB::all('SELECT id, title FROM blog_posts WHERE country_id = ? AND deleted_at IS NULL ORDER BY title', [$country['id']]), 'title', 'id'),
            'blogCategories' => Options::blogCategoriesById(),
        ]);
    }

    public function save(string $location): Response
    {
        $this->authorize('navigation.manage');
        if (!isset(Navigation::LOCATIONS[$location])) {
            throw HttpException::notFound();
        }
        $country = Countries::adminCurrent();
        Countries::assertAccess((int) $country['id']);
        $items = json_decode($this->request->str('tree'), true);
        if (!is_array($items)) {
            $this->flash('The menu could not be read. Reload and try again.', 'error');
            return $this->back('/admin/navigation');
        }
        DB::transaction(function () use ($country, $location, $items): void {
            $nav = DB::first('SELECT id FROM navigations WHERE country_id = ? AND location = ?', [$country['id'], $location]);
            $navId = $nav ? (int) $nav['id'] : DB::insert('navigations', ['country_id' => $country['id'], 'name' => Navigation::LOCATIONS[$location], 'location' => $location]);
            DB::delete('navigation_items', ['navigation_id' => $navId]);
            $this->insertItems($navId, null, $items, 0);
        });
        Audit::record('updated', 'Navigation', $location, $country['name'] . ' ' . strtolower(Navigation::LOCATIONS[$location]));
        $this->flash('Menu saved.');
        return $this->redirect('/admin/navigation?location=' . $location);
    }

    private function insertItems(int $navId, ?int $parent, array $items, int $depth): void
    {
        foreach (array_slice(array_values($items), 0, 60) as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = Sanitizer::line((string) ($item['label'] ?? ''), 120);
            if ($label === '') {
                continue;
            }
            $type = in_array($item['link_type'] ?? '', ['INTERNAL', 'EXTERNAL', 'PAGE', 'PRODUCT', 'BLOG_POST', 'BLOG_CATEGORY'], true) ? $item['link_type'] : 'INTERNAL';
            $ref = static fn (string $table, $id) => is_numeric($id) && DB::value("SELECT id FROM `$table` WHERE id = ?", [(int) $id]) ? (int) $id : null;
            $id = DB::insert('navigation_items', [
                'navigation_id' => $navId,
                'parent_id' => $parent,
                'label' => $label,
                'link_type' => $type,
                'url' => in_array($type, ['INTERNAL', 'EXTERNAL'], true) ? (Sanitizer::url((string) ($item['url'] ?? '')) ?: null) : null,
                'page_id' => $type === 'PAGE' ? $ref('pages', $item['page_id'] ?? null) : null,
                'product_id' => $type === 'PRODUCT' ? $ref('products', $item['product_id'] ?? null) : null,
                'blog_post_id' => $type === 'BLOG_POST' ? $ref('blog_posts', $item['blog_post_id'] ?? null) : null,
                'blog_category_id' => $type === 'BLOG_CATEGORY' ? $ref('blog_categories', $item['blog_category_id'] ?? null) : null,
                'description' => Sanitizer::line((string) ($item['description'] ?? ''), 255) ?: null,
                'icon' => isset(\App\Cms\Icons::PATHS[$item['icon'] ?? '']) ? $item['icon'] : null,
                'open_in_new_tab' => !empty($item['open_in_new_tab']) ? 1 : 0,
                'is_highlighted' => !empty($item['is_highlighted']) ? 1 : 0,
                'mega_menu' => !empty($item['mega_menu']) ? 1 : 0,
                'mega_columns' => max(1, min(4, (int) ($item['mega_columns'] ?? 2))),
                'is_visible' => !isset($item['is_visible']) || !empty($item['is_visible']) ? 1 : 0,
                'sort_order' => $i,
            ]);
            if ($depth < 1 && !empty($item['children']) && is_array($item['children'])) {
                $this->insertItems($navId, $id, $item['children'], $depth + 1);
            }
        }
    }
}
