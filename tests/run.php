<?php

declare(strict_types=1);

/*
 * Dependency-free test runner.
 *
 *   php tests/run.php            unit + integration
 *   php tests/run.php unit       unit tests only (no database needed)
 *
 * Integration tests connect with the installed site's database login and use
 * the database "<site database>_test". It is wiped and migrated at the start of the run —
 * never point it at real data.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
use App\Core\Session;

$GLOBALS['__tests'] = [];
$GLOBALS['__assertions'] = 0;

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

final class AssertionFailed extends RuntimeException
{
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    $GLOBALS['__assertions']++;
    if ($expected !== $actual) {
        throw new AssertionFailed(($message ? "$message\n" : '') . 'Expected ' . var_export($expected, true) . "\n     got " . var_export($actual, true));
    }
}

function assert_true(mixed $value, string $message = 'Expected true'): void
{
    assert_same(true, (bool) $value, $message);
}

function assert_false(mixed $value, string $message = 'Expected false'): void
{
    assert_same(false, (bool) $value, $message);
}

function assert_contains(string $needle, string $haystack, string $message = ''): void
{
    $GLOBALS['__assertions']++;
    if (!str_contains($haystack, $needle)) {
        throw new AssertionFailed(($message ? "$message\n" : '') . "Expected to find '$needle' in:\n" . mb_substr($haystack, 0, 500));
    }
}

function assert_not_contains(string $needle, string $haystack, string $message = ''): void
{
    $GLOBALS['__assertions']++;
    if (str_contains(strtolower($haystack), strtolower($needle))) {
        throw new AssertionFailed(($message ? "$message\n" : '') . "Did not expect '$needle' in:\n" . mb_substr($haystack, 0, 500));
    }
}

/** @param class-string<Throwable> $class */
function assert_throws(string $class, callable $fn, string $message = ''): Throwable
{
    $GLOBALS['__assertions']++;
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return $e;
        }
        throw new AssertionFailed("Expected $class, got " . $e::class . ': ' . $e->getMessage());
    }
    throw new AssertionFailed($message ?: "Expected $class to be thrown");
}

$suite = $argv[1] ?? 'all';
Config::set('app.env', 'testing');
Config::set('app.debug', false);
if (strlen((string) config('app.key')) < 16) {
    Config::set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
}
Session::useMemory();

foreach (glob(__DIR__ . '/unit/*Test.php') ?: [] as $file) {
    require $file;
}

if ($suite !== 'unit') {
    if (!App\Core\Installation::installed()) {
        fwrite(STDERR, "Install the site first (open it in a browser) so the tests know the database login.\nRun with 'unit' to skip integration tests.\n");
        exit(1);
    }
    $db = config('database.database') . '_test';
    Config::set('database.database', $db);
    DB::setPdo(null);
    try {
        DB::pdo();
    } catch (Throwable $e) {
        fwrite(STDERR, "Cannot connect to the test database \"$db\": {$e->getMessage()}\nRun with 'unit' to skip integration tests.\n");
        exit(1);
    }
    require __DIR__ . '/integration/bootstrap.php';
    fresh_test_database();
    foreach (glob(__DIR__ . '/integration/*Test.php') ?: [] as $file) {
        require $file;
    }
}

$passed = 0;
$failed = [];
$start = microtime(true);
foreach ($GLOBALS['__tests'] as [$name, $fn]) {
    App\Core\Auth::reset();
    App\Services\Settings::flush();
    App\Services\Countries::reset();
    App\Services\Forms::clearCache();
    try {
        $fn();
        $passed++;
        fwrite(STDOUT, '.');
    } catch (Throwable $e) {
        $failed[] = [$name, $e];
        fwrite(STDOUT, 'F');
    }
}
fwrite(STDOUT, "\n\n");
foreach ($failed as [$name, $e]) {
    fwrite(STDOUT, "✗ $name\n  " . str_replace("\n", "\n  ", $e->getMessage()) . "\n  at " . $e->getFile() . ':' . $e->getLine() . "\n\n");
}
fwrite(STDOUT, sprintf("%d passed, %d failed, %d assertions in %.2fs\n", $passed, count($failed), $GLOBALS['__assertions'], microtime(true) - $start));
exit($failed ? 1 : 0);
