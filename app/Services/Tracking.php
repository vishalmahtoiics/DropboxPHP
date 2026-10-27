<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use Throwable;

/**
 * Marketing tags. Each vendor is emitted from a fixed template with only a
 * format-validated ID interpolated, so the marketing screen cannot be used to
 * inject arbitrary JavaScript. Custom scripts are a separate, explicitly
 * privileged and audited feature.
 *
 * With consent required, every tag is printed inert (type="text/plain") and
 * only activated by site.js after the visitor accepts. Google Tag Manager is the
 * exception: it is printed live, in Google's standard form at the top of <head>
 * (with its <noscript> fallback after <body>), behind Google Consent Mode
 * defaults that deny storage until the visitor accepts. That keeps consent and
 * lets tools that look for the container (Search Console verification) find it.
 */
final class Tracking
{
    public const ID_PATTERNS = [
        'ga4_id' => '/^G-[A-Z0-9]{4,15}$/',
        'gtm_id' => '/^GTM-[A-Z0-9]{4,10}$/',
        'google_ads_id' => '/^AW-\d{6,15}$/',
        'meta_pixel_id' => '/^\d{8,20}$/',
        'microsoft_uet_id' => '/^\d{5,15}$/',
        'hotjar_id' => '/^\d{5,12}$/',
        'linkedin_partner_id' => '/^\d{4,12}$/',
        'tiktok_pixel_id' => '/^[A-Z0-9]{10,30}$/',
    ];

    public static function valid(string $key, string $value): bool
    {
        return isset(self::ID_PATTERNS[$key]) && preg_match(self::ID_PATTERNS[$key], $value) === 1;
    }

    private static function allowed(array $t, ?string $consent): bool
    {
        return empty($t['consent_required']) || $consent === 'granted';
    }

    private static function script(string $js, bool $active, string $src = ''): string
    {
        $type = $active ? '' : ' type="text/plain" data-consent="analytics"';
        $srcAttr = $src !== '' ? ' src="' . e($src) . '" async' : '';
        return '<script' . $type . ' nonce="' . e(nonce()) . '"' . $srcAttr . '>' . $js . '</script>';
    }

    private static function gtmId(array $t): string
    {
        $id = (string) ($t['gtm_id'] ?? '');
        return !empty($t['gtm_enabled']) && self::valid('gtm_id', $id) ? $id : '';
    }

    /** Printed first in <head>: Consent Mode defaults, then the Tag Manager container. */
    public static function headTop(array $t, ?string $consent): string
    {
        $gtm = self::gtmId($t);
        if ($gtm === '') {
            return '';
        }
        $state = self::allowed($t, $consent) ? 'granted' : 'denied';
        $consentJs = 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
            . "gtag('consent','default',{ad_storage:'$state',ad_user_data:'$state',ad_personalization:'$state',analytics_storage:'$state'"
            . ($state === 'denied' ? ',wait_for_update:500' : '') . '});';
        // Google's container snippet, unchanged apart from the CSP nonce ('strict-dynamic' lets it load gtm.js).
        return self::script($consentJs, true) . "\n<!-- Google Tag Manager -->\n"
            . self::script("(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\nnew Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\nj=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n})(window,document,'script','dataLayer','" . $gtm . "');", true)
            . "\n<!-- End Google Tag Manager -->\n";
    }

    public static function head(array $t, ?string $consent): string
    {
        $on = self::allowed($t, $consent);
        $out = '';
        $id = static fn (string $k) => self::valid($k, (string) ($t[$k] ?? '')) ? (string) $t[$k] : '';

        $gtagIds = array_values(array_filter([$t['ga4_enabled'] ? $id('ga4_id') : '', $t['google_ads_enabled'] ? $id('google_ads_id') : '']));
        if ($gtagIds) {
            $out .= self::script('', $on, 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($gtagIds[0]));
            $cfg = 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());';
            foreach ($gtagIds as $gid) {
                $cfg .= 'gtag("config",' . js($gid) . ');';
            }
            $out .= self::script($cfg, $on);
        }
        if ($t['meta_pixel_enabled'] && ($px = $id('meta_pixel_id'))) {
            $out .= self::script("!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init'," . js($px) . ");fbq('track','PageView');", $on);
        }
        if ($t['microsoft_uet_enabled'] && ($uet = $id('microsoft_uet_id'))) {
            $out .= self::script('(function(w,d,t,r,u){var f,n,i;w[u]=w[u]||[],f=function(){var o={ti:' . js($uet) . ',enableAutoSpaTracking:true};o.q=w[u],w[u]=new UET(o),w[u].push("pageLoad")},n=d.createElement(t),n.src=r,n.async=1,n.onload=n.onreadystatechange=function(){var s=this.readyState;s&&s!=="loaded"&&s!=="complete"||(f(),n.onload=n.onreadystatechange=null)},i=d.getElementsByTagName(t)[0],i.parentNode.insertBefore(n,i)})(window,document,"script","//bat.bing.com/bat.js","uetq");', $on);
        }
        if ($t['hotjar_enabled'] && ($hj = $id('hotjar_id'))) {
            $out .= self::script("(function(h,o,t,j,a,r){h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};h._hjSettings={hjid:" . (int) $hj . ",hjsv:6};a=o.getElementsByTagName('head')[0];r=o.createElement('script');r.async=1;r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;a.appendChild(r);})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');", $on);
        }
        if ($t['linkedin_enabled'] && ($li = $id('linkedin_partner_id'))) {
            $out .= self::script('_linkedin_partner_id=' . js($li) . ';window._linkedin_data_partner_ids=window._linkedin_data_partner_ids||[];window._linkedin_data_partner_ids.push(_linkedin_partner_id);(function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}var s=document.getElementsByTagName("script")[0];var b=document.createElement("script");b.type="text/javascript";b.async=true;b.src="https://snap.licdn.com/li.lms-analytics/insight.min.js";s.parentNode.insertBefore(b,s);})(window.lintrk);', $on);
        }
        if ($t['tiktok_enabled'] && ($tt = $id('tiktok_pixel_id'))) {
            $out .= self::script('!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load(' . js($tt) . ');ttq.page();}(window,document,"ttq");', $on);
        }
        return $out . self::custom('HEAD', $t, $consent);
    }

    public static function bodyStart(array $t, ?string $consent): string
    {
        $gtm = self::gtmId($t);
        $noscript = $gtm === '' ? '' : "<!-- Google Tag Manager (noscript) -->\n<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id=" . $gtm
            . "\"\nheight=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n<!-- End Google Tag Manager (noscript) -->\n";
        return $noscript . self::custom('BODY_START', $t, $consent);
    }

    public static function bodyEnd(array $t, ?string $consent): string
    {
        return self::custom('BODY_END', $t, $consent);
    }

    /** Admin-authored scripts (marketing.manage, audited). Each <script> gets the CSP nonce. */
    private static function custom(string $placement, array $t, ?string $consent): string
    {
        try {
            $env = config('app.env') === 'production' ? 'PRODUCTION' : 'DEVELOPMENT';
            $rows = DB::all(
                "SELECT code, requires_consent FROM tracking_scripts WHERE is_active = 1 AND placement = ? AND environment IN ('ALL', ?) ORDER BY id",
                [$placement, $env]
            );
        } catch (Throwable) {
            return '';
        }
        $out = '';
        foreach ($rows as $row) {
            $active = !(int) $row['requires_consent'] || self::allowed(['consent_required' => true], $consent) || empty($t['consent_required']);
            $code = (string) $row['code'];
            $code = preg_replace_callback('/<script\b([^>]*)>/i', static function (array $m) use ($active): string {
                $attrs = preg_replace('/\snonce="[^"]*"/i', '', $m[1]) ?? '';
                if (!$active) {
                    $attrs = preg_replace('/\stype="[^"]*"/i', '', $attrs) . ' type="text/plain" data-consent="analytics"';
                }
                return '<script' . $attrs . ' nonce="' . e(nonce()) . '">';
            }, $code) ?? '';
            $out .= $code . "\n";
        }
        return $out;
    }
}
