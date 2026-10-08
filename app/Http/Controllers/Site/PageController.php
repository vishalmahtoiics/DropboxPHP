<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controller;
use App\Services\Content;
use App\Services\Countries;
use App\Services\Redirects;
use App\Services\Seo;
use App\Services\Settings;
use App\Services\SiteContext;

/**
 * The public catch-all. Every address that is not a system route lands here
 * and is classified within its market: page, product, blog archive, category,
 * tag or article. Redirects are looked up only when nothing matched, so they
 * cost nothing on the happy path.
 */
final class PageController extends Controller
{
    public function route(string $path = ''): Response
    {
        if (strlen($this->request->path) > 500 || preg_match('/[\x00-\x1f]/', $this->request->path)) {
            throw HttpException::notFound();
        }
        $rest = SiteContext::boot($this->request);
        $country = Countries::current();

        if (Settings::get('website', 'maintenance_mode') && !$this->isStaff()) {
            return Response::html(View::render('site/maintenance', ['title' => 'Back soon']), 503)->header('Retry-After', '600');
        }
        if (\App\Services\Backups::restoreInProgress() && !$this->isStaff()) {
            return Response::html(View::render('site/maintenance', ['title' => 'Back soon']), 503)->header('Retry-After', '300');
        }

        $blogBase = trim((string) Settings::get('blog', 'base_path', 'blog'), '/');
        $productBase = trim((string) Settings::get('product', 'base_path', 'products'), '/');
        $segments = $rest === '' ? [] : explode('/', $rest);

        if ($blogBase !== '' && ($segments[0] ?? null) === $blogBase) {
            $response = (new BlogController($this->request))->dispatch(array_slice($segments, 1));
            if ($response) {
                return $response;
            }
        } elseif ($productBase !== '' && ($segments[0] ?? null) === $productBase && count($segments) === 2) {
            $product = Content::product($country, $segments[1]);
            if ($product) {
                return (new ProductController($this->request))->show($product);
            }
        } else {
            $page = Content::page((int) $country['id'], $rest);
            if ($page) {
                return $this->renderPage($page);
            }
        }

        $redirect = Redirects::match($this->request->path);
        if ($redirect) {
            $to = $redirect['destination'];
            $qs = $this->request->queryString();
            if ($qs !== '' && !str_contains($to, '?')) {
                $to .= '?' . $qs;
            }
            return Response::redirect($to, $redirect['type'] === 'PERMANENT' ? 301 : 302);
        }
        Redirects::recordNotFound($this->request);
        throw HttpException::notFound();
    }

    /** @param array<string,mixed> $page */
    public function renderPage(array $page, bool $preview = false): Response
    {
        $sections = Content::sections((int) $page['id'], $preview);
        $path = Content::pagePath($page);
        $og = $page['og_image_id'] ? Content::mediaUrl((int) $page['og_image_id']) : null;
        $graph = [];
        if ($page['slug'] !== '') {
            $graph[] = Seo::breadcrumbs([['Home', Countries::href(null, '/')], [$page['title'], $path]]);
        }
        $html = View::render('site/page', [
            'page' => $page,
            'sections' => $sections,
            'graph' => $graph,
            'preview' => $preview,
            'seo' => Seo::meta([
                'title' => $page['seo_title'] ?: ($page['slug'] === '' ? '' : $page['title']),
                'raw_title' => $page['slug'] === '' && !$page['seo_title'],
                'description' => $page['seo_description'],
                'canonical' => $page['canonical_url'] ?: $path,
                'image' => $og,
                'noindex' => (int) $page['no_index'] === 1 || $preview,
                'nofollow' => (int) $page['no_follow'] === 1,
                'og_title' => $page['og_title'],
                'og_description' => $page['og_description'],
            ]),
        ], 'layouts/site');
        return Response::html($html);
    }

    private function isStaff(): bool
    {
        if ($this->request->cookie((string) config('app.session.name')) === null) {
            return false;
        }
        Session::start($this->request);
        return Auth::user() !== null;
    }
}
