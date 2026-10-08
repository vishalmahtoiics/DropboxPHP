<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Http\AdminController;
use App\Services\Slugs;

final class PageCategoryController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('pages.edit');
        $rows = DB::all('SELECT c.*, p.name AS parent_name, (SELECT COUNT(*) FROM pages WHERE category_id = c.id AND deleted_at IS NULL) AS pages FROM page_categories c LEFT JOIN page_categories p ON p.id = c.parent_id ORDER BY c.sort_order, c.name');
        $edit = $this->request->int('edit') ? DB::first('SELECT * FROM page_categories WHERE id = ?', [$this->request->int('edit')]) : null;
        return $this->screen('taxonomy', [
            'title' => 'Page categories', 'rows' => $rows, 'edit' => $edit, 'action' => '/admin/page-categories',
            'heading' => 'Page categories', 'sub' => 'Group pages in the admin. Categories can be nested.',
            'crumbs' => [['Pages', '/admin/pages'], ['Categories', null]], 'countLabel' => 'Pages', 'countKey' => 'pages',
            'parents' => array_column($rows, 'name', 'id'), 'fields' => ['description'],
        ]);
    }

    public function save(): Response
    {
        $this->authorize('pages.edit');
        $data = $this->validate(['id' => 'nullable|int', 'name' => 'required|max:160', 'slug' => 'nullable|max:160', 'description' => 'nullable|max:2000', 'parent_id' => 'nullable|int', 'sort_order' => 'nullable|int']);
        $id = $data['id'] ?: null;
        $slug = Slugs::unique('page_categories', Slugs::make((string) ($data['slug'] ?: $data['name'])), $id);
        $parent = $data['parent_id'] && (int) $data['parent_id'] !== $id ? (int) $data['parent_id'] : null;
        $row = ['name' => Sanitizer::line((string) $data['name']), 'slug' => $slug, 'description' => $data['description'] ?: null, 'parent_id' => $parent, 'sort_order' => (int) $data['sort_order']];
        if ($id) {
            DB::update('page_categories', $row, ['id' => $id]);
        } else {
            $id = DB::insert('page_categories', $row);
        }
        Audit::record('saved', 'PageCategory', $id, $row['name']);
        $this->flash('Category saved.');
        return $this->redirect('/admin/page-categories');
    }

    public function destroy(string $id): Response
    {
        $this->authorize('pages.delete');
        DB::delete('page_categories', ['id' => $this->id($id)]);
        Audit::record('deleted', 'PageCategory', $id);
        $this->flash('Category deleted. Its pages are now uncategorised.');
        return $this->redirect('/admin/page-categories');
    }
}
