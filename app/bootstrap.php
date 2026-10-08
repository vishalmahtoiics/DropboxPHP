<?php

declare(strict_types=1);

/*
 * Application bootstrap. Shared by the web front controller, the console and
 * the test runner. It loads the environment, registers the autoloader and the
 * error handlers, and nothing else — no output, no session, no database
 * connection. Those are opened lazily by whatever needs them.
 */

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('PHP 8.1 or newer is required.');
}

define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

date_default_timezone_set((string) config('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register();
