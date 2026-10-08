<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Session;

App\Services\Mailer::$fake = true;

function drop_all_tables(): void
{
    DB::pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (DB::column("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $t) {
        DB::pdo()->exec("DROP TABLE `$t`");
    }
    DB::pdo()->exec('SET FOREIGN_KEY_CHECKS=1');
}

/** Empty schema with the two markets the tests expect. */
function fresh_test_database(): void
{
    drop_all_tables();
    App\Core\Migrator::migrate();
    App\Core\Permissions::sync();
    DB::query("INSERT INTO countries (name, code, slug, currency, currency_symbol, is_default, is_active, is_published, settings) VALUES
        ('India', 'IN', '', 'INR', '₹', 1, 1, 1, '{}'), ('UAE', 'AE', 'ae', 'AED', 'AED ', 0, 1, 1, '{}')");
}

/** Create a staff user with a role and sign them in (MFA verified). */
function sign_in_as(string $roleSlug, string $email = ''): array
{
    $email = $email ?: $roleSlug . '-' . bin2hex(random_bytes(3)) . '@test.local';
    $roleId = (int) DB::value('SELECT id FROM roles WHERE slug = ?', [$roleSlug]);
    $id = DB::insert('users', ['email' => $email, 'name' => ucfirst($roleSlug), 'role_id' => $roleId, 'password_hash' => Auth::hash('Passw0rdPassw0rd'),
        'status' => 'ACTIVE', 'two_factor_enabled' => 1, 'two_factor_required' => 1]);
    Session::useMemory();
    $sid = bin2hex(random_bytes(32));
    DB::insert('auth_sessions', ['id' => $sid, 'user_id' => $id, 'mfa_verified_at' => now(), 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);
    Session::put('auth_sid', $sid);
    Auth::reset();
    return Auth::user() ?? throw new RuntimeException('sign-in failed');
}

/** @param array<string,mixed> $body */
function fake_request(string $method, string $path, array $body = [], array $cookies = []): Request
{
    return new Request($method, $path, [], $body, [], ['REMOTE_ADDR' => '203.0.113.' . random_int(1, 250), 'HTTP_USER_AGENT' => 'tests', 'HTTP_HOST' => 'localhost'], $cookies);
}

/** Run a request through the real router and kernel. */
function dispatch(Request $request): App\Core\Response
{
    $router = new App\Core\Router();
    (require BASE_PATH . '/app/routes.php')($router);
    if ($request->isPost()) {
        $request = new Request($request->method, $request->path, $request->query, $request->body + ['_token' => App\Core\Csrf::token()], $request->files, $request->server, $request->cookies);
    }
    App\Core\View::resetShared();
    App\Services\SiteContext::reset();
    App\Builder\Chrome::reset();
    return (new App\Core\Kernel($router))->handle($request);
}

function make_form(string $slug, array $fields, array $extra = []): array
{
    $id = DB::insert('forms', $extra + ['name' => ucfirst($slug), 'slug' => $slug, 'submit_label' => 'Send', 'success_message' => 'Thanks', 'creates_lead' => 1]);
    foreach ($fields as $i => $f) {
        DB::insert('form_fields', $f + ['form_id' => $id, 'sort_order' => $i]);
    }
    App\Services\Forms::clearCache();
    return App\Services\Forms::bySlug($slug);
}
