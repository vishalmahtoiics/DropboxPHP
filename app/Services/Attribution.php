<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Response;

/**
 * First-touch and last-touch marketing attribution.
 *
 * On every public page view the UTM parameters and an external referrer are
 * written to two cookies: `attr_first` once, ever (1 year), and `attr_last`
 * whenever a visit carries campaign data (30 days). Both are attached to
 * every lead the visitor submits.
 */
final class Attribution
{
    public const FIRST = 'attr_first';
    public const LAST = 'attr_last';
    public const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public static function capture(Request $request, Response $response): void
    {
        $visit = self::visit($request);
        $first = self::parse($request->cookie(self::FIRST));
        $options = [
            'path' => '/',
            'secure' => $request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if ($first === null) {
            $response->withCookie(self::FIRST, self::encode($visit ?? self::direct($request)), $options + ['expires' => time() + 31536000]);
        }
        if ($visit !== null) {
            $response->withCookie(self::LAST, self::encode($visit), $options + ['expires' => time() + 2592000]);
        }
    }

    /** Campaign data of this request, or null for a direct/internal visit. */
    public static function visit(Request $request): ?array
    {
        $touch = [];
        foreach (self::UTM_KEYS as $key) {
            $value = $request->queryStr($key);
            if ($value !== '') {
                $touch[$key] = mb_substr($value, 0, 150);
            }
        }
        $referrer = $request->referer();
        $external = null;
        if ($referrer) {
            $host = parse_url($referrer, PHP_URL_HOST);
            if (is_string($host) && strcasecmp($host, explode(':', $request->host())[0]) !== 0) {
                $external = mb_substr($referrer, 0, 500);
            }
        }
        if ($touch === [] && $external === null) {
            return null;
        }
        if ($external !== null) {
            $touch['referrer'] = $external;
            $touch['utm_source'] ??= (string) parse_url($external, PHP_URL_HOST);
            $touch['utm_medium'] ??= 'referral';
        }
        $touch['landing'] = mb_substr($request->path, 0, 500);
        $touch['at'] = date('c');
        return $touch;
    }

    private static function direct(Request $request): array
    {
        return ['utm_source' => '(direct)', 'utm_medium' => '(none)', 'landing' => mb_substr($request->path, 0, 500), 'at' => date('c')];
    }

    /** @return array<string,string>|null */
    public static function parse(?string $cookie): ?array
    {
        if (!$cookie) {
            return null;
        }
        $data = json_decode(base64_decode(strtr($cookie, '-_', '+/'), true) ?: '', true);
        if (!is_array($data)) {
            return null;
        }
        $clean = [];
        foreach (array_merge(self::UTM_KEYS, ['referrer', 'landing', 'at']) as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $clean[$key] = mb_substr($data[$key], 0, 500);
            }
        }
        return $clean;
    }

    private static function encode(array $touch): string
    {
        return rtrim(strtr(base64_encode((string) json_encode($touch, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    }

    /**
     * The attribution to store on a lead, from the cookies plus whatever the
     * submitting page itself carries.
     *
     * @return array{first: array<string,string>, last: array<string,string>}
     */
    public static function forLead(Request $request, array $pageQuery = []): array
    {
        $first = self::parse($request->cookie(self::FIRST)) ?? [];
        $last = self::parse($request->cookie(self::LAST)) ?? [];
        $fromPage = [];
        foreach (self::UTM_KEYS as $key) {
            if (isset($pageQuery[$key]) && is_string($pageQuery[$key]) && $pageQuery[$key] !== '') {
                $fromPage[$key] = mb_substr($pageQuery[$key], 0, 150);
            }
        }
        if ($fromPage !== []) {
            $last = $fromPage + ['landing' => $last['landing'] ?? ''];
        }
        if ($first === [] && $last !== []) {
            $first = $last;
        }
        return ['first' => $first, 'last' => $last];
    }
}
