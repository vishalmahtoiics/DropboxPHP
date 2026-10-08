<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\View;

/**
 * Everything the public layout needs, resolved once per request: the market,
 * the brand, menus, footer, tracking and consent state.
 */
final class SiteContext
{
    private static bool $booted = false;

    public static function reset(): void
    {
        self::$booted = false;
    }

    /** @return string the path inside the market ("ae/pricing" → "pricing") */
    public static function boot(Request $request, ?array $country = null): string
    {
        $rest = '';
        if ($country === null) {
            [$country, $rest] = Countries::resolve($request->path);
        }
        Countries::setCurrent($country);
        if (self::$booted) {
            return $rest;
        }
        self::$booted = true;
        $cid = (int) $country['id'];
        View::share('site', Settings::group('website'));
        View::share('footerSettings', Settings::group('footer'));
        View::share('country', $country);
        View::share('markets', Countries::published());
        View::share('headerNav', Navigation::tree($cid, 'HEADER'));
        View::share('footerNav', Navigation::tree($cid, 'FOOTER'));
        View::share('footerSecondaryNav', Navigation::tree($cid, 'FOOTER_SECONDARY'));
        View::share('legalNav', Navigation::tree($cid, 'LEGAL'));
        $mobile = Navigation::tree($cid, 'MOBILE');
        View::share('mobileNav', $mobile ?: null);
        View::share('tracking', Settings::group('tracking'));
        View::share('consent', $request->cookie('consent'));
        View::share('requestPath', $request->path);
        View::share('requestQuery', $request->query);
        return $rest;
    }
}
