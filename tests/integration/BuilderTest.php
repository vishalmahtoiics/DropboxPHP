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

test('custom HTML needs the code permission; with it, scripts run on the live page', function () use ($newPage): void {
    $id = $newPage('bx-code');
    $layout = ['elements' => [['type' => 'container', 'children' => [['type' => 'html', 'settings' => ['code' => '<div id="hello">Hi</div><script>window.hello=1</script>']]]]]];
    sign_in_as('content-marketing'); // no builder.code
    dispatch(fake_request('POST', '/admin/builder/page/' . $id . '/save', ['layout' => $layout, 'publish' => 1]));
    assert_not_contains('window.hello', dispatch(fake_request('GET', '/bx-code'))->body, 'dropped without the permission');

    sign_in_as('admin');
    $render = json_decode(dispatch(fake_request('POST', '/admin/builder/page/' . $id . '/render', ['layout' => $layout]))->body, true);
    assert_not_contains('<script', $render['html'], 'scripts never run in the editor');
    dispatch(fake_request('POST', '/admin/builder/page/' . $id . '/save', ['layout' => $layout, 'publish' => 1]));
    $live = dispatch(fake_request('GET', '/bx-code'))->body;
    assert_contains('<div id="hello">Hi</div>', $live);
    assert_true((bool) preg_match('/<script nonce="[^"]+">window\.hello=1<\/script>/', $live), 'the script carries the page nonce');
});

test('an active header and footer template replace the built-in ones', function () use ($newPage, $india): void {
    $id = $newPage('bx-chrome');
    sign_in_as('admin');
    $nav = DB::insert('navigations', ['country_id' => $india(), 'name' => 'Header', 'location' => 'HEADER']);
    DB::insert('navigation_items', ['navigation_id' => $nav, 'label' => 'Pricing link', 'url' => '/pricing', 'link_type' => 'INTERNAL', 'sort_order' => 1]);
    $before = dispatch(fake_request('GET', '/bx-chrome'))->body;
    assert_contains('class="site-header', $before);

    $res = dispatch(fake_request('POST', '/admin/builder/templates', ['name' => 'Main header', 'kind' => 'header']));
    assert_same(303, $res->status);
    $hid = (int) DB::value("SELECT id FROM builder_templates WHERE kind = 'header' ORDER BY id DESC LIMIT 1");
    assert_same(0, (int) DB::value('SELECT is_default FROM builder_templates WHERE id = ?', [$hid]), 'not live until made active');
    dispatch(fake_request('POST', '/admin/builder/templates/' . $hid, ['action' => 'default']));
    App\Builder\Chrome::reset();
    $after = dispatch(fake_request('GET', '/bx-chrome'))->body;
    assert_not_contains('class="site-header', $after);
    assert_contains('bx-site-header', $after);
    assert_contains('Pricing link', $after, 'the menu widget shows the market menu');
    assert_contains('data-bx-nav-toggle', $after);

    dispatch(fake_request('POST', '/admin/builder/templates', ['name' => 'Main footer', 'kind' => 'footer']));
    $fid = (int) DB::value("SELECT id FROM builder_templates WHERE kind = 'footer' ORDER BY id DESC LIMIT 1");
    dispatch(fake_request('POST', '/admin/builder/templates/' . $fid, ['action' => 'default']));
    App\Builder\Chrome::reset();
    $page = dispatch(fake_request('GET', '/bx-chrome'))->body;
    assert_contains('bx-site-footer', $page);
    assert_contains('© ' . date('Y'), $page);

    sign_in_as('sales');
    assert_same(403, dispatch(fake_request('GET', '/admin/builder/template/' . $hid))->status);
    sign_in_as('admin');
    dispatch(fake_request('POST', '/admin/builder/templates/' . $hid, ['action' => 'undefault']));
    dispatch(fake_request('POST', '/admin/builder/templates/' . $fid, ['action' => 'undefault']));
    App\Builder\Chrome::reset();
    assert_contains('class="site-header', dispatch(fake_request('GET', '/bx-chrome'))->body);
});

test('the builder settings dialog changes the address and keeps the old one working', function () use ($newPage): void {
    $id = $newPage('bx-old-address');
    $other = $newPage('bx-taken');
    sign_in_as('content-marketing');
    $taken = dispatch(fake_request('POST', '/admin/builder/page/' . $id . '/settings', ['title' => 'Renamed', 'slug' => 'bx-taken', 'show_header' => 1, 'show_footer' => 1]));
    assert_same(302, $taken->status, 'an address in use is refused (JSON callers get 422)');
    assert_same('bx-old-address', DB::value('SELECT slug FROM pages WHERE id = ?', [$id]));
    $res = dispatch(fake_request('POST', '/admin/builder/page/' . $id . '/settings', ['title' => 'Renamed page', 'slug' => 'bx-new-address', 'seo_title' => 'New SEO title', 'show_header' => 1, 'show_footer' => 0]));
    assert_same(200, $res->status);
    $json = json_decode($res->body, true);
    assert_contains('redirects', $json['message']);
    assert_contains('/bx-new-address', $json['settings']['url']);
    $page = DB::first('SELECT * FROM pages WHERE id = ?', [$id]);
    assert_same('bx-new-address', $page['slug']);
    assert_same('New SEO title', $page['seo_title']);
    assert_same(0, (int) $page['show_footer']);
    $old = dispatch(fake_request('GET', '/bx-old-address'));
    assert_same(301, $old->status);
    assert_same('/bx-new-address', $old->headers['Location']);
    unset($other);
});

test('renaming a media file moves it, updates every use and redirects the old address', function () use ($newPage): void {
    sign_in_as('content-marketing');
    $tmp = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($tmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    $key = date('Y/m') . '/img-' . bin2hex(random_bytes(4)) . '.png';
    [$provider, $url] = App\Services\Storage::put($tmp, $key, 'image/png');
    @unlink($tmp);
    $mid = DB::insert('media', ['filename' => basename($key), 'storage_key' => $key, 'url' => $url, 'provider' => $provider, 'mime_type' => 'image/png', 'kind' => 'IMAGE', 'size' => 68]);
    $pid = $newPage('bx-media');
    DB::update('pages', ['editor' => 'builder', 'builder_layout' => Layout::encode(Layout::normalize(['elements' => [['type' => 'container', 'children' => [['type' => 'image', 'settings' => ['src' => $url, 'alt' => 'Plan']]]]]]))], ['id' => $pid]);
    DB::insert('page_sections', ['page_id' => $pid, 'block_type' => 'imageWidget', 'sort_order' => 0, 'content' => ['image' => $url], 'settings' => App\Cms\Design::defaults()]);

    $res = dispatch(fake_request('POST', '/admin/media/' . $mid . '/rename', ['name' => 'Dropbox Business Plan']));
    assert_same(303, $res->status);
    $media = DB::first('SELECT * FROM media WHERE id = ?', [$mid]);
    assert_same(dirname($key) . '/dropbox-business-plan.png', $media['storage_key']);
    assert_true(App\Services\Storage::localPath($media['storage_key']) !== null, 'the file moved');
    assert_same(null, App\Services\Storage::localPath($key));
    assert_contains($media['url'], (string) DB::value('SELECT builder_layout FROM pages WHERE id = ?', [$pid]));
    assert_not_contains($url, (string) DB::value('SELECT builder_layout FROM pages WHERE id = ?', [$pid]));
    assert_not_contains(basename($key), (string) DB::value('SELECT content FROM page_sections WHERE page_id = ?', [$pid]));
    $old = dispatch(fake_request('GET', $url));
    assert_same(301, $old->status);
    assert_same($media['url'], $old->headers['Location']);
    assert_same(200, dispatch(fake_request('GET', $media['url']))->status);
    App\Services\Storage::delete('local', $media['storage_key']);
});

test('copy a classic section and paste it on another page, or into the builder', function () use ($newPage): void {
    $from = $newPage('bx-copy-from');
    $to = $newPage('bx-copy-to');
    $sid = DB::insert('page_sections', ['page_id' => $from, 'block_type' => 'hero', 'sort_order' => 0, 'content' => App\Cms\Blocks::normalize('hero', ['heading' => 'Copied hero'] + App\Cms\Blocks::defaults('hero')), 'settings' => App\Cms\Design::normalize(['preset' => 'dark'])]);
    sign_in_as('content-marketing');
    $copy = json_decode(dispatch(fake_request('GET', '/admin/sections/' . $sid . '/clipboard'))->body, true);
    assert_same('container', $copy['element']['type']);
    assert_same('block.hero', $copy['element']['children'][0]['type']);
    assert_same('Copied hero', $copy['element']['children'][0]['settings']['heading']);

    // Classic editor of another page.
    $res = dispatch(fake_request('POST', '/admin/pages/' . $to . '/sections/paste', ['element' => $copy['element']]));
    assert_same(200, $res->status);
    $row = DB::first('SELECT * FROM page_sections WHERE page_id = ?', [$to]);
    assert_same('hero', $row['block_type']);
    assert_same('Copied hero', json_decode((string) $row['content'], true)['heading']);
    assert_same('dark', json_decode((string) $row['settings'], true)['preset'], 'the design comes along');
    assert_contains('Copied hero', dispatch(fake_request('GET', '/bx-copy-to'))->body);

    // The same clipboard pastes into the builder (it is a builder element).
    $layout = ['elements' => [$copy['element']]];
    $render = json_decode(dispatch(fake_request('POST', '/admin/builder/page/' . $to . '/render', ['layout' => $layout]))->body, true);
    assert_contains('Copied hero', $render['html']);

    // Builder-only widgets cannot go into the classic editor.
    $widget = ['type' => 'container', 'children' => [['type' => 'heading', 'settings' => ['title' => 'Builder only']]]];
    $refused = dispatch(fake_request('POST', '/admin/pages/' . $to . '/sections/paste', ['element' => $widget]));
    assert_same(302, $refused->status);
    assert_same(1, (int) DB::value('SELECT COUNT(*) FROM page_sections WHERE page_id = ?', [$to]));

    sign_in_as('sales');
    assert_same(403, dispatch(fake_request('POST', '/admin/pages/' . $to . '/sections/paste', ['element' => $copy['element']]))->status);
});

test('opening the builder straight on a sections page shows its sections', function () use ($newPage): void {
    $id = $newPage('bx-direct');
    DB::insert('page_sections', ['page_id' => $id, 'block_type' => 'cta', 'sort_order' => 0, 'content' => App\Cms\Blocks::normalize('cta', ['heading' => 'Direct CTA'] + App\Cms\Blocks::defaults('cta')), 'settings' => App\Cms\Design::defaults()]);
    sign_in_as('content-marketing');
    $body = dispatch(fake_request('GET', '/admin/builder/page/' . $id))->body;
    preg_match('#<script type="application/json" id="bx-config">(.*?)</script>#s', $body, $m);
    $cfg = json_decode($m[1], true);
    assert_same('block.cta', $cfg['layout']['elements'][0]['children'][0]['type']);
    assert_same('Direct CTA', $cfg['layout']['elements'][0]['children'][0]['settings']['heading']);
});
