<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\DB;
use App\Core\Session;

test('anonymous /admin answers 404, never a redirect to the login screen', function (): void {
    Session::useMemory();
    Auth::reset();
    $res = dispatch(fake_request('GET', '/admin'));
    assert_same(404, $res->status);
    assert_false(isset($res->headers['Location']));
});

test('a half-authenticated session is sent to the MFA step', function (): void {
    $user = sign_in_as('sales');
    DB::update('auth_sessions', ['mfa_verified_at' => null], ['id' => Session::get('auth_sid')]);
    Auth::reset();
    $res = dispatch(fake_request('GET', '/admin/leads'));
    assert_same('/auth/verify-2fa', $res->headers['Location'] ?? null);
});

test('a role without the permission is refused and nothing changes', function (): void {
    sign_in_as('sales');
    $res = dispatch(fake_request('POST', '/admin/settings/website', ['site_name' => 'Hacked']));
    assert_same(403, $res->status);
    assert_same(null, DB::value("SELECT data FROM settings WHERE `group` = 'website'"));
    $res = dispatch(fake_request('GET', '/admin/staff'));
    assert_same(403, $res->status);
});

test('POST without a CSRF token is rejected', function (): void {
    sign_in_as('super-admin');
    $router = new App\Core\Router();
    (require BASE_PATH . '/app/routes.php')($router);
    $res = (new App\Core\Kernel($router))->handle(fake_request('POST', '/admin/settings/website', ['site_name' => 'X']));
    assert_true(in_array($res->status, [302, 303], true));
    assert_same(null, DB::value("SELECT data FROM settings WHERE `group` = 'website'"));
});

test('admins cannot grant a role at or above their own', function (): void {
    sign_in_as('admin');
    $super = (int) DB::value("SELECT id FROM roles WHERE slug = 'super-admin'");
    dispatch(fake_request('POST', '/admin/staff', ['name' => 'X', 'email' => 'x@test.local', 'role_id' => $super, 'password' => 'Abcdefgh123', 'all_countries' => 1]));
    assert_same(null, DB::value("SELECT id FROM users WHERE email = 'x@test.local'"));
});

test('revoked and suspended sessions stop working immediately', function (): void {
    $u = sign_in_as('admin');
    assert_true(Auth::user() !== null);
    DB::update('users', ['status' => 'SUSPENDED'], ['id' => $u['id']]);
    Auth::reset();
    assert_same(null, Auth::user());
    DB::update('users', ['status' => 'ACTIVE'], ['id' => $u['id']]);
    Auth::revokeAll((int) $u['id'], 'test');
    Auth::reset();
    assert_same(null, Auth::user());
});

test('wrong passwords never reveal whether an account exists', function (): void {
    sign_in_as('sales', 'known@test.local');
    assert_same(null, Auth::attempt('known@test.local', 'wrong'));
    assert_same(null, Auth::attempt('unknown@test.local', 'wrong'));
    assert_true(Auth::attempt('known@test.local', 'Passw0rdPassw0rd') !== null);
});
