<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\ErrorHandler;
use Throwable;

/**
 * Scheduled jobs. Run every 5–15 minutes by crontab
 *   *\/5 * * * * php /path/to/project/bin/console cron
 * or, on hosts without cron, by the /cron/run?token=… URL.
 */
final class Cron
{
    /** @return list<string> */
    public static function run(): array
    {
        $log = [];
        $jobs = [
            'publish scheduled content' => [self::class, 'publishScheduled'],
            'clean expired sessions and limits' => [self::class, 'cleanup'],
            'scheduled backup' => [Backups::class, 'runScheduled'],
        ];
        foreach ($jobs as $name => $job) {
            try {
                $result = $job();
                $log[] = $name . ': ' . ($result ?: 'ok');
            } catch (Throwable $e) {
                ErrorHandler::report($e);
                $log[] = $name . ': FAILED (' . $e->getMessage() . ')';
            }
        }
        return $log;
    }

    public static function publishScheduled(): string
    {
        $n = 0;
        foreach (['pages', 'blog_posts', 'products'] as $table) {
            $n += DB::query(
                "UPDATE `$table` SET status = 'PUBLISHED' WHERE status = 'SCHEDULED' AND published_at IS NOT NULL AND published_at <= NOW() AND deleted_at IS NULL"
            )->rowCount();
        }
        return "$n item(s) published";
    }

    public static function cleanup(): string
    {
        $sessions = DB::query('DELETE FROM auth_sessions WHERE expires_at < NOW() - INTERVAL 30 DAY')->rowCount();
        $limits = DB::query('DELETE FROM rate_limits WHERE expires_at < NOW()')->rowCount();
        return "$sessions session(s), $limits limit(s) removed";
    }
}
