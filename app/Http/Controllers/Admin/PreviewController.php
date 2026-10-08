<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Http\AdminController;
use App\Http\Controllers\Site\BlogController;
use App\Http\Controllers\Site\PageController as SitePageController;
use App\Http\Controllers\Site\ProductController;
use App\Services\Content;
use App\Services\Countries;
use App\Services\SiteContext;

/** Drafts, scheduled and hidden content rendered exactly as the public site would, for staff only. */
final class PreviewController extends AdminController
{
    public function page(string $id): Response
    {
        $this->authorize('pages.view');
        $page = DB::first('SELECT * FROM pages WHERE id = ? AND deleted_at IS NULL', [$this->id($id)]);
        if (!$page) {
            throw HttpException::notFound();
        }
        Countries::assertAccess((int) $page['country_id']);
        SiteContext::boot($this->request, Countries::find((int) $page['country_id']));
        return $this->framed((new SitePageController($this->request))->renderPage($page, true));
    }

    public function post(string $id): Response
    {
        $this->authorize('blog.view');
        $post = DB::first(
            'SELECT bp.*, bc.name AS category_name, bc.slug AS category_slug, u.name AS author_name, u.job_title AS author_title,
                    u.bio AS author_bio, u.avatar_url AS author_avatar, m.url AS image_url, m.alt_text AS image_alt
               FROM blog_posts bp LEFT JOIN blog_categories bc ON bc.id = bp.category_id LEFT JOIN users u ON u.id = bp.author_id
          LEFT JOIN media m ON m.id = bp.featured_image_id WHERE bp.id = ? AND bp.deleted_at IS NULL',
            [$this->id($id)]
        );
        if (!$post) {
            throw HttpException::notFound();
        }
        Countries::assertAccess((int) $post['country_id']);
        SiteContext::boot($this->request, Countries::find((int) $post['country_id']));
        \App\Core\View::share('preview', true);
        return $this->framed((new BlogController($this->request))->post($post, true));
    }

    public function product(string $id): Response
    {
        $this->authorize('products.view');
        $country = Countries::adminCurrent();
        $row = DB::first(
            'SELECT p.*, pc.currency AS m_currency, pc.monthly_price AS m_monthly, pc.annual_price AS m_annual, pc.compare_at_price AS m_compare,
                    pc.discount_percent AS m_discount, pc.price_suffix AS m_suffix, pc.price_note AS m_note, pc.short_description AS m_short,
                    pc.cta_label AS m_cta_label, pc.cta_url AS m_cta_url, pc.cta_form_id AS m_cta_form, pc.is_featured AS m_featured,
                    pc.seo_title AS m_seo_title, pc.seo_description AS m_seo_desc, pc.no_index AS m_noindex,
                    c.name AS category_name, c.slug AS category_slug, b.name AS brand_name, b.slug AS brand_slug, m.url AS image_url, m.alt_text AS image_alt
               FROM products p LEFT JOIN product_countries pc ON pc.product_id = p.id AND pc.country_id = ?
          LEFT JOIN product_categories c ON c.id = p.category_id LEFT JOIN brands b ON b.id = p.brand_id LEFT JOIN media m ON m.id = p.image_id
              WHERE p.id = ? AND p.deleted_at IS NULL',
            [(int) $country['id'], $this->id($id)]
        );
        if (!$row) {
            throw HttpException::notFound();
        }
        SiteContext::boot($this->request, $country);
        \App\Core\View::share('preview', true);
        return $this->framed((new ProductController($this->request))->show(Content::hydrateProduct($row, $country), true));
    }

    /** Previews may be shown in the editor's iframe, never indexed, never cached. */
    private function framed(Response $r): Response
    {
        return $r->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Content-Security-Policy', "frame-ancestors 'self'; default-src 'self'; script-src 'self' 'nonce-" . nonce() . "'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: https:; frame-src https://www.youtube-nocookie.com https://player.vimeo.com; form-action 'self'; base-uri 'self'")
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store');
    }
}
