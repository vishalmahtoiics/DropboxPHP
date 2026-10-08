<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Migrator;
use App\Core\Response;
use App\Http\Controller;
use App\Services\Cron;
use Throwable;

final class SystemController extends Controller
{
    /** Liveness: the process answers and the database responds. */
    public function health(): Response
    {
        try {
            DB::value('SELECT 1');
            return Response::json(['status' => 'ok'])->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            return Response::json(['status' => 'database unavailable'], 503)->header('Cache-Control', 'no-store');
        }
    }

    /** Readiness: also every migration has been applied. */
    public function ready(): Response
    {
        try {
            $pending = Migrator::pending();
            if ($pending !== []) {
                return Response::json(['status' => 'migrations pending', 'pending' => count($pending)], 503);
            }
            return Response::json(['status' => 'ready', 'version' => config('app.version')])->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            return Response::json(['status' => 'database unavailable'], 503);
        }
    }

    /**
     * HTTP cron trigger for hosts without a real crontab:
     *   https://site/cron/run?token=CRON_SECRET  (every 5–15 minutes)
     * Prefer `php bin/console cron` from crontab where available.
     */
    public function cron(): Response
    {
        $secret = (string) config('app.cron_secret');
        $token = $this->request->queryStr('token');
        if (strlen($secret) < 24 || !hash_equals($secret, $token)) {
            throw HttpException::notFound();
        }
        return Response::json(['ok' => true, 'log' => Cron::run()])->header('Cache-Control', 'no-store');
    }
}
