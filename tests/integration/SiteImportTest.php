<?php

declare(strict_types=1);

use App\Builder\Layout;
use App\Builder\Transfer;
use App\Core\DB;
use App\Core\Request;

/** Build a site package in a temp file. @param array<string,string> $files */
$package = static function (array $manifest, array $files): string {
    $path = tempnam(sys_get_temp_dir(), 'pkg') . '.zip';
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('manifest.json', json_encode($manifest));
    foreach ($files as $name => $data) {
        $zip->addFromString($name, $data);
    }
    $zip->close();
    return $path;
};
$upload = static function (string $zip, array $body = []): App\Core\Response {
    $req = fake_request('POST', '/admin/site-import', $body + ['existing' => 'draft', 'templates' => '1', 'menus' => '1', 'redirects' => '1']);
    return dispatch(new Request('POST', $req->path, [], $req->body, ['package' => ['name' => 'site.zip', 'type' => 'application/zip', 'tmp_name' => $zip, 'size' => filesize($zip), 'error' => UPLOAD_ERR_OK]], $req->server, []));
};
// A 1x1 PNG.
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
// Package layouts are written raw: "pkg:media/…" only becomes a real address on import.
$layout = static fn (array $elements) => json_encode(['format' => 'builder-layout', 'version' => 1, 'kind' => 'page', 'name' => 'x', 'layout' => ['version' => 1, 'elements' => $elements]]);

test('a site package becomes draft pages with their images, forms, templates, menus and redirects', function () use ($package, $upload, $png, $layout): void {
    $india = (int) DB::value("SELECT id FROM countries WHERE code = 'IN'");
    DB::insert('pages', ['country_id' => $india, 'title' => 'Already here', 'slug' => 'imp-existing', 'status' => 'PUBLISHED', 'published_at' => now()]);
    $zip = $package([
        'format' => 'site-package', 'version' => 1, 'name' => 'example.com',
        'pages' => [
            ['file' => 'pages/about.json', 'title' => 'About us', 'slug' => 'imp-about', 'seo_title' => 'About | Example', 'seo_description' => 'Who we are.'],
            ['file' => 'pages/existing.json', 'title' => 'Already here', 'slug' => 'imp-existing'],
            ['file' => 'pages/about.json', 'title' => 'Blog', 'slug' => 'blog'],
            ['file' => '../evil.json', 'title' => 'Evil', 'slug' => 'imp-evil'],
        ],
        'templates' => [['file' => 'templates/header.json', 'kind' => 'header', 'name' => 'Imported header']],
        'forms' => [['slug' => 'imp-get-in-touch', 'name' => 'Get In Touch', 'submit_label' => 'Send', 'fields' => [
            ['type' => 'NAME', 'label' => 'Name', 'name' => 'name', 'placeholder' => 'Name', 'required' => true],
            ['type' => 'EMAIL', 'label' => 'Email', 'name' => 'email', 'required' => true],
            ['type' => 'BOGUS', 'label' => 'Nope'],
        ]]],
        'menus' => ['HEADER' => [['label' => 'Home', 'url' => '/'], ['label' => 'Firewall', 'url' => '/imp-about', 'children' => [['label' => 'Sophos', 'url' => '/sophos']]], ['label' => 'Bad', 'url' => 'javascript:alert(1)']]],
        'redirects' => [['from' => '/old-about', 'to' => '/imp-about'], ['from' => 'no-slash', 'to' => '/x']],
    ], [
        'pages/about.json' => $layout([['type' => 'container', 'children' => [
            ['type' => 'heading', 'settings' => ['title' => 'About heading']],
            ['type' => 'image', 'settings' => ['src' => 'pkg:media/2023/team.png', 'alt' => 'Team']],
            ['type' => 'text', 'settings' => ['content' => '<p><img src="pkg:media/2023/team.png" alt=""> Missing: <img src="pkg:media/nope.png" alt=""></p>']],
            ['type' => 'form', 'settings' => ['form' => 'imp-get-in-touch']],
        ]]]),
        'pages/existing.json' => $layout([['type' => 'container', 'children' => [['type' => 'heading', 'settings' => ['title' => 'Imported over existing']]]]]),
        'templates/header.json' => Transfer::export(['elements' => [['type' => 'container', 'children' => [['type' => 'site_logo', 'settings' => []]]]]], 'header', 'Imported header'),
        'media/2023/team.png' => $png,
        'media/2023/not-an-image.png' => '<?php echo 1;',
    ]);
    sign_in_as('admin');
    $res = $upload($zip);
    unlink($zip);
    assert_same(200, $res->status, substr($res->body, 0, 300));
    assert_contains('Site imported', $res->body);

    $about = DB::first("SELECT * FROM pages WHERE country_id = ? AND slug = 'imp-about'", [$india]);
    assert_same('DRAFT', $about['status'], 'imported pages are never live by themselves');
    assert_same('builder', $about['editor']);
    assert_same('About | Example', $about['seo_title']);
    $json = (string) $about['builder_layout'];
    $url = (string) DB::value("SELECT url FROM media WHERE filename = 'team.png' ORDER BY id DESC LIMIT 1");
    assert_true($url !== '');
    assert_contains(str_replace('/', '\/', $url), json_encode(Layout::decode($json)), 'image addresses point at the uploaded file');
    assert_not_contains('pkg:', $json);
    assert_same(null, DB::value("SELECT id FROM media WHERE filename = 'not-an-image.png'"), 'files that are not real images are refused');
    assert_contains('not-an-image.png', $res->body, 'and reported');

    $existing = DB::first("SELECT * FROM pages WHERE country_id = ? AND slug = 'imp-existing'", [$india]);
    assert_same('PUBLISHED', $existing['status']);
    assert_contains('Imported over existing', (string) $existing['builder_draft'], 'an existing page gets the import as its draft');
    assert_not_contains('Imported over existing', dispatch(fake_request('GET', '/imp-existing'))->body, 'its live version is unchanged');

    assert_same(null, DB::value("SELECT id FROM pages WHERE country_id = ? AND slug = 'blog'", [$india]), 'reserved addresses are skipped');
    assert_same(null, DB::value("SELECT id FROM pages WHERE slug = 'imp-evil'"), 'paths outside the package are never read');

    $form = DB::first("SELECT * FROM forms WHERE slug = 'imp-get-in-touch'");
    assert_same('Send', $form['submit_label']);
    assert_same(2, (int) DB::value('SELECT COUNT(*) FROM form_fields WHERE form_id = ?', [$form['id']]), 'unknown field types are dropped');

    $tpl = DB::first("SELECT * FROM builder_templates WHERE name = 'Imported header'");
    assert_same('header', $tpl['kind']);
    assert_same(0, (int) $tpl['is_default'], 'the header is not made active');

    $nav = (int) DB::value("SELECT id FROM navigations WHERE country_id = ? AND location = 'HEADER'", [$india]);
    $items = DB::all('SELECT label, url, parent_id FROM navigation_items WHERE navigation_id = ? ORDER BY id', [$nav]);
    assert_same(['Home', 'Firewall', 'Sophos', 'Bad'], array_column($items, 'label'));
    assert_true($items[2]['parent_id'] !== null, 'sub-menus keep their parent');
    assert_same(null, $items[3]['url'], 'unsafe links are dropped');

    assert_same('/imp-about', DB::value("SELECT destination FROM redirects WHERE source = '/old-about'"));
});

test('site import needs page and media permissions and a real package', function () use ($package, $upload): void {
    sign_in_as('sales');
    assert_same(403, dispatch(fake_request('GET', '/admin/site-import'))->status);
    sign_in_as('admin');
    assert_same(200, dispatch(fake_request('GET', '/admin/site-import'))->status);
    $zip = $package(['format' => 'something-else'], []);
    $res = $upload($zip);
    unlink($zip);
    assert_same(302, $res->status, 'a bad package is refused with a message');
    $tmp = tempnam(sys_get_temp_dir(), 'nz');
    file_put_contents($tmp, 'not a zip');
    assert_same(302, $upload($tmp)->status);
    unlink($tmp);
});
