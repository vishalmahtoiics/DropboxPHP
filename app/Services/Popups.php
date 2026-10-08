<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use Throwable;

/**
 * Popups for a public request: active, within their date window, for this
 * market (or all markets) and matching the page's path patterns. Device,
 * trigger and frequency are applied in the browser.
 *
 * Patterns: "/" (homepage only), "/pricing", "/blog/*" (prefix), "*" (all).
 * An empty list means every page. A popup with the CLICK trigger is always
 * printed so any button linking to #popup-ID can open it.
 */
final class Popups
{
    /** @return list<array<string,mixed>> */
    public static function forPath(string $path, int $countryId): array
    {
        try {
            $rows = DB::all(
                'SELECT p.*, m.url AS image_url FROM popups p LEFT JOIN media m ON m.id = p.image_id
                  WHERE p.is_active = 1 AND p.deleted_at IS NULL AND (p.country_id IS NULL OR p.country_id = ?)
                    AND (p.starts_at IS NULL OR p.starts_at <= NOW()) AND (p.ends_at IS NULL OR p.ends_at >= NOW())
                  ORDER BY p.id',
                [$countryId]
            );
        } catch (Throwable) {
            return [];
        }
        $country = Countries::find($countryId);
        $local = '/' . ltrim((string) preg_replace('#^/' . preg_quote((string) ($country['slug'] ?? ''), '#') . '(?=/|$)#', '', $path), '/');
        if (($country['slug'] ?? '') === '') {
            $local = $path;
        }
        return array_values(array_filter($rows, static function (array $p) use ($local): bool {
            if ($p['trigger'] === 'CLICK') {
                return true;
            }
            $patterns = json_decode((string) ($p['url_patterns'] ?? '[]'), true) ?: [];
            return $patterns === [] || self::matches($patterns, $local);
        }));
    }

    /** @param list<string> $patterns */
    public static function matches(array $patterns, string $path): bool
    {
        $path = '/' . trim($path, '/');
        foreach ($patterns as $pattern) {
            $pattern = '/' . trim((string) $pattern, '/');
            if ($pattern === '/*' || $pattern === $path) {
                return true;
            }
            if (str_ends_with($pattern, '/*') && str_starts_with($path . '/', substr($pattern, 0, -1))) {
                return true;
            }
        }
        return false;
    }
}
