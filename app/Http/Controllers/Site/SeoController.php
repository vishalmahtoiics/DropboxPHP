<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Http\Controller;
use App\Services\Content;
use App\Services\Countries;
use App\Services\Settings;

final class SeoController extends Controller
{
    public function robots(): Response
    {
        $seo = Settings::group('seo');
        $lines = ['User-agent: *'];
        if ($seo['noindex_site']) {
            $lines[] = 'Disallow: /';
        } else {
            foreach (['/admin', '/preview', '/forms/', '/auth', '/cron', '/lead-magnet/'] as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
            foreach (Countries::active() as $country) {
                foreach (preg_split('/\R/', (string) Countries::setting($country, 'robots_disallow', '')) ?: [] as $rule) {
                    $rule = trim($rule);
                    if ($rule !== '' && str_starts_with($rule, '/')) {
                        $lines[] = 'Disallow: ' . Countries::href($country, $rule);
                    }
                }
                if (Countries::setting($country, 'noindex', false) && (int) $country['is_default'] === 0) {
                    $lines[] = 'Disallow: /' . $country['slug'] . '/';
                }
            }
            $lines[] = 'Allow: /';
        }
        $extra = trim((string) $seo['robots_extra']);
        if ($extra !== '') {
            $lines[] = '';
            foreach (preg_split('/\R/', $extra) ?: [] as $line) {
                if (preg_match('/^(User-agent|Disallow|Allow|Crawl-delay|Sitemap|Host):/i', trim($line))) {
                    $lines[] = trim($line);
                }
            }
        }
        if ($seo['sitemap_enabled'] && !$seo['noindex_site']) {
            $lines[] = '';
            $lines[] = 'Sitemap: ' . absolute_url('/sitemap.xml');
        }
        return Response::text(implode("\n", $lines) . "\n")->header('Cache-Control', 'public, max-age=3600');
    }

    /** One sitemap for every market's published, indexable content. */
    public function sitemap(): Response
    {
        $seo = Settings::group('seo');
        if (!$seo['sitemap_enabled'] || $seo['noindex_site']) {
            throw HttpException::notFound();
        }
        $urls = [];
        $add = static function (string $path, ?string $modified, string $freq = 'weekly', string $priority = '0.6') use (&$urls): void {
            $urls[] = [absolute_url($path), $modified ? date('Y-m-d', strtotime($modified)) : null, $freq, $priority];
        };
        $productBase = trim((string) Settings::get('product', 'base_path', 'products'), '/');
        foreach (Countries::published() as $country) {
            if (Countries::setting($country, 'noindex', false) || Countries::setting($country, 'exclude_from_sitemap', false)) {
                continue;
            }
            $cid = (int) $country['id'];
            foreach (DB::all('SELECT slug, updated_at FROM pages WHERE country_id = ? AND no_index = 0 AND (canonical_url IS NULL OR canonical_url = \'\') AND ' . Content::publishedSql() . ' ORDER BY slug', [$cid]) as $p) {
                $add(Countries::href($country, '/' . $p['slug']), $p['updated_at'], $p['slug'] === '' ? 'daily' : 'weekly', $p['slug'] === '' ? '1.0' : '0.7');
            }
            foreach (DB::all(
                "SELECT p.slug, GREATEST(p.updated_at, pc.updated_at) AS updated_at FROM products p JOIN product_countries pc ON pc.product_id = p.id AND pc.country_id = ?
                  WHERE p.no_index = 0 AND pc.no_index = 0 AND pc.status = 'PUBLISHED' AND " . Content::publishedSql('p'),
                [$cid]
            ) as $p) {
                $add(Countries::href($country, '/' . $productBase . '/' . $p['slug']), $p['updated_at'], 'weekly', '0.8');
            }
            $blogBase = Content::blogBase($country);
            $posts = DB::all('SELECT slug, updated_at FROM blog_posts WHERE country_id = ? AND no_index = 0 AND ' . Content::publishedSql() . ' ORDER BY published_at DESC', [$cid]);
            if ($posts) {
                $add($blogBase, $posts[0]['updated_at'], 'daily', '0.6');
            }
            foreach ($posts as $p) {
                $add($blogBase . '/' . $p['slug'], $p['updated_at'], 'monthly', '0.6');
            }
            foreach (DB::all('SELECT bc.slug, MAX(bp.updated_at) AS updated_at FROM blog_categories bc JOIN blog_posts bp ON bp.category_id = bc.id AND bp.country_id = ? AND ' . Content::publishedSql('bp') . ' WHERE bc.is_active = 1 AND bc.no_index = 0 GROUP BY bc.slug', [$cid]) as $c) {
                $add($blogBase . '/category/' . $c['slug'], $c['updated_at'], 'weekly', '0.4');
            }
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$loc, $mod, $freq, $prio]) {
            $xml .= '<url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . ($mod ? '<lastmod>' . $mod . '</lastmod>' : '')
                . '<changefreq>' . $freq . '</changefreq><priority>' . $prio . '</priority></url>' . "\n";
        }
        $xml .= '</urlset>';
        return Response::text($xml, 200, 'application/xml')->header('Cache-Control', 'public, max-age=1800');
    }
}
