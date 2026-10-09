<?php

declare(strict_types=1);

use App\Builder\Layout;
use App\Core\DB;

$india = static fn (): int => (int) DB::value("SELECT id FROM countries WHERE code = 'IN'");
$page = static function (string $slug, string $status = 'DRAFT', array $extra = []) use ($india): int {
    return DB::insert('pages', $extra + ['country_id' => $india(), 'title' => 'Bulk ' . $slug, 'slug' => $slug, 'status' => $status]);
};
$role = static function (array $perms): string {
    $slug = 'bulk-' . bin2hex(random_bytes(3));
    $id = DB::insert('roles', ['name' => 'Bulk role', 'slug' => $slug, 'rank' => 50]);
    foreach ($perms as $perm) {
        DB::query('INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE `key` = ?', [$id, $perm]);
    }
    return $slug;
};

test('pages can be published, unpublished and deleted together; publishing makes builder drafts live', function () use ($page, $india): void {
    $draft = Layout::encode(Layout::normalize(['elements' => [['type' => 'container', 'children' => [['type' => 'heading', 'settings' => ['title' => 'From the import']]]]]]));
    $a = $page('bulk-a');
    $b = $page('bulk-b', 'DRAFT', ['builder_draft' => $draft]);
    $home = (int) DB::value("SELECT id FROM pages WHERE country_id = ? AND slug = ''", [$india()]);
    sign_in_as('admin');

    $res = dispatch(fake_request('POST', '/admin/pages/bulk', ['action' => 'publish', 'ids' => [$a, $b, 999999]]));
    assert_same(302, $res->status);
    assert_same('PUBLISHED', DB::value('SELECT status FROM pages WHERE id = ?', [$a]));
    $pb = DB::first('SELECT * FROM pages WHERE id = ?', [$b]);
    assert_same('PUBLISHED', $pb['status']);
    assert_same(null, $pb['builder_draft'], 'the builder draft went live');
    assert_same('builder', $pb['editor']);
    assert_contains('From the import', dispatch(fake_request('GET', '/bulk-b'))->body);

    $homeStatus = DB::value('SELECT status FROM pages WHERE id = ?', [$home]);
    dispatch(fake_request('POST', '/admin/pages/bulk', ['action' => 'draft', 'ids' => [$a, $home]]));
    assert_same('DRAFT', DB::value('SELECT status FROM pages WHERE id = ?', [$a]));
    if ($homeStatus === 'PUBLISHED') {
        assert_same('PUBLISHED', DB::value('SELECT status FROM pages WHERE id = ?', [$home]), 'the live homepage is never taken offline in bulk');
    }

    dispatch(fake_request('POST', '/admin/pages/bulk', ['action' => 'delete', 'ids' => [$a, $home]]));
    assert_true(DB::value('SELECT deleted_at FROM pages WHERE id = ?', [$a]) !== null);
    assert_same(null, DB::value('SELECT deleted_at FROM pages WHERE id = ?', [$home]), 'the homepage is never deleted');

    $list = dispatch(fake_request('GET', '/admin/pages'))->body;
    assert_contains('data-bulk-form', $list);
    assert_contains('form="bulk-pages" data-bulk-item', $list);
});

test('bulk actions keep each permission', function () use ($page, $role): void {
    $a = $page('bulk-perm');
    sign_in_as($role(['dashboard.view', 'pages.view', 'pages.edit']));
    assert_same(403, dispatch(fake_request('POST', '/admin/pages/bulk', ['action' => 'publish', 'ids' => [$a]]))->status);
    assert_same(403, dispatch(fake_request('POST', '/admin/pages/bulk', ['action' => 'delete', 'ids' => [$a]]))->status);
    assert_same('DRAFT', DB::value('SELECT status FROM pages WHERE id = ?', [$a]));
    assert_not_contains('Publish (and make builder drafts live)', dispatch(fake_request('GET', '/admin/pages'))->body, 'actions the role cannot use are not offered');
    sign_in_as('sales');
    assert_same(403, dispatch(fake_request('POST', '/admin/products/bulk', ['action' => 'delete', 'ids' => [1]]))->status);
    assert_same(403, dispatch(fake_request('POST', '/admin/media/bulk', ['action' => 'delete', 'ids' => [1]]))->status);
});

test('products, articles, forms, customers, popups and redirects work in bulk', function () use ($india): void {
    sign_in_as('admin');
    $p1 = DB::insert('products', ['name' => 'Bulk product 1', 'slug' => 'bulk-product-1', 'status' => 'DRAFT']);
    $p2 = DB::insert('products', ['name' => 'Bulk product 2', 'slug' => 'bulk-product-2', 'status' => 'DRAFT']);
    dispatch(fake_request('POST', '/admin/products/bulk', ['action' => 'publish', 'ids' => [$p1, $p2]]));
    assert_same(2, (int) DB::value("SELECT COUNT(*) FROM products WHERE id IN (?, ?) AND status = 'PUBLISHED'", [$p1, $p2]));
    dispatch(fake_request('POST', '/admin/products/bulk', ['action' => 'delete', 'ids' => [$p2]]));
    assert_true(DB::value('SELECT deleted_at FROM products WHERE id = ?', [$p2]) !== null);

    $post = DB::insert('blog_posts', ['country_id' => $india(), 'title' => 'Bulk post', 'slug' => 'bulk-post', 'status' => 'DRAFT', 'content' => '<p>x</p>']);
    dispatch(fake_request('POST', '/admin/blog/bulk', ['action' => 'publish', 'ids' => [$post]]));
    assert_same('PUBLISHED', DB::value('SELECT status FROM blog_posts WHERE id = ?', [$post]));

    $form = DB::insert('forms', ['name' => 'Bulk form', 'slug' => 'bulk-form', 'success_message' => 'ok', 'is_active' => 1]);
    dispatch(fake_request('POST', '/admin/forms/bulk', ['action' => 'deactivate', 'ids' => [$form]]));
    assert_same(0, (int) DB::value('SELECT is_active FROM forms WHERE id = ?', [$form]));

    $cust = DB::insert('customers', ['name' => 'Bulk customer', 'email' => 'bulk@example.com', 'status' => 'PROSPECT']);
    dispatch(fake_request('POST', '/admin/customers/bulk', ['action' => 'status:ACTIVE', 'ids' => [$cust]]));
    assert_same('ACTIVE', DB::value('SELECT status FROM customers WHERE id = ?', [$cust]));
    dispatch(fake_request('POST', '/admin/customers/bulk', ['action' => 'status:BOGUS', 'ids' => [$cust]]));
    assert_same('ACTIVE', DB::value('SELECT status FROM customers WHERE id = ?', [$cust]), 'unknown statuses are refused');

    $popup = DB::insert('popups', ['name' => 'Bulk popup', 'type' => 'LEAD_FORM', 'is_active' => 0]);
    dispatch(fake_request('POST', '/admin/popups/bulk', ['action' => 'activate', 'ids' => [$popup]]));
    assert_same(1, (int) DB::value('SELECT is_active FROM popups WHERE id = ?', [$popup]));

    $r1 = App\Services\Redirects::save(['source' => '/bulk-old-1', 'destination' => '/x', 'is_active' => 1]);
    $r2 = App\Services\Redirects::save(['source' => '/bulk-old-2', 'destination' => '/x', 'is_active' => 1]);
    dispatch(fake_request('POST', '/admin/redirects/bulk', ['action' => 'deactivate', 'ids' => [$r1, $r2]]));
    assert_same(0, (int) DB::value('SELECT SUM(is_active) FROM redirects WHERE id IN (?, ?)', [$r1, $r2]));
    dispatch(fake_request('POST', '/admin/redirects/bulk', ['action' => 'delete', 'ids' => [$r1]]));
    assert_same(null, DB::value('SELECT id FROM redirects WHERE id = ?', [$r1]));
});

test('media moves to folders in bulk and deleting leaves files that pages use', function () use ($page): void {
    sign_in_as('admin');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    $files = [];
    foreach (['used', 'unused'] as $n) {
        $tmp = tempnam(sys_get_temp_dir(), 'bk');
        file_put_contents($tmp, $png);
        $files[$n] = App\Services\Uploads::store(['name' => "bulk-$n.png", 'tmp_name' => $tmp, 'size' => strlen($png), 'error' => 0], null);
        @unlink($tmp);
    }
    $page('bulk-uses-image', 'PUBLISHED', ['editor' => 'builder', 'builder_layout' => Layout::encode(Layout::normalize(['elements' => [['type' => 'container', 'children' => [['type' => 'image', 'settings' => ['src' => $files['used']['url']]]]]]]))]);
    $folder = DB::insert('media_folders', ['name' => 'Bulk folder']);
    $ids = [(int) $files['used']['id'], (int) $files['unused']['id']];
    dispatch(fake_request('POST', '/admin/media/bulk', ['action' => 'folder:' . $folder, 'ids' => $ids]));
    assert_same(2, (int) DB::value('SELECT COUNT(*) FROM media WHERE folder_id = ?', [$folder]));

    dispatch(fake_request('POST', '/admin/media/bulk', ['action' => 'delete', 'ids' => $ids]));
    assert_true(DB::value('SELECT id FROM media WHERE id = ?', [$ids[0]]) !== null, 'a file a page uses is kept');
    assert_same(null, DB::value('SELECT id FROM media WHERE id = ?', [$ids[1]]));
    dispatch(fake_request('POST', '/admin/media/bulk', ['action' => 'delete-all', 'ids' => [$ids[0]]]));
    assert_same(null, DB::value('SELECT id FROM media WHERE id = ?', [$ids[0]]));
});

test('the recycle bin restores and deletes in bulk; templates delete in bulk', function () use ($page): void {
    sign_in_as('admin');
    $a = $page('bulk-trash-a');
    $b = $page('bulk-trash-b');
    dispatch(fake_request('POST', '/admin/pages/bulk', ['action' => 'delete', 'ids' => [$a, $b]]));
    dispatch(fake_request('POST', '/admin/trash/bulk', ['action' => 'restore:page', 'ids' => [$a]]));
    $restored = DB::first('SELECT * FROM pages WHERE id = ?', [$a]);
    assert_same(null, $restored['deleted_at']);
    assert_same('bulk-trash-a', $restored['slug'], 'the address comes back');
    dispatch(fake_request('POST', '/admin/trash/bulk', ['action' => 'purge:page', 'ids' => [$b]]));
    assert_same(null, DB::value('SELECT id FROM pages WHERE id = ?', [$b]));
    assert_same(404, dispatch(fake_request('POST', '/admin/trash/bulk', ['action' => 'purge:nonsense', 'ids' => [$a]]))->status);

    $t1 = DB::insert('builder_templates', ['name' => 'Bulk tpl 1', 'kind' => 'section', 'layout' => '{"version":1,"elements":[]}']);
    $t2 = DB::insert('builder_templates', ['name' => 'Bulk tpl 2', 'kind' => 'section', 'layout' => '{"version":1,"elements":[]}']);
    dispatch(fake_request('POST', '/admin/builder/templates/bulk', ['action' => 'delete', 'ids' => [$t1, $t2]]));
    assert_same(0, (int) DB::value('SELECT COUNT(*) FROM builder_templates WHERE id IN (?, ?)', [$t1, $t2]));
});
