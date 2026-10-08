<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Http\AdminController;
use App\Services\Slugs;

final class BlogTaxonomyController extends AdminController
{
    private const TABS = [['Categories', '/admin/blog/categories'], ['Tags', '/admin/blog/tags']];

    public function categories(): Response
    {
        $this->authorize('blog.categories');
        $rows = DB::all('SELECT c.*, p.name AS parent_name, m.url AS image_url, (SELECT COUNT(*) FROM blog_posts bp WHERE bp.category_id = c.id AND bp.deleted_at IS NULL) AS n FROM blog_categories c LEFT JOIN blog_categories p ON p.id = c.parent_id LEFT JOIN media m ON m.id = c.image_id ORDER BY c.sort_order, c.name');
        return $this->list('Blog categories', '/admin/blog/categories', $rows, ['description', 'image', 'seo', 'is_active'], array_column($rows, 'name', 'id'));
    }

    public function tags(): Response
    {
        $this->authorize('blog.tags');
        $rows = DB::all('SELECT t.*, (SELECT COUNT(*) FROM blog_post_tags pt WHERE pt.tag_id = t.id) AS n FROM blog_tags t ORDER BY t.name');
        return $this->list('Blog tags', '/admin/blog/tags', $rows, ['description', 'seo', 'is_active', 'no_sort'], null);
    }

    private function list(string $heading, string $action, array $rows, array $fields, ?array $parents): Response
    {
        $edit = null;
        foreach ($rows as $r) {
            if ((int) $r['id'] === $this->request->int('edit')) {
                $edit = $r;
            }
        }
        $data = ['title' => $heading, 'heading' => $heading, 'rows' => $rows, 'edit' => $edit, 'action' => $action, 'countLabel' => 'Articles',
            'countKey' => 'n', 'fields' => $fields, 'tabs' => self::TABS, 'crumbs' => [['Blog', '/admin/blog'], [$heading, null]]];
        if ($parents !== null) {
            $data['parents'] = $parents;
        }
        return $this->screen('taxonomy', $data);
    }

    public function saveCategory(): Response
    {
        $this->authorize('blog.categories');
        $d = $this->validate(['id' => 'nullable|int', 'name' => 'required|max:160', 'slug' => 'nullable|max:160', 'description' => 'nullable|max:2000', 'parent_id' => 'nullable|int', 'image' => 'nullable|max:1000', 'seo_title' => 'nullable|max:255', 'seo_description' => 'nullable|max:500', 'no_index' => 'bool', 'is_active' => 'bool', 'sort_order' => 'nullable|int']);
        $id = $d['id'] ?: null;
        $row = [
            'name' => Sanitizer::line((string) $d['name'], 160),
            'slug' => Slugs::unique('blog_categories', Slugs::make((string) ($d['slug'] ?: $d['name'])), $id),
            'description' => $d['description'] ?: null,
            'parent_id' => $d['parent_id'] && (int) $d['parent_id'] !== $id ? $d['parent_id'] : null,
            'image_id' => $d['image'] ? (DB::value('SELECT id FROM media WHERE url = ?', [$d['image']]) ?: null) : null,
            'seo_title' => $d['seo_title'] ?: null, 'seo_description' => $d['seo_description'] ?: null,
            'no_index' => $d['no_index'] ? 1 : 0, 'is_active' => $d['is_active'] ? 1 : 0, 'sort_order' => (int) $d['sort_order'],
        ];
        $id ? DB::update('blog_categories', $row, ['id' => $id]) : ($id = DB::insert('blog_categories', $row));
        Audit::record('saved', 'BlogCategory', $id, $row['name']);
        $this->flash('Category saved.');
        return $this->redirect('/admin/blog/categories');
    }

    public function saveTag(): Response
    {
        $this->authorize('blog.tags');
        $d = $this->validate(['id' => 'nullable|int', 'name' => 'required|max:120', 'slug' => 'nullable|max:120', 'description' => 'nullable|max:2000', 'seo_title' => 'nullable|max:255', 'seo_description' => 'nullable|max:500', 'no_index' => 'bool', 'is_active' => 'bool']);
        $id = $d['id'] ?: null;
        $row = [
            'name' => Sanitizer::line((string) $d['name'], 120),
            'slug' => Slugs::unique('blog_tags', Slugs::make((string) ($d['slug'] ?: $d['name'])), $id),
            'description' => $d['description'] ?: null, 'seo_title' => $d['seo_title'] ?: null, 'seo_description' => $d['seo_description'] ?: null,
            'no_index' => $d['no_index'] ? 1 : 0, 'is_active' => $d['is_active'] ? 1 : 0,
        ];
        $id ? DB::update('blog_tags', $row, ['id' => $id]) : ($id = DB::insert('blog_tags', $row));
        Audit::record('saved', 'BlogTag', $id, $row['name']);
        $this->flash('Tag saved.');
        return $this->redirect('/admin/blog/tags');
    }

    public function deleteCategory(string $id): Response
    {
        $this->authorize('blog.categories');
        DB::delete('blog_categories', ['id' => $this->id($id)]);
        Audit::record('deleted', 'BlogCategory', $id);
        $this->flash('Category deleted. Its articles are now uncategorised.');
        return $this->redirect('/admin/blog/categories');
    }

    public function deleteTag(string $id): Response
    {
        $this->authorize('blog.tags');
        DB::delete('blog_tags', ['id' => $this->id($id)]);
        Audit::record('deleted', 'BlogTag', $id);
        $this->flash('Tag deleted.');
        return $this->redirect('/admin/blog/tags');
    }
}
