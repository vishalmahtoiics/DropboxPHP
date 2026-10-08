<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Session;
use Throwable;

/**
 * Markets. Exactly one country is the default and is served from "/"; every
 * other active country owns a URL prefix ("/ae"). Adding a market is a row in
 * `countries` plus its content — routing reads this table, nothing else.
 */
final class Countries
{
    /** @var list<array<string,mixed>>|null */
    private static ?array $all = null;
    private static ?array $current = null;

    /** Prefixes that can never be a market because a system route owns them. */
    public const RESERVED = ['admin', 'api', 'auth', 'media', 'uploads', 'assets', 'preview', 'forms', 'cron',
        'sitemap.xml', 'sitemaps', 'robots.txt', 'health', 'ready', 'favicon.ico', 'search', 'consent'];

    public static function reset(): void
    {
        self::$all = null;
        self::$current = null;
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        if (self::$all === null) {
            try {
                self::$all = array_map([self::class, 'hydrate'], DB::all('SELECT * FROM countries ORDER BY is_default DESC, sort_order, name'));
            } catch (Throwable) {
                self::$all = [];
            }
        }
        return self::$all;
    }

    /** @return list<array<string,mixed>> */
    public static function active(): array
    {
        return array_values(array_filter(self::all(), static fn ($c) => (int) $c['is_active'] === 1));
    }

    /** @return list<array<string,mixed>> markets visitors can switch to */
    public static function published(): array
    {
        return array_values(array_filter(self::active(), static fn ($c) => (int) $c['is_published'] === 1));
    }

    public static function default(): array
    {
        foreach (self::all() as $country) {
            if ((int) $country['is_default'] === 1) {
                return $country;
            }
        }
        return self::all()[0] ?? self::fallback();
    }

    public static function find(int $id): ?array
    {
        foreach (self::all() as $country) {
            if ((int) $country['id'] === $id) {
                return $country;
            }
        }
        return null;
    }

    public static function bySlug(string $slug): ?array
    {
        foreach (self::active() as $country) {
            if ($country['slug'] === $slug && (int) $country['is_default'] === 0) {
                return $country;
            }
        }
        return null;
    }

    /**
     * Split a public path into its market and the remainder.
     * "/ae/pricing" → [UAE, "pricing"]; "/pricing" → [default, "pricing"].
     *
     * @return array{0: array<string,mixed>, 1: string}
     */
    public static function resolve(string $path): array
    {
        $trimmed = trim($path, '/');
        $first = explode('/', $trimmed)[0];
        if ($first !== '' && ($country = self::bySlug($first)) !== null) {
            return [$country, trim(substr($trimmed, strlen($first)), '/')];
        }
        return [self::default(), $trimmed];
    }

    public static function setCurrent(array $country): void
    {
        self::$current = $country;
    }

    /** The market of the current public request. */
    public static function current(): array
    {
        return self::$current ?? self::default();
    }

    /** A path in a market: href(UAE, "/pricing") → "/ae/pricing". */
    public static function href(?array $country, string $path = '/'): string
    {
        $path = '/' . ltrim($path, '/');
        if (preg_match('#^(https?:|mailto:|tel:|\#)#i', ltrim($path, '/'))) {
            return ltrim($path, '/');
        }
        $country ??= self::current();
        if ((int) ($country['is_default'] ?? 1) === 1 || ($country['slug'] ?? '') === '') {
            return $path;
        }
        return '/' . $country['slug'] . ($path === '/' ? '' : $path);
    }

    /** The settings JSON of a market (company details, SEO defaults …). */
    public static function setting(?array $country, string $key, mixed $default = null): mixed
    {
        $settings = $country['settings'] ?? [];
        $value = is_array($settings) ? ($settings[$key] ?? null) : null;
        return $value === null || $value === '' ? $default : $value;
    }

    // --- admin -------------------------------------------------------------

    /** @return list<int> ids of markets the signed-in user may work in */
    public static function accessibleIds(?array $user = null): array
    {
        $user ??= Auth::user();
        if (!$user) {
            return [];
        }
        $ids = array_map(static fn ($c) => (int) $c['id'], self::all());
        if ((int) $user['all_countries'] === 1 || $user['role_slug'] === 'super-admin') {
            return $ids;
        }
        $allowed = array_map('intval', DB::column('SELECT country_id FROM user_countries WHERE user_id = ?', [$user['id']]));
        return array_values(array_intersect($ids, $allowed));
    }

    /** @return list<array<string,mixed>> */
    public static function accessible(): array
    {
        $ids = self::accessibleIds();
        return array_values(array_filter(self::all(), static fn ($c) => in_array((int) $c['id'], $ids, true)));
    }

    /** The market the admin is currently working in. */
    public static function adminCurrent(): array
    {
        $ids = self::accessibleIds();
        $selected = (int) Session::get('admin_country_id', 0);
        if ($selected && in_array($selected, $ids, true) && ($c = self::find($selected))) {
            return $c;
        }
        $default = self::default();
        if (in_array((int) ($default['id'] ?? 0), $ids, true)) {
            return $default;
        }
        return $ids ? (self::find($ids[0]) ?? $default) : $default;
    }

    /** Refuse a write to a market the user cannot work in. A form body is never trusted alone. */
    public static function assertAccess(int $countryId): void
    {
        if (!in_array($countryId, self::accessibleIds(), true)) {
            throw HttpException::forbidden();
        }
    }

    /** @param array<string,mixed> $row */
    private static function hydrate(array $row): array
    {
        $row['settings'] = is_string($row['settings'] ?? null) ? (json_decode($row['settings'], true) ?: []) : [];
        return $row;
    }

    private static function fallback(): array
    {
        return [
            'id' => 0, 'name' => 'India', 'code' => 'IN', 'slug' => '', 'locale' => 'en-IN', 'currency' => 'INR',
            'currency_symbol' => '₹', 'is_default' => 1, 'is_active' => 1, 'is_published' => 1, 'settings' => [],
        ];
    }
}
