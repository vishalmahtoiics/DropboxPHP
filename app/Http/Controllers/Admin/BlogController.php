<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

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
use App\Services\Options;
use App\Services\Redirects;
use App\Services\Settings;
use App\Services\Slugs;

final class BlogController extends AdminController
{
    private const STATUSES = ['DRAFT' => 'Draft', 'PUBLISHED' => 'Published', 'SCHEDULED' => 'Scheduled', 'ARCHIVED' => 'Archived'];

    public function index(): Response
    {
        $this->authorize('blog.view');
        $country = Countries::adminCurrent();
        $where = 'bp.country_id = ? AND bp.deleted_at IS NULL';
        $params = [(int) $country['id']];
        $q = $this->request->queryStr('q');
        if ($q !== '') {
            $where .= ' AND (bp.title LIKE ? OR bp.slug LIKE ?)';
            array_push($params, "%$q%", "%$q%");
        }
        $status = $this->request->queryStr('status');
        if (isset(self::STATUSES[$status])) {
            $where .= ' AND bp.status = ?';
            $params[] = $status;
        }
        if ($cat = $this->request->int('category')) {
            $where .= ' AND bp.category_id = ?';
            $params[] = $cat;
        }
        $page = max(1, $this->request->int('page', 1));
        $total = (int) DB::value("SELECT COUNT(*) FROM blog_posts bp WHERE $where", $params);
        $rows = DB::all("SELECT bp.*, bc.name AS category_name, u.name AS author_name FROM blog_posts bp LEFT JOIN blog_categories bc ON bc.id = bp.category_id LEFT JOIN users u ON u.id = bp.author_id WHERE $where ORDER BY COALESCE(bp.published_at, bp.created_at) DESC LIMIT 25 OFFSET " . (($page - 1) * 25), $params);
        $counts = array_column(DB::all('SELECT status, COUNT(*) n FROM blog_posts WHERE country_id = ? AND deleted_at IS NULL GROUP BY status', [(int) $country['id']]), 'n', 'status');
        return $this->screen('blog/index', [
            'title' => 'Articles', 'rows' => $rows, 'total' => $total, 'pageNo' => $page, 'pages' => (int) ceil($total / 25),
            'q' => $q, 'status' => $status, 'statuses' => self::STATUSES, 'categories' => Options::blogCategoriesById(), 'counts' => $counts,
            'views' => (int) DB::value('SELECT COALESCE(SUM(view_count),0) FROM blog_posts WHERE country_id = ? AND deleted_at IS NULL', [(int) $country['id']]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('blog.create');
        return $this->editor(null);
    }

    public function edit(string $id): Response
    {
        $this->authorize('blog.view');
        return $this->editor($this->find($this->id($id)));
    }

    private function editor(?array $post): Response
    {
        $tags = DB::all('SELECT id, name FROM blog_tags ORDER BY name');
        $selected = $post ? array_map('intval', DB::column('SELECT tag_id FROM blog_post_tags WHERE post_id = ?', [$post['id']])) : [];
        $url = static fn (?int $id) => $id ? (string) DB::value('SELECT url FROM media WHERE id = ?', [$id]) : '';
        return $this->screen('blog/edit', [
            'title' => $post ? $post['title'] : 'New article',
            'post' => $post,
            'tags' => $tags,
            'selectedTags' => $selected,
            'categories' => Options::blogCategoriesById(),
            'authors' => Options::staff(),
            'statuses' => self::STATUSES,
            'imageUrl' => $post ? $url($post['featured_image_id'] ? (int) $post['featured_image_id'] : null) : '',
            'ogUrl' => $post ? $url($post['og_image_id'] ? (int) $post['og_image_id'] : null) : '',
            'report' => $post ? \App\Services\SeoAnalysis::forPost($post) : null,
            'publicUrl' => $post ? Content::postUrl($post) : '',
        ]);
    }

    public function store(): Response
    {
        $this->authorize('blog.create');
        $id = $this->save(null);
        $this->flash('Article created.');
        return $this->redirect('/admin/blog/' . $id);
    }

    public function update(string $id): Response
    {
        $this->authorize('blog.edit');
        $post = $this->find($this->id($id));
        $this->save($post);
        $this->flash('Article saved.');
        return $this->redirect('/admin/blog/' . $post['id']);
    }

    private function save(?array $existing): int
    {
        $d = $this->validate([
            'title' => 'required|max:255',
            'slug' => 'nullable|max:200',
            'subtitle' => 'nullable|max:255',
            'status' => 'required|in:DRAFT,PUBLISHED,SCHEDULED,ARCHIVED',
            'published_at' => 'nullable|date',
            'excerpt' => 'nullable|max:1000',
            'content' => 'nullable|max:500000',
            'category_id' => 'nullable|int',
            'author_id' => 'nullable|int',
            'is_featured' => 'bool',
            'image' => 'nullable|max:1000',
            'og_image' => 'nullable|max:1000',
            'seo_title' => 'nullable|max:255',
            'seo_description' => 'nullable|max:500',
            'focus_keyword' => 'nullable|max:120',
            'canonical_url' => 'nullable|url|max:500',
            'no_index' => 'bool',
            'no_follow' => 'bool',
            'og_title' => 'nullable|max:255',
            'og_description' => 'nullable|max:500',
            'tags' => 'array',
            'new_tags' => 'nullable|max:500',
        ]);
        $country = $existing ? Countries::find((int) $existing['country_id']) : Countries::adminCurrent();
        Countries::assertAccess((int) $country['id']);
        $slug = Slugs::make((string) ($d['slug'] ?: $d['title']));
        if ($slug === '') {
            throw ValidationException::with('slug', 'Enter an address.');
        }
        if (DB::value('SELECT id FROM blog_posts WHERE country_id = ? AND slug = ? AND id <> ?', [$country['id'], $slug, $existing['id'] ?? 0])) {
            throw ValidationException::with('slug', 'Another article in this market uses this address.');
        }
        $publishing = in_array($d['status'], ['PUBLISHED', 'SCHEDULED'], true) && ($existing['status'] ?? 'DRAFT') !== $d['status'];
        if ($publishing && !Auth::can('blog.publish')) {
            throw ValidationException::with('status', 'Your role cannot publish. Save as draft for an editor to publish.');
        }
        if ($d['status'] === 'SCHEDULED' && (!$d['published_at'] || strtotime((string) $d['published_at']) <= time())) {
            throw ValidationException::with('published_at', 'Choose a future date to schedule.');
        }
        $publishedAt = $d['published_at'] ? date('Y-m-d H:i:s', strtotime((string) $d['published_at'])) : null;
        if ($d['status'] === 'PUBLISHED' && !$publishedAt) {
            $publishedAt = $existing['published_at'] ?? now();
        }
        $content = Sanitizer::html((string) $d['content']);
        $words = str_word_count(strip_tags($content));
        $mediaId = static fn (?string $u) => $u ? (DB::value('SELECT id FROM media WHERE url = ?', [$u]) ?: null) : null;
        $row = [
            'country_id' => $country['id'],
            'title' => Sanitizer::line((string) $d['title']),
            'slug' => $slug,
            'subtitle' => $d['subtitle'] ?: null,
            'status' => $d['status'],
            'published_at' => $publishedAt,
            'excerpt' => Sanitizer::text((string) $d['excerpt'], 1000) ?: Sanitizer::text(str_limit(strip_tags($content), 220), 1000),
            'content' => $content,
            'reading_time' => max(1, (int) ceil($words / 220)),
            'category_id' => $d['category_id'] ?: null,
            'author_id' => $d['author_id'] ?: ($existing['author_id'] ?? $this->me['id']),
            'is_featured' => $d['is_featured'] ? 1 : 0,
            'featured_image_id' => $mediaId($d['image']),
            'og_image_id' => $mediaId($d['og_image']),
            'seo_title' => $d['seo_title'] ?: null,
            'seo_description' => $d['seo_description'] ?: null,
            'focus_keyword' => $d['focus_keyword'] ?: null,
            'canonical_url' => $d['canonical_url'] ?: null,
            'no_index' => $d['no_index'] ? 1 : 0,
            'no_follow' => $d['no_follow'] ? 1 : 0,
            'og_title' => $d['og_title'] ?: null,
            'og_description' => $d['og_description'] ?: null,
        ];
        return DB::transaction(function () use ($row, $existing, $d, $country): int {
            if ($existing) {
                DB::update('blog_posts', $row, ['id' => $existing['id']]);
                $id = (int) $existing['id'];
                if ($row['slug'] !== $existing['slug'] && $existing['status'] === 'PUBLISHED') {
                    $base = Content::blogBase($country);
                    Redirects::onSlugChange($base . '/' . $existing['slug'], $base . '/' . $row['slug']);
                }
                Audit::record('updated', 'BlogPost', $id, $row['title']);
            } else {
                $id = DB::insert('blog_posts', $row);
                Audit::record('created', 'BlogPost', $id, $row['title']);
            }
            $tagIds = array_map('intval', (array) $d['tags']);
            if (Auth::can('blog.tags')) {
                foreach (array_filter(array_map('trim', explode(',', (string) $d['new_tags']))) as $name) {
                    $name = Sanitizer::line($name, 120);
                    $slug = Slugs::make($name);
                    if ($slug === '') {
                        continue;
                    }
                    $tid = DB::value('SELECT id FROM blog_tags WHERE slug = ?', [$slug]) ?: DB::insert('blog_tags', ['name' => $name, 'slug' => $slug]);
                    $tagIds[] = (int) $tid;
                }
            }
            DB::delete('blog_post_tags', ['post_id' => $id]);
            foreach (array_unique($tagIds) as $tid) {
                if (DB::value('SELECT id FROM blog_tags WHERE id = ?', [$tid])) {
                    DB::insert('blog_post_tags', ['post_id' => $id, 'tag_id' => $tid]);
                }
            }
            return $id;
        });
    }

    public function destroy(string $id): Response
    {
        $this->authorize('blog.delete');
        $post = $this->find($this->id($id));
        DB::update('blog_posts', ['deleted_at' => now(), 'slug' => Slugs::trashed($post['slug'], (int) $post['id']), 'status' => 'DRAFT'], ['id' => $post['id']]);
        Audit::record('deleted', 'BlogPost', $post['id'], 'Moved "' . $post['title'] . '" to the recycle bin');
        $this->flash('Article moved to the recycle bin.');
        return $this->redirect('/admin/blog');
    }

    public function settings(): Response
    {
        $this->authorize('blog.design');
        return $this->screen('blog/settings', ['title' => 'Blog settings', 's' => Settings::group('blog'), 'narrow' => true]);
    }

    public function saveSettings(): Response
    {
        $this->authorize('blog.design');
        $base = Slugs::make($this->request->str('base_path'));
        if ($base === '' || in_array($base, Countries::RESERVED, true) || $base === Settings::get('product', 'base_path')) {
            throw ValidationException::with('base_path', 'Choose a different address prefix.');
        }
        $old = (string) Settings::get('blog', 'base_path');
        Settings::save('blog', ['base_path' => $base] + $this->request->body);
        if ($old !== $base) {
            foreach (Countries::active() as $c) {
                Redirects::onSlugChange(Countries::href($c, '/' . $old), Countries::href($c, '/' . $base));
            }
        }
        Audit::record('updated', 'Settings', 'blog');
        $this->flash('Blog settings saved.');
        return $this->redirect('/admin/blog/settings');
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $post = DB::first('SELECT * FROM blog_posts WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$post) {
            throw HttpException::notFound();
        }
        Countries::assertAccess((int) $post['country_id']);
        return $post;
    }
}
