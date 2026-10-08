<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Request;
use App\Core\ValidationException;
use Throwable;

/**
 * Manual and automatic redirects. Sources are stored normalised (leading
 * slash, no trailing slash, lower-case) and looked up by hash. Saving walks the
 * chain and refuses a loop; following always goes straight to the final
 * destination, never through a chain.
 */
final class Redirects
{
    public static function normalise(string $path): string
    {
        $path = trim($path);
        if (preg_match('#^https?://#i', $path)) {
            $path = (string) (parse_url($path, PHP_URL_PATH) ?: '/');
        }
        $path = '/' . ltrim(explode('?', explode('#', $path)[0])[0], '/');
        $path = $path !== '/' ? rtrim($path, '/') : $path;
        return mb_strtolower(rawurldecode($path));
    }

    /** @return array<string,mixed>|null */
    public static function match(string $path): ?array
    {
        try {
            $source = self::normalise($path);
            $row = DB::first('SELECT * FROM redirects WHERE source_hash = ? AND is_active = 1', [hash('sha256', $source)]);
            if (!$row) {
                return null;
            }
            DB::query('UPDATE redirects SET hit_count = hit_count + 1, last_hit_at = NOW() WHERE id = ?', [$row['id']]);
            // Follow the chain to its end (bounded), so the visitor takes one hop.
            $seen = [$source => true];
            $dest = (string) $row['destination'];
            for ($i = 0; $i < 10 && str_starts_with($dest, '/'); $i++) {
                $key = self::normalise($dest);
                if (isset($seen[$key])) {
                    break;
                }
                $seen[$key] = true;
                $next = DB::first('SELECT destination FROM redirects WHERE source_hash = ? AND is_active = 1', [hash('sha256', $key)]);
                if (!$next) {
                    break;
                }
                $dest = (string) $next['destination'];
            }
            $row['destination'] = $dest;
            return $row;
        } catch (Throwable) {
            return null;
        }
    }

    /** Save (create or update) a redirect, refusing loops and self-redirects. */
    public static function save(array $data, ?int $id = null): int
    {
        $source = self::normalise((string) $data['source']);
        $dest = trim((string) $data['destination']);
        if (str_starts_with($dest, '/')) {
            $destKey = self::normalise($dest);
            if ($destKey === $source) {
                throw ValidationException::with('destination', 'A redirect cannot point to itself.');
            }
            $cursor = $destKey;
            for ($i = 0; $i < 20; $i++) {
                $next = DB::first('SELECT id, destination FROM redirects WHERE source_hash = ? AND is_active = 1', [hash('sha256', $cursor)]);
                if (!$next || ($id !== null && (int) $next['id'] === $id) || !str_starts_with((string) $next['destination'], '/')) {
                    break;
                }
                $cursor = self::normalise((string) $next['destination']);
                if ($cursor === $source) {
                    throw ValidationException::with('destination', 'This would create a redirect loop.');
                }
            }
        } elseif (!preg_match('#^https?://#i', $dest)) {
            throw ValidationException::with('destination', 'Use a path starting with / or a full https:// URL.');
        }
        $existing = DB::first('SELECT id FROM redirects WHERE source_hash = ?', [hash('sha256', $source)]);
        if ($existing && (int) $existing['id'] !== $id) {
            throw ValidationException::with('source', 'A redirect from this address already exists.');
        }
        $row = [
            'source' => $source,
            'source_hash' => hash('sha256', $source),
            'destination' => $dest,
            'type' => ($data['type'] ?? 'PERMANENT') === 'TEMPORARY' ? 'TEMPORARY' : 'PERMANENT',
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'note' => mb_substr((string) ($data['note'] ?? ''), 0, 255) ?: null,
            'origin' => $data['origin'] ?? 'MANUAL',
        ];
        if ($id) {
            DB::update('redirects', $row, ['id' => $id]);
            return $id;
        }
        return DB::insert('redirects', $row);
    }

    /**
     * When a published slug changes, keep the old address working.
     * Points any existing redirect at the old path straight to the new one.
     */
    public static function onSlugChange(string $oldPath, string $newPath): void
    {
        $old = self::normalise($oldPath);
        $new = self::normalise($newPath);
        if ($old === $new || $old === '/') {
            return;
        }
        try {
            DB::query('UPDATE redirects SET destination = ? WHERE destination = ?', [$new, $old]);
            DB::delete('redirects', ['source_hash' => hash('sha256', $new)]);
            $existing = DB::first('SELECT id FROM redirects WHERE source_hash = ?', [hash('sha256', $old)]);
            if ($existing) {
                DB::update('redirects', ['destination' => $new, 'is_active' => 1], ['id' => $existing['id']]);
            } else {
                DB::insert('redirects', [
                    'source' => $old, 'source_hash' => hash('sha256', $old), 'destination' => $new,
                    'type' => 'PERMANENT', 'is_active' => 1, 'origin' => 'AUTOMATIC', 'note' => 'Created when the address changed',
                ]);
            }
        } catch (Throwable) {
            // A missing redirect is recoverable; failing the save is not acceptable.
        }
    }

    /** Count 404s for the URL health report. */
    public static function recordNotFound(Request $request): void
    {
        if ($request->method !== 'GET' || preg_match('/\.(php|env|git|aspx?|jsp|cgi|sql|bak|zip|tar|gz|ico|png|jpe?g|gif|svg|webp|css|js|map|txt|xml)$|wp-|xmlrpc|apple-touch/i', $request->path)) {
            return;
        }
        try {
            $path = mb_substr(self::normalise($request->path), 0, 500);
            DB::query(
                'INSERT INTO not_found_log (path_hash, path, hits, last_referrer, first_seen_at, last_seen_at) VALUES (?, ?, 1, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen_at = NOW(), last_referrer = VALUES(last_referrer)',
                [hash('sha256', $path), $path, mb_substr((string) $request->referer(), 0, 500) ?: null]
            );
        } catch (Throwable) {
        }
    }
}
