<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Turns warnings into exceptions and makes sure no stack trace, SQL or file
 * path ever reaches a visitor outside debug mode. Everything is logged to
 * storage/logs/app-YYYY-MM-DD.log.
 */
final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e): void {
            self::report($e);
            self::render($e);
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::log('FATAL ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
            }
        });
    }

    public static function report(Throwable $e): void
    {
        if ($e instanceof HttpException && $e->status < 500) {
            return;
        }
        self::log(sprintf(
            "%s: %s in %s:%d\n%s",
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }

    public static function log(string $message, string $channel = 'app'): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        @file_put_contents($dir . '/' . $channel . '-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    private static function render(Throwable $e): void
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
            exit(1);
        }
        $status = $e instanceof HttpException ? $e->status : 500;
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
        }
        if (config('app.debug')) {
            echo '<pre style="white-space:pre-wrap;font:13px/1.5 monospace;padding:1rem">'
                . e($e::class . ': ' . $e->getMessage() . "\n\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString())
                . '</pre>';
            return;
        }
        echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
            . '<div style="font-family:system-ui;max-width:32rem;margin:15vh auto;padding:1rem;text-align:center">'
            . '<h1 style="font-size:1.5rem">Something went wrong</h1>'
            . '<p style="color:#5b6b85">Please try again in a moment.</p></div>';
    }
}
