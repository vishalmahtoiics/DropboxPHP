<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Response;
use App\Http\AdminController;
use App\Services\Content;
use App\Services\Countries;
use App\Services\SeoAnalysis;
use App\Services\Settings;

/** Global SEO settings and the SEO Intelligence overview of every page and article. */
final class SeoController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('seo.manage');
        $country = Countries::adminCurrent();
        $scores = [];
        foreach (DB::all('SELECT * FROM pages WHERE country_id = ? AND deleted_at IS NULL ORDER BY slug', [$country['id']]) as $p) {
            $r = SeoAnalysis::forPage($p);
            $scores[] = ['type' => 'Page', 'title' => $p['title'], 'path' => Content::pagePath($p), 'edit' => '/admin/pages/' . $p['id'] . '#seo', 'status' => $p['status'], 'r' => $r];
        }
        foreach (DB::all('SELECT * FROM blog_posts WHERE country_id = ? AND deleted_at IS NULL ORDER BY published_at DESC LIMIT 200', [$country['id']]) as $p) {
            $r = SeoAnalysis::forPost($p);
            $scores[] = ['type' => 'Article', 'title' => $p['title'], 'path' => Content::postUrl($p), 'edit' => '/admin/blog/' . $p['id'], 'status' => $p['status'], 'r' => $r];
        }
        usort($scores, static fn ($a, $b) => $a['r']['overall'] <=> $b['r']['overall']);
        $siteUrl = (string) config('app.url');
        $browsing = ($this->request->isSecure() ? 'https' : 'http') . '://' . $this->request->host();
        return $this->screen('seo/index', [
            'title' => 'SEO', 's' => Settings::group('seo'), 'scores' => $scores,
            'addressWarning' => rtrim($siteUrl, '/') !== $browsing && !in_array(explode(':', $this->request->host())[0], ['localhost', '127.0.0.1'], true) ? [$siteUrl, $browsing] : null,
            'notFound' => DB::all("SELECT * FROM not_found_log WHERE status = 'OPEN' ORDER BY hits DESC LIMIT 20"),
        ]);
    }

    public function save(): Response
    {
        $this->authorize('seo.manage');
        $input = $this->request->body;
        foreach (['sitemap_enabled', 'noindex_site'] as $k) {
            $input[$k] ??= '';
        }
        $before = Settings::group('seo');
        Settings::save('seo', $input, true);
        Audit::record('updated', 'Settings', 'seo', null, $before, Settings::group('seo'));
        $this->flash('SEO settings saved.');
        return $this->redirect('/admin/seo#settings');
    }
}
