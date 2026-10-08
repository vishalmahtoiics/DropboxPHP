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
        return Response::html(View::render('site/product', [
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
}
