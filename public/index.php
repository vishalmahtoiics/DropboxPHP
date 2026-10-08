<?php

declare(strict_types=1);

/*
 * Front controller. The web server's document root is this directory, so
 * nothing outside it — application code, .env, storage, uploads — can be
 * requested directly.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Kernel;
use App\Core\Request;
use App\Core\Router;

// The PHP built-in server (development only): serve real static files directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

$router = new Router();
(require dirname(__DIR__) . '/app/routes.php')($router);

(new Kernel($router))->handle(Request::capture())->send();
