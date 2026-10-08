<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Cms\Blocks;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\Content;
use App\Services\Countries;
use App\Services\Redirects;
use App\Services\Slugs;

final class PageController extends AdminController
{
    private const STATUSES = ['DRAFT' => 'Draft', 'PUBLISHED' => 'Published', 'SCHEDULED' => 'Scheduled', 'ARCHIVED' => 'Archived'];

    public function index(): Response
    {
        $this->authorize('pages.view');
        $country = Countries::adminCurrent();
        $q = $this->request->queryStr('q');
        $status = $this->request->queryStr('status');
        $category = $this->request->int('category');
        $sort = in_array($this->request->queryStr('sort'), ['title', 'slug', 'status', 'updated_at'], true) ? $this->request->queryStr('sort') : 'updated_at';
        $dir = $this->request->queryStr('dir') === 'asc' ? 'ASC' : 'DESC';
        $where = 'p.country_id = ? AND p.deleted_at IS NULL';
        $params = [(int) $country['id']];
        if ($q !== '') {
            $where .= ' AND (p.title LIKE ? OR p.slug LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if (isset(self::STATUSES[$status])) {
            $where .= ' AND p.status = ?';
            $params[] = $status;
        }
        if ($category) {
            $where .= ' AND p.category_id = ?';
            $params[] = $category;
        }
        $page = max(1, $this->request->int('page', 1));
        $per = 25;
        $total = (int) DB::value("SELECT COUNT(*) FROM pages p WHERE $where", $params);
        $rows = DB::all(
            "SELECT p.*, pc.name AS category_name, u.name AS editor, (SELECT COUNT(*) FROM page_sections s WHERE s.page_id = p.id) AS sections
               FROM pages p LEFT JOIN page_categories pc ON pc.id = p.category_id LEFT JOIN users u ON u.id = p.updated_by_id
              WHERE $where ORDER BY p.slug = '' DESC, p.`$sort` $dir LIMIT $per OFFSET " . (($page - 1) * $per),
            $params
        );
        $counts = array_column(DB::all('SELECT status, COUNT(*) n FROM pages WHERE country_id = ? AND deleted_at IS NULL GROUP BY status', [(int) $country['id']]), 'n', 'status');
        return $this->screen('pages/index', [
            'title' => 'Pages', 'rows' => $rows, 'total' => $total, 'pageNo' => $page, 'pages' => (int) ceil($total / $per),
            'q' => $q, 'status' => $status, 'category' => $category, 'sort' => $sort, 'dir' => strtolower($dir), 'counts' => $counts,
            'categories' => \App\Services\Options::pageCategories(), 'statuses' => self::STATUSES,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('pages.create');
        $country = Countries::adminCurrent();
        return $this->screen('pages/create', [
            'title' => 'New page',
            'templates' => \App\Services\Options::pages((int) $country['id']),
            'categories' => \App\Services\Options::pageCategories(),
            'narrow' => true,
        ]);
    }

    public function store(): Response
    {
        $this->authorize('pages.create');
        $country = Countries::adminCurrent();
        Countries::assertAccess((int) $country['id']);
        $data = $this->validate([
            'title' => 'required|max:255',
            'slug' => 'nullable|max:255',
            'category_id' => 'nullable|int',
            'template_id' => 'nullable|int',
        ]);
        $slug = Slugs::make((string) ($data['slug'] ?: $data['title']), true);
        if ($slug === '' && $data['slug'] !== '' && $data['slug'] !== null) {
            throw ValidationException::with('slug', 'Enter a valid address.');
        }
        Slugs::assertPageSlug($slug, (int) $country['id']);
        $id = DB::transaction(function () use ($data, $slug, $country): int {
            $id = DB::insert('pages', [
                'country_id' => $country['id'],
                'title' => Sanitizer::line((string) $data['title']),
                'slug' => $slug,
                'status' => 'DRAFT',
                'category_id' => $data['category_id'] ?: null,
                'created_by_id' => $this->me['id'],
                'updated_by_id' => $this->me['id'],
            ]);
            if ($data['template_id']) {
                $source = DB::first('SELECT country_id FROM pages WHERE id = ?', [$data['template_id']]);
                if ($source && in_array((int) $source['country_id'], Countries::accessibleIds(), true)) {
                    self::copySections((int) $data['template_id'], $id);
                }
            }
            return $id;
        });
        Audit::record('created', 'Page', $id, 'Created page "' . $data['title'] . '"');
        $this->flash('Page created. Add sections to build it.');
        return $this->redirect('/admin/pages/' . $id);
    }

    public function edit(string $id): Response
    {
        $this->authorize('pages.view');
        $page = $this->find($this->id($id));
        return $this->screen('pages/edit', [
            'title' => $page['title'],
            'p' => $page,
            'sections' => Content::sections((int) $page['id'], true),
            'blocks' => Blocks::grouped(),
            'categories' => \App\Services\Options::pageCategories(),
            'statuses' => self::STATUSES,
            'publicPath' => Content::pagePath($page),
        ]);
    }

    public function update(string $id): Response
    {
        $this->authorize('pages.edit');
        $page = $this->find($this->id($id));
        $data = $this->validate([
            'title' => 'required|max:255',
            'slug' => 'nullable|max:255',
            'status' => 'required|in:DRAFT,PUBLISHED,SCHEDULED,ARCHIVED',
            'published_at' => 'nullable|date',
            'category_id' => 'nullable|int',
            'show_header' => 'bool',
            'show_footer' => 'bool',
            'seo_title' => 'nullable|max:255',
            'seo_description' => 'nullable|max:500',
            'canonical_url' => 'nullable|url|max:500',
            'no_index' => 'bool',
            'no_follow' => 'bool',
            'og_title' => 'nullable|max:255',
            'og_description' => 'nullable|max:500',
            'og_image' => 'nullable|max:1000',
            'primary_keywords' => 'nullable|max:500',
        ]);
        $isHome = $page['slug'] === '';
        $slug = $isHome ? '' : Slugs::make((string) $data['slug'], true);
        if (!$isHome && $slug === '') {
            throw ValidationException::with('slug', 'Every page except the homepage needs an address.');
        }
        Slugs::assertPageSlug($slug, (int) $page['country_id'], (int) $page['id']);
        if ($data['status'] !== $page['status'] && in_array($data['status'], ['PUBLISHED', 'SCHEDULED'], true) && !Auth::can('pages.publish')) {
            throw ValidationException::with('status', 'Your role cannot publish pages. Save as draft and ask an editor to publish.');
        }
        if ($data['status'] === 'SCHEDULED' && (!$data['published_at'] || strtotime((string) $data['published_at']) <= time())) {
            throw ValidationException::with('published_at', 'Choose a future date and time to schedule.');
        }
        $publishedAt = $data['published_at'] ? date('Y-m-d H:i:s', strtotime((string) $data['published_at'])) : null;
        if ($data['status'] === 'PUBLISHED' && (!$publishedAt || strtotime($publishedAt) > time())) {
            $publishedAt = $page['published_at'] && strtotime((string) $page['published_at']) <= time() ? $page['published_at'] : now();
        }
        $ogImage = null;
        if ($data['og_image']) {
            $ogImage = DB::value('SELECT id FROM media WHERE url = ? AND deleted_at IS NULL', [$data['og_image']]);
        }
        $row = [
            'title' => Sanitizer::line((string) $data['title']),
            'slug' => $slug,
            'status' => $data['status'],
            'published_at' => $publishedAt,
            'category_id' => $data['category_id'] ?: null,
            'show_header' => $data['show_header'] ? 1 : 0,
            'show_footer' => $data['show_footer'] ? 1 : 0,
            'seo_title' => $data['seo_title'] ?: null,
            'seo_description' => $data['seo_description'] ?: null,
            'canonical_url' => $data['canonical_url'] ?: null,
            'no_index' => $data['no_index'] ? 1 : 0,
            'no_follow' => $data['no_follow'] ? 1 : 0,
            'og_title' => $data['og_title'] ?: null,
            'og_description' => $data['og_description'] ?: null,
            'og_image_id' => $ogImage ?: null,
            'primary_keywords' => $data['primary_keywords'] ?: null,
            'updated_by_id' => $this->me['id'],
        ];
        DB::update('pages', $row, ['id' => $page['id']]);
        if ($slug !== $page['slug'] && $page['status'] === 'PUBLISHED') {
            $country = Countries::find((int) $page['country_id']);
            Redirects::onSlugChange(Countries::href($country, '/' . $page['slug']), Countries::href($country, '/' . $slug));
        }
        Audit::record('updated', 'Page', $page['id'], 'Updated page "' . $row['title'] . '"', array_intersect_key($page, $row), $row);
        $this->flash('Page saved.');
        return $this->redirect('/admin/pages/' . $page['id'] . '#settings');
    }

    public function status(string $id): Response
    {
        $this->authorize('pages.publish');
        $page = $this->find($this->id($id));
        $status = $this->request->str('status');
        if (!isset(self::STATUSES[$status]) || $status === 'SCHEDULED') {
            throw HttpException::notFound();
        }
        DB::update('pages', [
            'status' => $status,
            'published_at' => $status === 'PUBLISHED' ? ($page['published_at'] && strtotime((string) $page['published_at']) <= time() ? $page['published_at'] : now()) : $page['published_at'],
            'updated_by_id' => $this->me['id'],
        ], ['id' => $page['id']]);
        Audit::record(strtolower($status === 'PUBLISHED' ? 'published' : 'status-changed'), 'Page', $page['id'], $page['title'] . ' → ' . $status);
        $this->flash($status === 'PUBLISHED' ? 'Page published.' : 'Page moved to ' . strtolower($status) . '.');
        return $this->back('/admin/pages');
    }

    public function duplicate(string $id): Response
    {
        $this->authorize('pages.create');
        $page = $this->find($this->id($id));
        $newId = DB::transaction(function () use ($page): int {
            $slug = $page['slug'] === '' ? 'home-copy' : $page['slug'] . '-copy';
            $slug = Slugs::unique('pages', $slug, null, ['country_id' => (int) $page['country_id']]);
            $copy = $page;
            unset($copy['id'], $copy['created_at'], $copy['updated_at']);
            $copy['title'] = $page['title'] . ' (copy)';
            $copy['slug'] = $slug;
            $copy['status'] = 'DRAFT';
            $copy['published_at'] = null;
            $copy['is_homepage'] = 0;
            $copy['created_by_id'] = $this->me['id'];
            $copy['updated_by_id'] = $this->me['id'];
            $newId = DB::insert('pages', $copy);
            self::copySections((int) $page['id'], $newId);
            return $newId;
        });
        Audit::record('duplicated', 'Page', $newId, 'Copied from page #' . $page['id']);
        $this->flash('Page duplicated as a draft.');
        return $this->redirect('/admin/pages/' . $newId);
    }

    public function destroy(string $id): Response
    {
        $this->authorize('pages.delete');
        $page = $this->find($this->id($id));
        if ($page['slug'] === '') {
            $this->flash('The homepage cannot be deleted. Edit it instead.', 'error');
            return $this->back('/admin/pages');
        }
        DB::update('pages', ['deleted_at' => now(), 'slug' => Slugs::trashed($page['slug'], (int) $page['id']), 'status' => 'DRAFT'], ['id' => $page['id']]);
        Audit::record('deleted', 'Page', $page['id'], 'Moved "' . $page['title'] . '" to the recycle bin');
        $this->flash('Page moved to the recycle bin.');
        return $this->redirect('/admin/pages');
    }

    /**
     * Copy a page to another market as a draft. If the address is free there it
     * keeps it; otherwise a "-copy" suffix is added. The copy is independent.
     */
    public function copyToMarket(string $id): Response
    {
        $this->authorize('pages.create');
        $page = $this->find($this->id($id));
        $target = $this->request->int('country_id');
        Countries::assertAccess($target);
        if ($target === (int) $page['country_id']) {
            throw ValidationException::with('country_id', 'Choose a different market.');
        }
        $newId = DB::transaction(function () use ($page, $target): int {
            $copy = $page;
            unset($copy['id'], $copy['created_at'], $copy['updated_at']);
            $copy['country_id'] = $target;
            $copy['status'] = 'DRAFT';
            $copy['published_at'] = null;
            $copy['city_id'] = null;
            $copy['generated_from_id'] = $page['id'];
            $copy['slug'] = $page['slug'] === '' && !DB::value("SELECT id FROM pages WHERE country_id = ? AND slug = ''", [$target])
                ? '' : Slugs::unique('pages', $page['slug'] === '' ? 'home' : $page['slug'], null, ['country_id' => $target]);
            $copy['created_by_id'] = $this->me['id'];
            $copy['updated_by_id'] = $this->me['id'];
            $newId = DB::insert('pages', $copy);
            self::copySections((int) $page['id'], $newId);
            return $newId;
        });
        Audit::record('copied', 'Page', $newId, 'Copied "' . $page['title'] . '" to ' . (Countries::find($target)['name'] ?? $target));
        \App\Core\Session::put('admin_country_id', $target);
        $this->flash('Copied as a draft. You are now working in ' . (Countries::find($target)['name'] ?? 'that market') . '.');
        return $this->redirect('/admin/pages/' . $newId);
    }

    public static function copySections(int $fromPage, int $toPage): void
    {
        foreach (DB::all('SELECT * FROM page_sections WHERE page_id = ? ORDER BY sort_order', [$fromPage]) as $s) {
            DB::insert('page_sections', [
                'page_id' => $toPage, 'block_type' => $s['block_type'], 'name' => $s['name'], 'sort_order' => $s['sort_order'],
                'is_visible' => $s['is_visible'], 'content' => $s['content'], 'settings' => $s['settings'],
            ]);
        }
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $page = DB::first('SELECT * FROM pages WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$page) {
            throw HttpException::notFound();
        }
        Countries::assertAccess((int) $page['country_id']);
        return $page;
    }
}
