<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\DB;
use App\Core\Installation;
use App\Core\ValidationException;
use App\Services\Installer;

/**
 * Runs $fn as if the site were not installed: a throwaway installation file
 * and an empty test database. Restores the normal test setup afterwards.
 */
$uninstalled = static function (callable $fn): void {
    $app = config('app');
    $db = config('database');
    $file = BASE_PATH . '/storage/cache/installed-test-' . bin2hex(random_bytes(4)) . '.php';
    try {
        Installation::useFile($file);
        Config::set('database', $db);
        Config::set('app.env', 'testing');
        Config::set('app.debug', false);
        DB::setPdo(null);
        drop_all_tables();
        $fn($db, $file);
    } finally {
        foreach (glob(BASE_PATH . '/storage/cache/installed-test-*') ?: [] as $f) {
            @unlink($f);
        }
        Installation::useFile(null);
        Config::set('app', $app);
        Config::set('database', $db);
        DB::setPdo(null);
        fresh_test_database();
    }
};

$form = static fn (array $db, array $extra = []): array => $extra + [
    'url' => 'https://www.example.com',
    'db_host' => $db['host'], 'db_port' => (string) $db['port'], 'db_name' => $db['database'],
    'db_user' => $db['username'], 'db_pass' => $db['password'],
    'admin_name' => 'Owner', 'admin_email' => 'owner@example.com',
    'admin_password' => 'CorrectHorse42x', 'admin_password_confirmation' => 'CorrectHorse42x',
    'login_path' => 'team-login', 'timezone' => 'Asia/Kolkata', 'demo' => '',
];

test('the installer validates the site address and sign-in address', function (): void {
    assert_true(Installer::urlError('ftp://example.com') !== null);
    assert_true(Installer::urlError('https://www.example.com') === null);
    assert_true(Installer::urlError('http://localhost:8000') === null);
    assert_true(Installer::loginPathError('/admin') !== null, '/admin is reserved');
    assert_true(Installer::loginPathError('/admin/login') !== null, 'nothing under /admin');
    assert_true(Installer::loginPathError('/install') !== null);
    assert_true(Installer::loginPathError('/a') !== null, 'too short');
    assert_true(Installer::loginPathError('/team//login') !== null);
    assert_true(Installer::loginPathError('/team-login') === null);
});

test('before installation every request is sent to the installer', function () use ($uninstalled): void {
    $uninstalled(function (): void {
        assert_false(Installation::installed());
        $home = dispatch(fake_request('GET', '/'));
        assert_same(302, $home->status);
        assert_same('/install', $home->headers['Location']);
        assert_same('/install', dispatch(fake_request('GET', '/admin'))->headers['Location']);
        $page = dispatch(fake_request('GET', '/install'));
        assert_same(200, $page->status);
        assert_contains('Install the website', $page->body);
        assert_contains('no-store', $page->headers['Cache-Control'] ?? '');
    });
});

test('the installer builds a fresh site and then locks itself', function () use ($uninstalled, $form): void {
    $uninstalled(function (array $db, string $file) use ($form): void {
        $wrong = assert_throws(ValidationException::class, fn () => Installer::install($form($db, ['db_pass' => 'not-the-password'])));
        assert_true(isset($wrong->errors['db_host']), 'a bad database login is reported');

        $weak = assert_throws(ValidationException::class, fn () => Installer::install($form($db, ['admin_password' => 'short', 'admin_password_confirmation' => 'short'])));
        assert_true(isset($weak->errors['admin_password']));
        assert_false(is_file($file), 'nothing is recorded until it succeeds');

        $result = Installer::install($form($db));
        assert_false($result['existing']);
        assert_true(Installation::installed());
        assert_same('0600', substr(sprintf('%o', fileperms($file)), -4));
        $saved = require $file;
        assert_same($result['key'], $saved['key']);
        assert_same('/team-login', $saved['login_path']);
        assert_same('https://www.example.com', $saved['url']);
        assert_true(strlen((string) $saved['cron_secret']) >= 24);

        assert_same('owner@example.com', DB::value("SELECT u.email FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'super-admin'"));
        assert_same(2, (int) DB::value('SELECT COUNT(*) FROM countries'));
        assert_same(0, (int) DB::value('SELECT COUNT(*) FROM pages'), 'demo content was not asked for');
        assert_true(Installation::keyMatches());

        assert_throws(ValidationException::class, fn () => Installer::install($form($db)), 'a second install is refused');
        assert_same(404, dispatch(fake_request('GET', '/install'))->status, 'the installer is gone once installed');
    });
});

test('reconnecting to an existing site needs its original key', function () use ($uninstalled, $form): void {
    $uninstalled(function (array $db, string $file) use ($form): void {
        $key = Installer::install($form($db))['key'];

        // Lost installation file, database intact.
        unlink($file);
        Installation::useFile($file);
        Config::set('database', $db);
        DB::setPdo(null);

        $missing = assert_throws(ValidationException::class, fn () => Installer::install($form($db)));
        assert_true(isset($missing->errors['existing_key']));
        $other = assert_throws(ValidationException::class, fn () => Installer::install($form($db, ['existing_key' => Installation::newKey()])));
        assert_contains('does not belong', $other->errors['existing_key'] ?? '');

        $result = Installer::install($form($db, ['existing_key' => $key]));
        assert_true($result['existing']);
        assert_same($key, $result['key']);
        assert_same(1, (int) DB::value('SELECT COUNT(*) FROM users'), 'no second administrator');
    });
});

test('System settings rewrite the recorded values; the key needs a password', function () use ($uninstalled, $form): void {
    $uninstalled(function (array $db, string $file) use ($form): void {
        $key = Installer::install($form($db))['key'];
        Config::set('app.key', $key);
        sign_in_as('super-admin', 'boss@test.local');

        $page = dispatch(fake_request('GET', '/admin/settings'));
        assert_same(200, $page->status);
        assert_contains('Cron job', $page->body);
        assert_contains('/cron/run?token=', $page->body);
        assert_not_contains($key, $page->body, 'the key is not shown without a password');

        $bad = dispatch(fake_request('POST', '/admin/settings/system', ['url' => 'https://new.example.com', 'login_path' => 'admin', 'timezone' => 'Asia/Dubai']));
        assert_same(302, $bad->status);
        assert_same('/team-login', (require $file)['login_path'], 'a reserved sign-in path is refused');

        dispatch(fake_request('POST', '/admin/settings/system', ['url' => 'https://new.example.com/', 'login_path' => 'staff-door', 'timezone' => 'Asia/Dubai']));
        $saved = require $file;
        assert_same('https://new.example.com', $saved['url']);
        assert_same('/staff-door', $saved['login_path']);
        assert_same('Asia/Dubai', $saved['timezone']);
        assert_same($key, $saved['key'], 'the key and database login are kept');
        assert_same($db['database'], $saved['database']['database']);

        assert_not_contains($key, dispatch(fake_request('POST', '/admin/settings/system/key', ['password' => 'wrong']))->body);
        $shown = dispatch(fake_request('POST', '/admin/settings/system/key', ['password' => 'Passw0rdPassw0rd']));
        assert_same(200, $shown->status);
        assert_contains($key, $shown->body);
        assert_same(1, (int) DB::value("SELECT COUNT(*) FROM audit_logs WHERE summary = 'Viewed the encryption key'"));

        sign_in_as('sales');
        assert_same(403, dispatch(fake_request('POST', '/admin/settings/system', ['url' => 'https://evil.example', 'login_path' => 'x-login', 'timezone' => 'UTC']))->status);
    });
});
