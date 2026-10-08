<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Append-only audit trail. Never throws: a failure to record must not undo
 * the change it describes.
 */
final class Audit
{
    private static ?Request $request = null;

    public static function setRequest(?Request $request): void
    {
        self::$request = $request;
    }

    public static function record(
        string $action,
        string $entity,
        int|string|null $entityId = null,
        ?string $summary = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        try {
            $user = Auth::state()['user'];
            DB::insert('audit_logs', [
                'actor_id' => $user['id'] ?? null,
                'actor_email' => $user['email'] ?? null,
                'action' => mb_substr($action, 0, 40),
                'entity' => mb_substr($entity, 0, 60),
                'entity_id' => $entityId === null ? null : (string) $entityId,
                'summary' => $summary,
                'before_data' => $before === null ? null : self::scrub($before),
                'after_data' => $after === null ? null : self::scrub($after),
                'ip_hash' => self::$request ? Crypto::hash(self::$request->ip(), 'ip') : null,
                'user_agent' => self::$request?->userAgent(),
            ]);
        } catch (Throwable $e) {
            ErrorHandler::log('audit failed: ' . $e->getMessage());
        }
    }

    /** Secrets never reach the audit log. */
    private static function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/password|secret|token|two_factor/i', $key)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = self::scrub($value);
            }
        }
        return $data;
    }
}
