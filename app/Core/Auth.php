<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Authentication state and authorisation checks.
 *
 * Two-factor authentication is mandatory, so "signed in" is not binary: a
 * correct password yields `mfa-setup` or `mfa-pending`, and only a verified
 * second factor yields `authenticated`. Every guard is built on state(), so a
 * route cannot accept a half-authenticated session by forgetting a check.
 *
 * Role, permissions and status are read from the database on every request
 * (once, cached) rather than trusted from the session, so a role change, a
 * suspension or a revoked session takes effect immediately.
 */
final class Auth
{
    public const ANONYMOUS = 'anonymous';
    public const MFA_SETUP = 'mfa-setup';
    public const MFA_PENDING = 'mfa-pending';
    public const AUTHENTICATED = 'authenticated';

    /** @var array{status:string,user:?array<string,mixed>}|null */
    private static ?array $state = null;

    private static ?string $dummyHash = null;

    public static function reset(): void
    {
        self::$state = null;
    }

    /** @return array{status:string,user:?array<string,mixed>} */
    public static function state(): array
    {
        if (self::$state !== null) {
            return self::$state;
        }
        $anonymous = ['status' => self::ANONYMOUS, 'user' => null];
        $sid = Session::get('auth_sid');
        if (!is_string($sid) || strlen($sid) !== 64) {
            return self::$state = $anonymous;
        }
        $session = DB::first(
            'SELECT * FROM auth_sessions WHERE id = ? AND revoked_at IS NULL AND expires_at > NOW()',
            [$sid]
        );
        if (!$session) {
            return self::$state = $anonymous;
        }
        $idle = (int) config('app.session.idle', 7200);
        if (strtotime((string) $session['last_seen_at']) < time() - $idle) {
            DB::update('auth_sessions', ['revoked_at' => now(), 'revoked_reason' => 'idle'], ['id' => $sid]);
            return self::$state = $anonymous;
        }
        $user = DB::first(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name, r.`rank` AS role_rank
               FROM users u JOIN roles r ON r.id = u.role_id
              WHERE u.id = ? AND u.deleted_at IS NULL',
            [$session['user_id']]
        );
        if (!$user || $user['status'] !== 'ACTIVE') {
            return self::$state = $anonymous;
        }
        $user['permissions'] = DB::column(
            'SELECT p.`key` FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
            [$user['role_id']]
        );
        $user['session_id'] = $sid;
        unset($user['password_hash'], $user['two_factor_secret'], $user['two_factor_pending']);

        if ((int) $user['two_factor_required'] === 1 && (int) $user['two_factor_enabled'] === 0) {
            return self::$state = ['status' => self::MFA_SETUP, 'user' => $user];
        }
        if ((int) $user['two_factor_required'] === 1 && $session['mfa_verified_at'] === null) {
            return self::$state = ['status' => self::MFA_PENDING, 'user' => $user];
        }
        // Touch at most once a minute to keep writes down.
        if (strtotime((string) $session['last_seen_at']) < time() - 60) {
            DB::update('auth_sessions', ['last_seen_at' => now()], ['id' => $sid]);
        }
        return self::$state = ['status' => self::AUTHENTICATED, 'user' => $user];
    }

    /** The fully authenticated user, or null. */
    public static function user(): ?array
    {
        $state = self::state();
        return $state['status'] === self::AUTHENTICATED ? $state['user'] : null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        return $user !== null && in_array($permission, $user['permissions'], true);
    }

    /** @param list<string> $permissions */
    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (self::can($p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check credentials. Runs a bcrypt comparison even for an unknown email so
     * response time does not reveal which addresses exist.
     *
     * @return array<string,mixed>|null
     */
    public static function attempt(string $email, string $password): ?array
    {
        $user = DB::first('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [strtolower(trim($email))]);
        $hash = $user['password_hash'] ?? null;
        $ok = password_verify($password, is_string($hash) && $hash !== '' ? $hash : (self::$dummyHash ??= self::hash(bin2hex(random_bytes(8)))));
        if (!$user || !$ok || !$hash || $user['status'] !== 'ACTIVE') {
            return null;
        }
        if (password_needs_rehash($hash, self::algo(), self::algoOptions())) {
            DB::update('users', ['password_hash' => self::hash($password)], ['id' => $user['id']]);
        }
        return $user;
    }

    public static function login(array $user, Request $request): void
    {
        Session::regenerate();
        $sid = bin2hex(random_bytes(32));
        DB::insert('auth_sessions', [
            'id' => $sid,
            'user_id' => $user['id'],
            'ip_hash' => Crypto::hash($request->ip(), 'ip'),
            'user_agent' => $request->userAgent(),
            'expires_at' => date('Y-m-d H:i:s', time() + (int) config('app.session.lifetime')),
        ]);
        Session::put('auth_sid', $sid);
        Csrf::rotate();
        DB::update('users', ['last_login_at' => now()], ['id' => $user['id']]);
        self::reset();
    }

    public static function markMfaVerified(string $method): void
    {
        $sid = Session::get('auth_sid');
        if (is_string($sid)) {
            DB::update('auth_sessions', ['mfa_verified_at' => now(), 'mfa_method' => $method], ['id' => $sid]);
            Session::regenerate();
            Csrf::rotate();
        }
        self::reset();
    }

    public static function logout(): void
    {
        $sid = Session::get('auth_sid');
        if (is_string($sid)) {
            DB::update('auth_sessions', ['revoked_at' => now(), 'revoked_reason' => 'signed-out'], ['id' => $sid]);
        }
        Session::destroy();
        self::reset();
    }

    /** Revoke every session of a user, optionally keeping the current one. */
    public static function revokeAll(int $userId, string $reason, ?string $exceptSid = null): void
    {
        DB::query(
            'UPDATE auth_sessions SET revoked_at = NOW(), revoked_reason = ? WHERE user_id = ? AND revoked_at IS NULL AND id <> ?',
            [$reason, $userId, $exceptSid ?? '']
        );
    }

    public static function hash(string $password): string
    {
        return password_hash($password, self::algo(), self::algoOptions());
    }

    private static function algo(): string|int|null
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    /** @return array<string,int> */
    private static function algoOptions(): array
    {
        return defined('PASSWORD_ARGON2ID') ? [] : ['cost' => 12];
    }

    /** Password policy shared by every place a password is set. */
    public static function passwordError(string $password, string $email = ''): ?string
    {
        if (mb_strlen($password) < 10) {
            return 'Use at least 10 characters.';
        }
        if (mb_strlen($password) > 200) {
            return 'Use at most 200 characters.';
        }
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Use upper- and lower-case letters and at least one number.';
        }
        if ($email !== '' && stripos($password, explode('@', $email)[0]) !== false) {
            return 'The password must not contain your email name.';
        }
        return null;
    }
}
