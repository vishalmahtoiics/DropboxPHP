<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Fixed-window rate limiting stored in MySQL, so it holds across PHP workers
 * and servers. Keys are hashed before storage; nothing personal is kept.
 */
final class RateLimiter
{
    /**
     * Count one attempt. Returns false once the key has exceeded $max attempts
     * within $windowSeconds.
     */
    public static function hit(string $key, int $max, int $windowSeconds): bool
    {
        $hash = Crypto::hash($key, 'ratelimit', 64);
        $now = time();
        $windowStart = $now - ($now % $windowSeconds);
        DB::query(
            'INSERT INTO rate_limits (`key_hash`, `window_start`, `hits`, `expires_at`)
             VALUES (?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE
               hits = IF(window_start = VALUES(window_start), hits + 1, 1),
               expires_at = VALUES(expires_at),
               window_start = VALUES(window_start)',
            [$hash, $windowStart, date('Y-m-d H:i:s', $windowStart + $windowSeconds)]
        );
        $hits = (int) DB::value('SELECT hits FROM rate_limits WHERE key_hash = ?', [$hash]);
        if (random_int(1, 200) === 1) {
            DB::query('DELETE FROM rate_limits WHERE expires_at < NOW()');
        }
        return $hits <= $max;
    }

    public static function tooMany(string $key, int $max): bool
    {
        $hash = Crypto::hash($key, 'ratelimit', 64);
        $hits = DB::value('SELECT hits FROM rate_limits WHERE key_hash = ? AND expires_at > NOW()', [$hash]);
        return $hits !== null && (int) $hits >= $max;
    }

    public static function clear(string $key): void
    {
        DB::query('DELETE FROM rate_limits WHERE key_hash = ?', [Crypto::hash($key, 'ratelimit', 64)]);
    }
}
