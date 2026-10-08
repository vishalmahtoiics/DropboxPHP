<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Response security headers and the per-request CSP nonce.
 */
final class Security
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        return self::$nonce ??= base64_encode(random_bytes(16));
    }

    public static function reset(): void
    {
        self::$nonce = null;
    }

    /**
     * @param 'admin'|'site'|'media' $surface
     */
    public static function apply(Response $response, Request $request, string $surface): Response
    {
        $h = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'X-Frame-Options' => $surface === 'admin' ? 'DENY' : 'SAMEORIGIN',
        ];
        if ($request->isSecure()) {
            $h['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        $nonce = self::nonce();
        if ($surface === 'media') {
            $h['Content-Security-Policy'] = "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox";
        } elseif ($surface === 'admin') {
            $h['Content-Security-Policy'] = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-$nonce'",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' data: https://fonts.gstatic.com",
                "img-src 'self' data: blob: https:",
                "connect-src 'self'",
                "frame-src 'self'",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]);
            $h['Cache-Control'] = 'no-store, private';
        } else {
            // The public site loads admin-configured vendor tags (GA4, GTM, Meta …).
            // 'strict-dynamic' lets a nonced loader pull in its own scripts while
            // still refusing any script an attacker manages to inject into markup.
            $h['Content-Security-Policy'] = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-$nonce' 'strict-dynamic' https: 'unsafe-inline'",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' data: https://fonts.gstatic.com",
                "img-src 'self' data: blob: https:",
                "media-src 'self' https:",
                "connect-src 'self' https:",
                // https: so embeds placed with the builder's Custom HTML widget (maps,
                // booking calendars) load; only staff with builder.code can add them.
                "frame-src 'self' https:",
                "frame-ancestors 'self'",
                "form-action 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]);
        }
        foreach ($h as $name => $value) {
            if (!isset($response->headers[$name])) {
                $response->headers[$name] = $value;
            }
        }
        return $response;
    }
}
