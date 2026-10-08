<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Sanitizer;
use Throwable;

/**
 * Typed settings groups, stored one JSON row per group.
 *
 * Every key has a default and a type here, so a missing or stale row can
 * never produce an undefined value, and saving keeps only known keys, each
 * coerced to its type. Colours and CSS lengths are validated on save because
 * they are printed into a stylesheet.
 */
final class Settings
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /**
     * Types: s = text, t = long text, b = bool, i = int, c = colour, l = css length,
     * u = url/path, e = email, x = secret (encrypted, never sent to the browser).
     */
    public const SCHEMA = [
        'website' => [
            'site_name' => ['s', 'Dropbox Reseller'],
            'tagline' => ['s', 'Authorised Dropbox Partner'],
            'contact_email' => ['e', ''],
            'contact_phone' => ['s', ''],
            'whatsapp_number' => ['s', ''],
            'address' => ['t', ''],
            'linkedin_url' => ['u', ''],
            'twitter_url' => ['u', ''],
            'facebook_url' => ['u', ''],
            'instagram_url' => ['u', ''],
            'youtube_url' => ['u', ''],
            'logo_url' => ['u', ''],
            'logo_dark_url' => ['u', ''],
            'favicon_url' => ['u', ''],
            'heading_font' => ['s', 'Inter'],
            'body_font' => ['s', 'Inter'],
            'heading_weight' => ['s', '700'],
            'body_weight' => ['s', '400'],
            'base_font_size' => ['l', '16px'],
            'heading_line_height' => ['s', '1.15'],
            'body_line_height' => ['s', '1.6'],
            'color_primary' => ['c', '#0061FF'],
            'color_secondary' => ['c', '#0B1B34'],
            'color_accent1' => ['c', '#1AC1A5'],
            'color_accent2' => ['c', '#FF8A3D'],
            'color_background' => ['c', '#FFFFFF'],
            'color_text' => ['c', '#0B1B34'],
            'color_muted' => ['c', '#5B6B85'],
            'color_border' => ['c', '#E3E8F0'],
            'container_width' => ['l', '72rem'],
            'section_spacing' => ['l', '5rem'],
            'section_spacing_mobile' => ['l', '3rem'],
            'border_radius' => ['l', '0.75rem'],
            'card_radius' => ['l', '1rem'],
            'button_radius' => ['l', '0.5rem'],
            'announcement_enabled' => ['b', false],
            'announcement_text' => ['s', ''],
            'announcement_url' => ['u', ''],
            'header_cta_label' => ['s', 'Talk to sales'],
            'header_cta_url' => ['u', '/contact'],
            'header_secondary_cta_label' => ['s', ''],
            'header_secondary_cta_url' => ['u', ''],
            'header_sticky' => ['b', true],
            'header_glass' => ['b', true],
            'header_show_markets' => ['b', true],
            'maintenance_mode' => ['b', false],
            'maintenance_message' => ['t', 'We are making a few improvements and will be back shortly.'],
            'trusted_proxies' => ['s', ''],
        ],
        'footer' => [
            'about' => ['t', 'Authorised Dropbox reseller helping teams buy, deploy and manage Dropbox.'],
            'copyright' => ['s', '© {year} {site}. All rights reserved.'],
            'show_social' => ['b', true],
            'show_contact' => ['b', true],
            'show_newsletter' => ['b', false],
            'newsletter_form_slug' => ['s', ''],
            'background' => ['c', '#0B1B34'],
            'text_color' => ['c', '#C9D3E3'],
        ],
        'seo' => [
            'default_title' => ['s', 'Authorised Dropbox Reseller'],
            'title_template' => ['s', '%s | Dropbox Reseller'],
            'default_description' => ['t', 'Buy Dropbox Business, Standard, Advanced and Enterprise plans from an authorised partner, with local billing and support.'],
            'default_og_image' => ['u', ''],
            'twitter_handle' => ['s', ''],
            'organization_name' => ['s', 'Dropbox Reseller'],
            'organization_logo' => ['u', ''],
            'organization_type' => ['s', 'Organization'],
            'google_verification' => ['s', ''],
            'bing_verification' => ['s', ''],
            'robots_extra' => ['t', ''],
            'sitemap_enabled' => ['b', true],
            'noindex_site' => ['b', false],
        ],
        'tracking' => [
            'ga4_id' => ['s', ''], 'ga4_enabled' => ['b', false],
            'gtm_id' => ['s', ''], 'gtm_enabled' => ['b', false],
            'google_ads_id' => ['s', ''], 'google_ads_enabled' => ['b', false], 'google_ads_label' => ['s', ''],
            'meta_pixel_id' => ['s', ''], 'meta_pixel_enabled' => ['b', false],
            'microsoft_uet_id' => ['s', ''], 'microsoft_uet_enabled' => ['b', false],
            'hotjar_id' => ['s', ''], 'hotjar_enabled' => ['b', false],
            'linkedin_partner_id' => ['s', ''], 'linkedin_enabled' => ['b', false],
            'tiktok_pixel_id' => ['s', ''], 'tiktok_enabled' => ['b', false],
            'consent_required' => ['b', true],
            'consent_message' => ['t', 'We use cookies to measure how the site is used and to improve our marketing. You can accept or decline analytics cookies.'],
        ],
        'email' => [
            'is_enabled' => ['b', false],
            'host' => ['s', ''],
            'port' => ['i', 587],
            'username' => ['s', ''],
            'password' => ['x', ''],
            'encryption' => ['s', 'tls'],
            'from_name' => ['s', 'Dropbox Reseller'],
            'from_email' => ['e', ''],
            'reply_to' => ['e', ''],
            'notify_new_lead' => ['b', true],
            'notify_assignment' => ['b', true],
            'notify_submission' => ['b', false],
            'send_confirmation' => ['b', false],
            'sales_emails' => ['t', ''],
        ],
        'blog' => [
            'title' => ['s', 'Blog'],
            'intro' => ['t', 'News, guides and best practice for teams using Dropbox.'],
            'base_path' => ['s', 'blog'],
            'posts_per_page' => ['i', 9],
            'show_author' => ['b', true],
            'show_reading_time' => ['b', true],
            'show_share' => ['b', true],
            'show_toc' => ['b', true],
            'show_related' => ['b', true],
            'sidebar' => ['b', true],
            'cta_heading' => ['s', 'Need help choosing a Dropbox plan?'],
            'cta_text' => ['t', 'Talk to our team for pricing, migration and deployment support.'],
            'cta_label' => ['s', 'Talk to sales'],
            'cta_url' => ['u', '/contact'],
        ],
        'backup' => [
            'enabled' => ['b', false],
            'frequency' => ['s', 'daily'],
            'hour' => ['i', 2],
            'retention' => ['i', 7],
            'include_media' => ['b', true],
        ],
        'storage' => [
            'driver' => ['s', 'local'],
            'bucket' => ['s', ''],
            'region' => ['s', ''],
            'endpoint' => ['u', ''],
            'access_key' => ['s', ''],
            'secret_key' => ['x', ''],
            'public_url' => ['u', ''],
            'path_style' => ['s', 'auto'],
        ],
        'product' => [
            'base_path' => ['s', 'products'],
            'show_compare_price' => ['b', true],
            'show_features' => ['b', true],
            'show_specs' => ['b', true],
            'show_benefits' => ['b', true],
            'benefits_heading' => ['s', 'Top benefits of {product}'],
            'show_related' => ['b', true],
            'default_form_slug' => ['s', ''],
        ],
    ];

    /** @return array<string, mixed> */
    public static function group(string $group): array
    {
        if (isset(self::$cache[$group])) {
            return self::$cache[$group];
        }
        $schema = self::SCHEMA[$group] ?? [];
        $values = array_map(static fn ($def) => $def[1], $schema);
        try {
            $raw = DB::value('SELECT data FROM settings WHERE `group` = ?', [$group]);
            $stored = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($stored)) {
                foreach ($schema as $key => [$type]) {
                    if (array_key_exists($key, $stored)) {
                        $values[$key] = self::cast($type, $stored[$key], $schema[$key][1]);
                    }
                }
            }
        } catch (Throwable) {
            // No database yet (installer, build): defaults keep the site rendering.
        }
        return self::$cache[$group] = $values;
    }

    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        return self::group($group)[$key] ?? $default;
    }

    /**
     * Save the known keys of a group from user input. Secrets left blank keep
     * their stored value.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed> the stored values
     */
    public static function save(string $group, array $input, bool $partial = false): array
    {
        $schema = self::SCHEMA[$group] ?? null;
        if ($schema === null) {
            throw new \InvalidArgumentException('Unknown settings group.');
        }
        $current = self::raw($group);
        $out = [];
        foreach ($schema as $key => [$type, $default]) {
            if (!array_key_exists($key, $input)) {
                if ($partial || $type === 'x') {
                    $out[$key] = $current[$key] ?? $default;
                } else {
                    $out[$key] = $type === 'b' ? false : ($current[$key] ?? $default);
                }
                continue;
            }
            $value = $input[$key];
            if ($type === 'x') {
                $value = is_string($value) ? trim($value) : '';
                $out[$key] = $value === '' ? ($current[$key] ?? '') : \App\Core\Crypto::encrypt($value, 'settings');
                continue;
            }
            $out[$key] = self::cast($type, $value, $default);
        }
        DB::upsert('settings', ['group' => $group, 'data' => $out], ['data']);
        unset(self::$cache[$group]);
        return $out;
    }

    /** Decrypted secret value. */
    public static function secret(string $group, string $key): string
    {
        $stored = self::raw($group)[$key] ?? '';
        return is_string($stored) && $stored !== '' ? (string) \App\Core\Crypto::decrypt($stored, 'settings') : '';
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    /** @return array<string, mixed> */
    private static function raw(string $group): array
    {
        $raw = DB::value('SELECT data FROM settings WHERE `group` = ?', [$group]);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($data) ? $data : [];
    }

    private static function cast(string $type, mixed $value, mixed $default): mixed
    {
        return match ($type) {
            'b' => in_array($value, [true, 1, '1', 'on', 'true', 'yes'], true),
            'i' => is_numeric($value) ? (int) $value : $default,
            'c' => ($c = Sanitizer::color(is_string($value) ? $value : '')) !== '' ? $c : ($value === '' ? '' : $default),
            'l' => ($l = Sanitizer::length(is_string($value) ? $value : '')) !== '' ? $l : ($value === '' ? '' : $default),
            'u' => Sanitizer::url(is_string($value) ? $value : ''),
            'e' => is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL) ? strtolower(trim($value)) : '',
            't' => Sanitizer::text(is_scalar($value) ? (string) $value : '', 5000),
            'x' => is_string($value) ? $value : '',
            default => Sanitizer::line(is_scalar($value) ? (string) $value : '', 500),
        };
    }
}
