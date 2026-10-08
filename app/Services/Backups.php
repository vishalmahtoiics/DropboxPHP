<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use Throwable;

final class Backups
{
    public static function runScheduled(): string
    {
        return 'not scheduled';
    }

    public static function restoreInProgress(): bool
    {
        try {
            return (bool) DB::value("SELECT 1 FROM system_locks WHERE name = 'restore' AND expires_at > NOW()");
        } catch (Throwable) {
            return false;
        }
    }
}
