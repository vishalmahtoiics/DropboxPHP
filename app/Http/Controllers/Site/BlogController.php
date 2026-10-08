<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\DB;
use App\Core\Response;
use App\Core\View;
use App\Http\Controller;
use App\Services\Content;
use App\Services\Countries;
use App\Services\Seo;
use App\Services\Settings;

/** The blog: archive (with search and pagination), category, tag and article. */
final class BlogController extends Controller
{
    /** @param list<string> $segments path after the blog base */
    public function dispatch(array $segments): ?Response
    {
        $country = Countries::current();
        $cid = (int) $country['id'];
        $count = count($segments);
        if ($count === 0) {
            return $this->archive(null, null);
        }
        if ($count === 2 && $segments[0] === 'category') {
            $cat = DB::first('SELECT * FROM blog_categories WHERE slug = ? AND is_active = 1', [$segments[1]]);
            return $cat ? $this->archive($cat, null) : null;
        }
        if ($count === 2 && $segments[0] === 'tag') {
            $tag = DB::first('SELECT * FROM blog_tags WHERE slug = ? AND is_active = 1', [$segments[1]]);
            return $tag ? $this->archive(null, $tag) : null;
        }
        if ($count === 1) {
            $post = Content::post($cid, $segments[0]);
            return $post ? $this->post($post) : null;
        }
        return null;
    }

    private function archive(?array $category, ?array $tag): Response
    {
        $country = Countries::current();
        $settings = Settings::group('blog');
        $perPage = max(1, min(48, (int) $settings['posts_per_page']));
        $page = max(1, $this->request->int('page', 1));
        $q = mb_substr($this->request->queryStr('q'), 0, 100);
        $result = Content::posts((int) $country['id'], [
            'category' => $category['id'] ?? null,
            'tag' => $tag['id'] ?? null,
            'q' => $q,
        ], $page, $perPage);
        $base = Content::blogBase($country);
        $path = $category ? $base . '/category/' . $category['slug'] : ($tag ? $base . '/tag/' . $tag['slug'] : $base);
        $heading = $category['name'] ?? ($tag ? '#' . $tag['name'] : $settings['title']);
        $intro = $category['description'] ?? ($tag['description'] ?? $settings['intro']);
        $titleBase = $category['seo_title'] ?? ($tag['seo_title'] ?? null) ?: $heading;
        $pages = max(1, (int) ceil($result['total'] / $perPage));
        $canonical = $page > 1 ? $path . '?page=' . $page : $path;

        return Response::html(View::render('site/blog-index', [
            'posts' => $result['items'],
            'total' => $result['total'],
            'pageNo' => $page,
            'pages' => $pages,
            'q' => $q,
            'heading' => $heading,
            'intro' => $intro,
            'basePath' => $path,
            'blogBase' => $base,
            'categories' => Content::blogCategories((int) $country['id']),
            'activeCategory' => $category['id'] ?? null,
            'blogSettings' => $settings,
            'graph' => [Seo::breadcrumbs(array_values(array_filter([
                ['Home', Countries::href(null, '/')],
                [$settings['title'], $base],
                $category ? [$category['name'], $path] : null,
                $tag ? [$tag['name'], $path] : null,
            ])))],
            'seo' => Seo::meta([
                'title' => $titleBase . ($page > 1 ? " – page $page" : ''),
                'description' => ($category['seo_description'] ?? ($tag['seo_description'] ?? null)) ?: $intro,
                'canonical' => $canonical,
                'noindex' => $q !== '' || (int) ($category['no_index'] ?? 0) === 1 || (int) ($tag['no_index'] ?? 0) === 1,
            ]),
        ], 'layouts/site'));
    }

    public function post(array $post, bool $preview = false): Response
    {
        $country = Countries::current();
        $url = Content::postUrl($post, $country);
        $settings = Settings::group('blog');
        if (!$preview) {
            DB::query('UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?', [$post['id']]);
        }
        $content = \App\Core\Sanitizer::html((string) $post['content']);
        [$content, $toc] = self::withToc($content);
        $related = $settings['show_related'] ? Content::posts((int) $country['id'], ['category' => $post['category_id'], 'exclude' => $post['id']], 1, 3)['items'] : [];
        $og = $post['og_image_id'] ? Content::mediaUrl((int) $post['og_image_id']) : ($post['image_url'] ?? null);
        $base = Content::blogBase($country);
        $crumbs = [['Home', Countries::href(null, '/')], [$settings['title'], $base]];
        if ($post['category_name']) {
            $crumbs[] = [$post['category_name'], $base . '/category/' . $post['category_slug']];
        }
        $crumbs[] = [$post['title'], $url];

        return Response::html(View::render('site/blog-post', [
            'post' => $post,
            'html' => $content,
            'toc' => $settings['show_toc'] ? $toc : [],
            'tags' => Content::tagsForPost((int) $post['id']),
            'related' => $related,
            'url' => $url,
            'blogBase' => $base,
            'blogSettings' => $settings,
            'categories' => Content::blogCategories((int) $country['id']),
            'graph' => [Seo::article($post, $url), Seo::breadcrumbs($crumbs)],
            'seo' => Seo::meta([
                'title' => $post['seo_title'] ?: $post['title'],
                'description' => $post['seo_description'] ?: $post['excerpt'],
                'canonical' => $post['canonical_url'] ?: $url,
                'image' => $og,
                'type' => 'article',
                'noindex' => (int) $post['no_index'] === 1 || $preview,
                'nofollow' => (int) $post['no_follow'] === 1,
                'og_title' => $post['og_title'],
                'og_description' => $post['og_description'],
                'published' => $post['published_at'],
                'modified' => $post['updated_at'],
            ]),
        ], 'layouts/site'));
    }

    /**
     * Give every h2/h3 an id and collect a table of contents.
     * @return array{0:string,1:list<array{level:int,id:string,text:string}>}
     */
    public static function withToc(string $html): array
    {
        $toc = [];
        $used = [];
        $html = preg_replace_callback('#<h([23])([^>]*)>(.*?)</h\1>#is', static function (array $m) use (&$toc, &$used): string {
            $text = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5));
            $id = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)) ?? '', '-') ?: 'section';
            $base = $id;
            for ($i = 2; isset($used[$id]); $i++) {
                $id = $base . '-' . $i;
            }
            $used[$id] = true;
            $toc[] = ['level' => (int) $m[1], 'id' => $id, 'text' => $text];
            $attrs = preg_replace('/\sid="[^"]*"/i', '', $m[2]);
            return '<h' . $m[1] . $attrs . ' id="' . e($id) . '">' . $m[3] . '</h' . $m[1] . '>';
        }, $html) ?? $html;
        return [$html, $toc];
    }
}
