<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Metadata and structured data.
 *
 * Resolution order for every value: the entity's own field → the market's
 * SEO defaults → the global SEO settings → a built-in fallback. JSON-LD is
 * built from arrays and encoded, never string-concatenated.
 */
final class Seo
{
    /**
     * @param array<string,mixed> $in title, description, canonical (path), image, type,
     *        noindex, nofollow, og_title, og_description, published, modified
     * @return array<string,mixed>
     */
    public static function meta(array $in): array
    {
        $seo = Settings::group('seo');
        $country = Countries::current();
        $template = Brand::text((string) (Countries::setting($country, 'title_template') ?: $seo['title_template'] ?: '%s'));
        $defaultTitle = Brand::text((string) (Countries::setting($country, 'default_title') ?: $seo['default_title']));
        $defaultDesc = Brand::text((string) (Countries::setting($country, 'default_description') ?: $seo['default_description']));

        $raw = Brand::text(trim((string) ($in['title'] ?? '')));
        $in['description'] = Brand::text((string) ($in['description'] ?? ''));
        $title = $raw === '' ? $defaultTitle : (!empty($in['raw_title']) ? $raw : str_replace('%s', $raw, $template));
        $desc = trim((string) ($in['description'] ?? '')) ?: $defaultDesc;
        $desc = mb_substr(preg_replace('/\s+/', ' ', strip_tags($desc)) ?? '', 0, 300);

        $canonical = (string) ($in['canonical'] ?? '');
        if ($canonical === '' && isset($in['path'])) {
            $canonical = (string) $in['path'];
        }
        $canonical = $canonical === '' ? '' : absolute_url($canonical);

        $noindex = !empty($in['noindex']) || $seo['noindex_site'] || (bool) Countries::setting($country, 'noindex', false);
        $robots = ($noindex ? 'noindex' : 'index') . ', ' . (!empty($in['nofollow']) ? 'nofollow' : 'follow');
        if (!$noindex) {
            $robots .= ', max-image-preview:large';
        }
        $image = (string) ($in['image'] ?? '') ?: (string) $seo['default_og_image'];

        return [
            'title' => $title,
            'description' => $desc,
            'canonical' => $canonical,
            'robots' => $robots,
            'og_type' => (string) ($in['type'] ?? 'website'),
            'og_title' => (string) ($in['og_title'] ?? '') ?: ($raw ?: $title),
            'og_description' => (string) ($in['og_description'] ?? '') ?: $desc,
            'image' => $image === '' ? '' : absolute_url($image),
            'twitter_handle' => (string) $seo['twitter_handle'],
            'published' => $in['published'] ?? null,
            'modified' => $in['modified'] ?? null,
            'google' => (string) $seo['google_verification'],
            'bing' => (string) $seo['bing_verification'],
            'locale' => str_replace('-', '_', (string) ($country['locale'] ?? 'en')),
            'alternates' => $in['alternates'] ?? [],
        ];
    }

    /** @return list<array<string,mixed>> site-wide JSON-LD */
    public static function siteGraph(): array
    {
        $seo = Settings::group('seo');
        $site = Settings::group('website');
        $country = Countries::current();
        $org = [
            '@context' => 'https://schema.org',
            '@type' => Countries::setting($country, 'organization_type') ?: ($seo['organization_type'] ?: 'Organization'),
            '@id' => absolute_url('/#organization'),
            'name' => Brand::text((string) (Countries::setting($country, 'company_name') ?: ($seo['organization_name'] ?: $site['site_name']))),
            'url' => absolute_url(Countries::href($country, '/')),
        ];
        $logo = $seo['organization_logo'] ?: $site['logo_url'];
        if ($logo) {
            $org['logo'] = absolute_url($logo);
        }
        $same = array_values(array_filter([$site['linkedin_url'], $site['twitter_url'], $site['facebook_url'], $site['instagram_url'], $site['youtube_url']]));
        if ($same) {
            $org['sameAs'] = $same;
        }
        $phone = Countries::setting($country, 'sales_phone') ?: $site['contact_phone'];
        $email = Countries::setting($country, 'sales_email') ?: $site['contact_email'];
        if ($phone || $email) {
            $org['contactPoint'] = array_filter(['@type' => 'ContactPoint', 'contactType' => 'sales', 'telephone' => $phone, 'email' => $email]);
        }
        $address = Countries::setting($country, 'address');
        if ($address) {
            $org['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $address, 'addressCountry' => $country['code'] ?? ''];
        }
        $website = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => absolute_url('/#website'),
            'name' => $site['site_name'],
            'url' => absolute_url('/'),
            'publisher' => ['@id' => absolute_url('/#organization')],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => absolute_url(Content::blogBase($country) . '?q={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ],
        ];
        return [$org, $website];
    }

    /** @param list<array{0:string,1:string}> $crumbs [name, path] */
    public static function breadcrumbs(array $crumbs): array
    {
        $items = [];
        foreach ($crumbs as $i => [$name, $path]) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => absolute_url($path)];
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    public static function product(array $p): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $p['name'],
            'description' => strip_tags((string) ($p['short_description'] ?: $p['description'])),
            'url' => absolute_url($p['url']),
            'sku' => $p['sku'] ?: $p['slug'],
        ];
        if (!empty($p['image_url'])) {
            $data['image'] = absolute_url($p['image_url']);
        }
        if (!empty($p['brand_name'])) {
            $data['brand'] = ['@type' => 'Brand', 'name' => $p['brand_name']];
        }
        $price = $p['annual_price'] ?? $p['monthly_price'];
        if ($price !== null && $price !== '') {
            $data['offers'] = [
                '@type' => 'Offer',
                'price' => (string) $price,
                'priceCurrency' => $p['currency'],
                'availability' => 'https://schema.org/InStock',
                'url' => absolute_url($p['url']),
                'seller' => ['@id' => absolute_url('/#organization')],
            ];
        }
        return $data;
    }

    public static function article(array $post, string $url): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => mb_substr((string) $post['title'], 0, 110),
            'description' => strip_tags((string) ($post['seo_description'] ?: $post['excerpt'])),
            'mainEntityOfPage' => absolute_url($url),
            'datePublished' => date('c', strtotime((string) ($post['published_at'] ?: $post['created_at']))),
            'dateModified' => date('c', strtotime((string) $post['updated_at'])),
            'author' => ['@type' => 'Person', 'name' => $post['author_name'] ?: Settings::get('website', 'site_name')],
            'publisher' => ['@id' => absolute_url('/#organization')],
        ];
        if (!empty($post['image_url'])) {
            $data['image'] = absolute_url($post['image_url']);
        }
        return $data;
    }

    /** @param list<array{question:string, answer:string}> $items */
    public static function faq(array $items): ?array
    {
        $entities = [];
        foreach ($items as $item) {
            if (trim((string) ($item['question'] ?? '')) === '' || trim(strip_tags((string) ($item['answer'] ?? ''))) === '') {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string) $item['answer'], '<p><br><ul><ol><li><a><strong><em>')],
            ];
        }
        return $entities ? ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities] : null;
    }

    public static function jsonLd(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        return '<script type="application/ld+json" nonce="' . e(nonce()) . '">' . $json . '</script>';
    }
}
