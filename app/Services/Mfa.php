<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\DB;
use App\Core\Totp;

/**
 * Authenticator-app two-factor authentication.
 *
 * Secrets are encrypted at rest. A code's time step is remembered and never
 * accepted twice, so an intercepted code cannot be replayed. Recovery codes
 * are stored only as hashes and each works once.
 */
final class Mfa
{
    public static function issuer(): string
    {
        return Brand::name();
    }

    /** Start (or continue) enrolment: a pending secret, not yet active. */
    public static function pendingSecret(int $userId): string
    {
        $row = DB::first('SELECT two_factor_pending FROM users WHERE id = ?', [$userId]);
        $secret = Crypto::decrypt($row['two_factor_pending'] ?? null, 'mfa');
        if (!$secret) {
            $secret = Totp::generateSecret();
            DB::update('users', ['two_factor_pending' => Crypto::encrypt($secret, 'mfa')], ['id' => $userId]);
        }
        return $secret;
    }

    /** Confirm enrolment with a first code. @return list<string>|null recovery codes */
    public static function confirmEnrolment(int $userId, string $code): ?array
    {
        $secret = self::pendingSecret($userId);
        $step = Totp::verify($secret, $code);
        if ($step === null) {
            return null;
        }
        DB::update('users', [
            'two_factor_secret' => Crypto::encrypt($secret, 'mfa'),
            'two_factor_pending' => null,
            'two_factor_enabled' => 1,
            'two_factor_verified_at' => now(),
            'two_factor_last_step' => $step,
        ], ['id' => $userId]);
        return self::regenerateRecoveryCodes($userId);
    }

    /** Verify a sign-in code (authenticator or recovery). Returns the method used, or null. */
    public static function verify(int $userId, string $code): ?string
    {
        $user = DB::first('SELECT two_factor_secret, two_factor_last_step FROM users WHERE id = ?', [$userId]);
        $secret = Crypto::decrypt($user['two_factor_secret'] ?? null, 'mfa');
        $clean = strtoupper(preg_replace('/[\s\-]/', '', $code) ?? '');

        if ($secret && preg_match('/^\d{6}$/', $clean)) {
            $last = $user['two_factor_last_step'] !== null ? (int) $user['two_factor_last_step'] : null;
            $step = Totp::verify($secret, $clean, $last);
            if ($step !== null) {
                DB::update('users', ['two_factor_last_step' => $step], ['id' => $userId]);
                return 'totp';
            }
            return null;
        }
        if (preg_match('/^[A-Z0-9]{10}$/', $clean)) {
            $hash = hash('sha256', $userId . ':' . $clean);
            $updated = DB::query(
                'UPDATE recovery_codes SET used_at = NOW() WHERE user_id = ? AND code_hash = ? AND used_at IS NULL',
                [$userId, $hash]
            )->rowCount();
            return $updated === 1 ? 'recovery' : null;
        }
        return null;
    }

    /** @return list<string> */
    public static function regenerateRecoveryCodes(int $userId): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codes = [];
        DB::transaction(static function () use ($userId, $alphabet, &$codes): void {
            DB::delete('recovery_codes', ['user_id' => $userId]);
            for ($i = 0; $i < 10; $i++) {
                $code = '';
                for ($j = 0; $j < 10; $j++) {
                    $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $codes[] = $code;
                DB::insert('recovery_codes', ['user_id' => $userId, 'code_hash' => hash('sha256', $userId . ':' . $code)]);
            }
        });
        return array_map(static fn ($c) => substr($c, 0, 5) . '-' . substr($c, 5), $codes);
    }

    public static function remainingRecoveryCodes(int $userId): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM recovery_codes WHERE user_id = ? AND used_at IS NULL', [$userId]);
    }

    public static function reset(int $userId): void
    {
        DB::update('users', [
            'two_factor_enabled' => 0, 'two_factor_secret' => null, 'two_factor_pending' => null,
            'two_factor_verified_at' => null, 'two_factor_last_step' => null,
        ], ['id' => $userId]);
        DB::delete('recovery_codes', ['user_id' => $userId]);
    }
}
