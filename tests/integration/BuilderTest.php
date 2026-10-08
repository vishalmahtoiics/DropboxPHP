<?php

declare(strict_types=1);

use App\Builder\Layout;
use App\Core\DB;

$india = static fn (): int => (int) DB::value("SELECT id FROM countries WHERE code = 'IN'");
$newPage = static function (string $slug, string $status = 'PUBLISHED') use ($india): int {
    return DB::insert('pages', ['country_id' => $india(), 'title' => 'Builder ' . $slug, 'slug' => $slug, 'status' => $status, 'published_at' => $status === 'PUBLISHED' ? now() : null]);
};
$heading = static fn (string $title, string $tag = 'h2') => ['elements' => [['type' => 'container', 'children' => [['type' => 'heading', 'settings' => ['title' => $title, 'tag' => $tag]]]]]];

test('only staff who may edit pages can open, render or save the builder', function () use ($newPage, $heading): void {
    $id = $newPage('bx-perm');
    sign_in_as('sales');
    assert_same(403, dispatch(fake_request('GET', '/admin/builder/page/' . $id))->status);
    assert_same(403, dispatch(fake_request('POST', '/admin/builder/page/' . $id . '/render', ['layout' => $heading('x')]))->status);
    assert_same(403, dispatch(fake_request('POST', '/admin/builder/page/' . $id . '/save', ['layout' => $heading('x'), 'publish' => 1]))->status);
    assert_same(null, DB::value('SELECT builder_layout FROM pages WHERE id = ?', [$id]));

    sign_in_as('content-marketing');
    $editor = dispatch(fake_request('GET', '/admin/builder/page/' . $id));
    assert_same(200, $editor->status);
    assert_contains('id="bx-config"', $editor->body);
    $canvas = dispatch(fake_request('GET', '/admin/builder/page/' . $id . '/canvas'));
    assert_same(200, $canvas->status);
    assert_same('SAMEORIGIN', $canvas->headers['X-Frame-Options'] ?? null, 'the canvas may only be framed by the editor');
    assert_contains("frame-ancestors 'self'", $canvas->headers['Content-Security-Policy'] ?? '');
    assert_same(404, dispatch(fake_request('GET', '/admin/builder/nonsense/' . $id))->status);
});

test('the live render escapes content and marks elements for the editor', function () use ($newPage, $heading): void {
    $id = $newPage('bx-render');
    sign_in_as('content-marketing');
    $res = dispatch(fake_request('POST', '/admin/builder/page/' . $id . '/render', ['layout' => $heading('<img src=x onerror=alert(1)> Safe')]));
    assert_same(200, $res->status);
    $json = json_decode($res->body, true);
    assert_contains('data-el=', $json['html']);
    assert_not_contains('<img src=x', $json['html']);
    assert_contains('Safe', $json['html']);
});

test('drafts stay private until published; publishing keeps a revision', function () use ($newPage, $heading): void {
    $id = $newPage('bx-flow');
    sign_in_as('content-marketing');
    $save = dispatch(fake_request('POST', '/admin/builder/page/' . $id . '/save', ['layout' => $heading('Draft words')]));
    assert_same(200, $save->status);
    $page = DB::first('SELECT * FROM pages WHERE id = ?', [$id]);
    assert_same('sections', $page['editor'], 'a draft does not switch the live page');
    assert_true($page['builder_draft'] !== null);
    assert_not_contains('Draft words', dispatch(fake_request('GET', '/bx-flow'))->body);
    assert_contains('Draft words', dispatch(fake_request('GET', '/preview/page/' . $id))->body, 'previews show the draft');

    dispatch(fake_request('POST', '/admin/builder/page/' . $id . '/save', ['layout' => $heading('Live words', 'h1'), 'publish' => 1]));
    $page = DB::first('SELECT * FROM pages WHERE id = ?', [$id]);
    assert_same('builder', $page['editor']);
    assert_same(null, $page['builder_draft']);
    $live = dispatch(fake_request('GET', '/bx-flow'));
    assert_contains('Live words', $live->body);
    assert_contains('<h1 class="bx-heading">', $live->body);
    assert_not_contains('data-el=', $live->body, 'editor markup never reaches visitors');
    assert_same(1, (int) DB::value("SELECT COUNT(*) FROM builder_revisions WHERE owner_type = 'page' AND owner_id = ?", [$id]));

    $revs = json_decode(dispatch(fake_request('GET', '/admin/builder/page/' . $id . '/revisions'))->body, true);
    assert_same(1, count($revs['items']));
    $rev = json_decode(dispatch(fake_request('GET', '/admin/builder/page/' . $id . '/revisions/' . $revs['items'][0]['id']))->body, true);
    assert_same('Live words', $rev['layout']['elements'][0]['children'][0]['settings']['title']);
});

test('publishing a live page needs the publish permission', function () use ($newPage, $heading): void {
    $id = $newPage('bx-publish-perm');
    $roleId = DB::insert('roles', ['name' => 'Writer', 'slug' => 'writer-' . bin2hex(random_bytes(3)), 'rank' => 50]);
    foreach (['dashboard.view', 'pages.view', 'pages.edit'] as $perm) {
        DB::query('INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE `key` = ?', [$roleId, $perm]);
    }
    $slug = (string) DB::value('SELECT slug FROM roles WHERE id = ?', [$roleId]);
    sign_in_as($slug);
    assert_same(200, dispatch(fake_request('POST', '/admin/builder/page/' . $id . '/save', ['layout' => $heading('Writer draft')]))->status, 'drafts are fine');
    assert_same(403, dispatch(fake_request('POST', '/admin/builder/page/' . $id . '/save', ['layout' => $heading('Writer live'), 'publish' => 1]))->status);
    assert_same(null, DB::value('SELECT builder_layout FROM pages WHERE id = ?', [$id]));
});

test('switching a page to the builder copies its sections into a draft', function () use ($newPage): void {
    $id = $newPage('bx-convert');
    DB::insert('page_sections', ['page_id' => $id, 'block_type' => 'hero', 'sort_order' => 0, 'content' => App\Cms\Blocks::normalize('hero', ['heading' => 'Converted hero'] + App\Cms\Blocks::defaults('hero')), 'settings' => App\Cms\Design::defaults()]);
    sign_in_as('content-marketing');
    $res = dispatch(fake_request('POST', '/admin/pages/' . $id . '/builder'));
    assert_same(303, $res->status);
    $draft = Layout::decode((string) DB::value('SELECT builder_draft FROM pages WHERE id = ?', [$id]));
    assert_same('block.hero', $draft['elements'][0]['children'][0]['type']);
    assert_same('Converted hero', $draft['elements'][0]['children'][0]['settings']['heading']);
    assert_contains('Converted hero', dispatch(fake_request('GET', '/bx-convert'))->body, 'the live page is unchanged until publishing');
});

test('product pages follow the default template, a chosen one, or their own layout', function () use ($india): void {
    sign_in_as('admin');
    $pid = DB::insert('products', ['name' => 'Builder Plan', 'slug' => 'builder-plan', 'status' => 'PUBLISHED', 'published_at' => now(), 'currency' => 'INR', 'annual_price' => '999.00']);
    DB::insert('product_countries', ['product_id' => $pid, 'country_id' => $india(), 'status' => 'PUBLISHED', 'published_at' => now(), 'currency' => 'INR', 'annual_price' => '999.00']);
    $classic = dispatch(fake_request('GET', '/products/builder-plan'));
    assert_same(200, $classic->status);
    assert_not_contains('bx-root', $classic->body, 'no template yet: the classic page');

    $tpl = static function (string $name, string $marker, int $default) {
        return DB::insert('builder_templates', ['name' => $name, 'kind' => 'product', 'is_default' => $default, 'layout' => Layout::encode(Layout::normalize(['elements' => [['type' => 'container', 'children' => [
            ['type' => 'product_title'], ['type' => 'heading', 'settings' => ['title' => $marker]],
        ]]]]))]);
    };
    $default = $tpl('Default product', 'FROM-DEFAULT', 1);
    $other = $tpl('Other product', 'FROM-OTHER', 0);
    $page = dispatch(fake_request('GET', '/products/builder-plan'))->body;
    assert_contains('FROM-DEFAULT', $page);
    assert_contains('<h1 class="bx-product-title">Builder Plan</h1>', $page);

    DB::update('products', ['template_id' => $other], ['id' => $pid]);
    assert_contains('FROM-OTHER', dispatch(fake_request('GET', '/products/builder-plan'))->body);

    DB::update('products', ['page_layout' => 'classic'], ['id' => $pid]);
    assert_not_contains('FROM-', dispatch(fake_request('GET', '/products/builder-plan'))->body);

    dispatch(fake_request('POST', '/admin/builder/product/' . $pid . '/save', ['layout' => ['elements' => [['type' => 'container', 'children' => [['type' => 'heading', 'settings' => ['title' => 'OWN-LAYOUT']]]]]], 'publish' => 1]));
    assert_same('builder', DB::value('SELECT page_layout FROM products WHERE id = ?', [$pid]));
    assert_contains('OWN-LAYOUT', dispatch(fake_request('GET', '/products/builder-plan'))->body);
    DB::query('DELETE FROM builder_templates WHERE id IN (?, ?)', [$default, $other]);
});

test('templates: save a selection, insert it with fresh ids', function () use ($heading): void {
    sign_in_as('content-marketing');
    $res = dispatch(fake_request('POST', '/admin/builder/templates/from-selection', ['name' => 'Hero band', 'kind' => 'section', 'layout' => $heading('Reusable')]));
    assert_same(200, $res->status);
    $id = json_decode($res->body, true)['id'];
    $stored = Layout::decode((string) DB::value('SELECT layout FROM builder_templates WHERE id = ?', [$id]));
    $out = json_decode(dispatch(fake_request('GET', '/admin/builder/templates/' . $id . '/elements'))->body, true);
    assert_same('Reusable', $out['elements'][0]['children'][0]['settings']['title']);
    assert_true($out['elements'][0]['id'] !== $stored['elements'][0]['id'], 'inserted copies get new ids');
    sign_in_as('sales');
    assert_same(403, dispatch(fake_request('POST', '/admin/builder/templates/from-selection', ['name' => 'X', 'layout' => $heading('x')]))->status);
});

test('the city generator fills placeholders inside builder layouts', function () use ($newPage, $india): void {
    $tplPage = $newPage('bx-city-template', 'DRAFT');
    DB::update('pages', ['editor' => 'builder', 'builder_layout' => Layout::encode(Layout::normalize(['elements' => [['type' => 'container', 'children' => [['type' => 'heading', 'settings' => ['title' => 'Dropbox in {{city.name}}']]]]]]))], ['id' => $tplPage]);
    $city = DB::insert('cities', ['country_id' => $india(), 'name' => 'Pune', 'slug' => 'pune']);
    $user = sign_in_as('admin');
    $out = App\Services\Cities::generate($tplPage, [$city], '{{city.slug}}', '', true, (int) $user['id']);
    assert_same(1, count($out['created']));
    $layout = Layout::decode((string) DB::value('SELECT builder_layout FROM pages WHERE id = ?', [$out['created'][0]['id']]));
    assert_same('Dropbox in Pune', $layout['elements'][0]['children'][0]['settings']['title']);
    assert_contains('Dropbox in Pune', dispatch(fake_request('GET', '/pune'))->body);
});
