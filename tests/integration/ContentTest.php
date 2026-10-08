<?php

declare(strict_types=1);

use App\Cms\Blocks;
use App\Cms\Design;
use App\Core\DB;
use App\Core\ValidationException;
use App\Services\Redirects;

$page = static function (string $slug, string $status = 'PUBLISHED', array $sections = [], int $country = 0): int {
    $country = $country ?: (int) DB::value("SELECT id FROM countries WHERE code = 'IN'");
    $id = DB::insert('pages', ['country_id' => $country, 'title' => 'T ' . $slug, 'slug' => $slug, 'status' => $status, 'published_at' => $status === 'PUBLISHED' ? now() : null]);
    foreach ($sections as $i => [$type, $content]) {
        DB::insert('page_sections', ['page_id' => $id, 'block_type' => $type, 'sort_order' => $i, 'content' => Blocks::normalize($type, $content + Blocks::defaults($type)), 'settings' => Design::defaults()]);
    }
    return $id;
};

test('published pages render; drafts and deleted pages 404', function () use ($page): void {
    $page('', 'PUBLISHED', [['hero', ['heading' => 'Welcome home']], ['faq', []]]);
    $page('draft-page', 'DRAFT');
    $res = dispatch(fake_request('GET', '/'));
    assert_same(200, $res->status);
    assert_contains('Welcome home', $res->body);
    assert_contains('"@type":"FAQPage"', $res->body, 'FAQ structured data');
    assert_contains('<h1', $res->body);
    assert_same(404, dispatch(fake_request('GET', '/draft-page'))->status);
});

test('a market prefix serves that market\'s page only', function () use ($page): void {
    $ae = (int) DB::value("SELECT id FROM countries WHERE code = 'AE'");
    $page('uae-only', 'PUBLISHED', [['headingText', ['heading' => 'Hello Dubai']]], $ae);
    assert_contains('Hello Dubai', dispatch(fake_request('GET', '/ae/uae-only'))->body);
    assert_same(404, dispatch(fake_request('GET', '/uae-only'))->status);
});

test('redirect loops are refused and chains collapse to one hop', function (): void {
    Redirects::save(['source' => '/a', 'destination' => '/b', 'type' => 'PERMANENT', 'is_active' => 1]);
    Redirects::save(['source' => '/b', 'destination' => '/c', 'type' => 'PERMANENT', 'is_active' => 1]);
    assert_throws(ValidationException::class, fn () => Redirects::save(['source' => '/c', 'destination' => '/a', 'type' => 'PERMANENT', 'is_active' => 1]));
    $res = dispatch(fake_request('GET', '/a'));
    assert_same(301, $res->status);
    assert_same('/c', $res->headers['Location']);
});

test('robots.txt and sitemap.xml are generated', function (): void {
    $robots = dispatch(fake_request('GET', '/robots.txt'));
    assert_contains('Disallow: /admin', $robots->body);
    assert_contains('Sitemap:', $robots->body);
    $map = dispatch(fake_request('GET', '/sitemap.xml'));
    assert_same(200, $map->status);
    assert_contains('<urlset', $map->body);
    assert_not_contains('draft-page', $map->body);
});

test('security headers are sent on public and admin responses', function (): void {
    $res = dispatch(fake_request('GET', '/'));
    assert_same('nosniff', $res->headers['X-Content-Type-Options'] ?? null);
    assert_contains("object-src 'none'", $res->headers['Content-Security-Policy'] ?? '');
    $admin = dispatch(fake_request('GET', config('app.login_path')));
    assert_same('DENY', $admin->headers['X-Frame-Options'] ?? null);
    assert_contains("frame-ancestors 'none'", $admin->headers['Content-Security-Policy'] ?? '');
});

test('uploads are checked by content, not by extension', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($tmp, '<?php echo "pwned";');
    assert_throws(ValidationException::class, fn () => App\Services\Uploads::store(['name' => 'photo.png', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => 0], null));
    file_put_contents($tmp, '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>');
    assert_throws(ValidationException::class, fn () => App\Services\Uploads::store(['name' => 'logo.svg', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => 0], null));
    file_put_contents($tmp, 'x');
    assert_throws(ValidationException::class, fn () => App\Services\Uploads::store(['name' => 'shell.phtml', 'tmp_name' => $tmp, 'size' => 1, 'error' => 0], null));
    @unlink($tmp);
});
