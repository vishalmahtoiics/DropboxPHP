<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Synchroniser-token CSRF protection.
 *
 * Every state-changing request through the router must carry the session's
 * token, either as the `_token` field or the `X-CSRF-Token` header. Checked in
 * the kernel before any controller runs, so no individual action can forget.
 */
final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }
        return $token;
    }

    public static function verify(Request $request): bool
    {
        $expected = Session::get('_csrf');
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        $given = $request->body['_token'] ?? $request->header('X-CSRF-Token');
        return is_string($given) && hash_equals($expected, $given);
    }

    public static function rotate(): void
    {
        Session::put('_csrf', bin2hex(random_bytes(32)));
    }
}
