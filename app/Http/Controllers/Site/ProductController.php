<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\Response;
use App\Core\View;
use App\Http\Controller;
use App\Services\Content;
use App\Services\Countries;
use App\Services\Forms;
use App\Services\Seo;
use App\Services\Settings;
use App\Builder\Renderer;
use App\Builder\Store;

final class ProductController extends Controller
{
    public function show(array $product, bool $preview = false): Response
    {
        $country = Countries::current();
        $settings = Settings::group('product');
        $form = $product['cta_form_id'] ? Forms::byId((int) $product['cta_form_id']) : null;
        $form ??= Forms::bySlug((string) $settings['default_form_slug']);
        $related = [];
        if ($settings['show_related'] && $product['category_slug']) {
            $related = array_values(array_filter(
                Content::productsForBlock($country, ['source' => 'category', 'category' => $product['category_slug'], 'limit' => 4]),
                static fn ($p) => (int) $p['id'] !== (int) $product['id']
            ));
        }
        $gallery = [];
        foreach ($product['gallery_ids'] as $id) {
            $url = Content::mediaUrl((int) $id);
            if ($url) {
                $gallery[] = $url;
            }
        }
        $pricingPath = Countries::href($country, '/pricing');
        $layout = $this->layoutFor($product, $preview);
        return Response::html(View::render($layout === null ? 'site/product' : 'site/product-builder', [
            'builder' => $layout === null ? null : Renderer::page($layout, ['product' => $product]),
            'preview' => $preview,
            'product' => $product,
            'form' => $form,
            'related' => array_slice($related, 0, 3),
            'gallery' => $gallery,
            'productSettings' => $settings,
            'graph' => [Seo::product($product), Seo::breadcrumbs([['Home', Countries::href(null, '/')], ['Plans', $pricingPath], [$product['name'], $product['url']]])],
            'seo' => Seo::meta([
                'title' => $product['seo_title'] ?: $product['name'],
                'description' => $product['seo_description'] ?: $product['short_description'],
                'canonical' => $product['canonical_url'] ?: $product['url'],
                'image' => $product['og_image_id'] ? Content::mediaUrl((int) $product['og_image_id']) : ($product['image_url'] ?? null),
                'type' => 'product',
                'noindex' => (int) $product['no_index'] === 1 || $preview,
            ]),
        ], 'layouts/site'));
    }

    /**
     * The builder layout for this product page, or null for the classic page.
     * Staff previewing a template pass ?template=ID to see that template.
     */
    private function layoutFor(array $product, bool $preview): ?array
    {
        $templateId = $preview ? (int) $this->request->queryStr('template', '0') : 0;
        if ($templateId > 0) {
            $tpl = \App\Core\DB::first("SELECT layout, draft FROM builder_templates WHERE id = ? AND kind = 'product'", [$templateId]);
            if ($tpl) {
                return \App\Builder\Layout::decode($tpl['draft'] ?? $tpl['layout']);
            }
        }
        return Store::productLayout($product, $preview);
    }
}
