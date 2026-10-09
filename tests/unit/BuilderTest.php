<?php

declare(strict_types=1);

use App\Builder\Controls;
use App\Builder\Elements;
use App\Builder\Layout;
use App\Builder\Style;

test('every builder widget has a template and well-formed controls', function (): void {
    foreach (Elements::all() as $type => $def) {
        if ($type !== 'container' && !isset($def['block'])) {
            assert_true(is_file(BASE_PATH . '/views/builder/widgets/' . $type . '.php'), "template for $type");
        }
        foreach (Elements::controls($type) as $c) {
            assert_true(is_string($c['key']) && $c['key'] !== '', "$type control key");
            assert_true(is_string($c['type']), "$type control type");
            if (isset($c['css'])) {
                foreach ($c['css'] as $sel => $decl) {
                    assert_contains('{{W}}', $sel, "$type.{$c['key']} selector is scoped");
                }
            }
        }
    }
});

test('layouts drop unknown elements and settings, and clean every value', function (): void {
    $layout = Layout::normalize(['elements' => [
        ['id' => 'aaaa1111', 'type' => 'container', 'settings' => [
            'direction' => 'sideways', 'gap' => ['size' => '20', 'unit' => 'px;}body{display:none'],
            '_bg_type' => 'color', '_bg_color' => 'red;}', 'evil' => '<script>',
        ], 'children' => [
            ['id' => 'aaaa1111', 'type' => 'heading', 'settings' => ['title' => '<b onclick=x>Hi</b>', 'tag' => 'script', 'link' => 'javascript:alert(1)']],
            ['type' => 'text', 'settings' => ['content' => '<p onmouseover="x()">ok</p><script>bad()</script><a href="javascript:x">l</a>']],
            ['type' => 'no-such-widget'],
            ['type' => 'icon', 'settings' => ['icon' => '<svg onload=x>']],
        ]],
    ]]);
    $con = $layout['elements'][0];
    assert_false(isset($con['settings']['direction']), 'invalid choice falls back to the default (not stored)');
    assert_same(['size' => 20, 'unit' => 'px'], $con['settings']['gap']);
    assert_false(isset($con['settings']['_bg_color']), 'invalid colour dropped');
    assert_false(isset($con['settings']['evil']));
    assert_same(3, count($con['children']), 'unknown widget removed');
    [$heading, $text, $icon] = $con['children'];
    assert_true($heading['id'] !== 'aaaa1111', 'duplicate ids are replaced');
    assert_same('Hi', $heading['settings']['title']);
    assert_false(isset($heading['settings']['tag']), 'tag not in the allowed list');
    assert_false(isset($heading['settings']['link']), 'javascript: link removed');
    assert_not_contains('onmouseover', $text['settings']['content']);
    assert_not_contains('<script', $text['settings']['content']);
    assert_not_contains('javascript:', $text['settings']['content']);
    assert_same('', $icon['settings']['icon']);
});

test('loose widgets get a container; depth and size are capped', function (): void {
    $layout = Layout::normalize(['elements' => [['type' => 'heading', 'settings' => ['title' => 'Loose']]]]);
    assert_same('container', $layout['elements'][0]['type']);
    assert_same('heading', $layout['elements'][0]['children'][0]['type']);

    $deep = ['type' => 'heading'];
    for ($i = 0; $i < 20; $i++) {
        $deep = ['type' => 'container', 'children' => [$deep]];
    }
    $depth = 0;
    $node = Layout::normalize(['elements' => [$deep]])['elements'][0];
    while (!empty($node['children'])) {
        $depth++;
        $node = $node['children'][0];
    }
    assert_true($depth < Layout::MAX_DEPTH, 'nesting is limited');

    $many = ['type' => 'container', 'children' => array_fill(0, Layout::MAX_ELEMENTS + 50, ['type' => 'spacer'])];
    assert_true(count(Layout::normalize(['elements' => [$many]])['elements'][0]['children']) < Layout::MAX_ELEMENTS);
});

test('settings are stored sparsely; responsive values are separate per device', function (): void {
    $layout = Layout::normalize(['elements' => [['type' => 'container', 'settings' => [
        'content_width' => 'boxed',                          // the default: not stored
        'direction' => 'row', 'direction_mobile' => 'column',
        'direction_tablet' => '',                            // "same as desktop": not stored
        'gap_mobile' => ['size' => 8, 'unit' => 'px'],
    ]]]]);
    $s = $layout['elements'][0]['settings'];
    assert_false(isset($s['content_width']));
    assert_same('row', $s['direction']);
    assert_same('column', $s['direction_mobile']);
    assert_false(isset($s['direction_tablet']));
    assert_same(['size' => 8, 'unit' => 'px'], $s['gap_mobile']);
});

test('styles are scoped to the element, with tablet and mobile media queries', function (): void {
    $layout = Layout::normalize(['elements' => [['id' => 'abcd1234', 'type' => 'container', 'settings' => [
        'direction' => 'row', 'direction_mobile' => 'column',
        '_padding' => ['top' => '40', 'right' => '', 'bottom' => '40', 'left' => '', 'unit' => 'px', 'linked' => false],
        '_padding_tablet' => ['top' => '20', 'right' => '', 'bottom' => '20', 'left' => '', 'unit' => 'px', 'linked' => false],
        '_bg_type' => 'gradient', '_bg_from' => '#0061ff', '_bg_to' => '#00c2ff', '_bg_angle' => 90,
    ], 'children' => [['id' => 'efef5678', 'type' => 'heading', 'settings' => ['color' => '#ff0000', 'font_size' => ['size' => 48, 'unit' => 'px'], 'font_size_mobile' => ['size' => 28, 'unit' => 'px']]]]]]]);
    $css = Style::layout($layout);
    assert_contains('.el-abcd1234>.bx-inner{flex-direction:row', $css);
    assert_contains('.el-abcd1234{padding-top:40px;padding-bottom:40px', $css);
    assert_contains('background-image:linear-gradient(90deg,#0061ff,#00c2ff)', $css);
    assert_contains('.el-efef5678 .bx-heading{font-size:48px}', $css);
    assert_contains('@media (max-width:1024px){.el-abcd1234{padding-top:20px;padding-bottom:20px}}', $css);
    assert_contains('@media (max-width:767px){', $css);
    assert_contains('.el-efef5678 .bx-heading{font-size:28px}', substr($css, (int) strpos($css, '(max-width:767px)')));
    assert_not_contains('}body', $css);
});

test('classic sections convert to a builder layout without losing content', function (): void {
    $layout = Layout::fromSections([
        ['id' => 1, 'block_type' => 'hero', 'is_visible' => 1, 'content' => ['heading' => 'Hello there'], 'settings' => ['preset' => 'dark']],
        ['id' => 2, 'block_type' => 'faq', 'is_visible' => 0, 'content' => [], 'settings' => []],
        ['id' => 3, 'block_type' => 'removed-block', 'is_visible' => 1, 'content' => [], 'settings' => []],
    ]);
    assert_same(2, count($layout['elements']));
    $hero = $layout['elements'][0]['children'][0];
    assert_same('block.hero', $hero['type']);
    assert_same('Hello there', $hero['settings']['heading']);
    assert_same('dark', $hero['settings']['_preset']);
    assert_true($layout['elements'][1]['children'][0]['settings']['_hide_desktop'], 'hidden sections stay hidden');
});

test('controls clean slider, dimension and link values', function (): void {
    $slider = ['type' => 'slider', 'units' => ['px', '%'], 'min' => 0, 'max' => 100, 'default' => ['size' => '', 'unit' => 'px']];
    assert_same(['size' => 100, 'unit' => '%'], Controls::clean($slider, ['size' => 500, 'unit' => '%']));
    assert_same(['size' => '', 'unit' => 'px'], Controls::clean($slider, ['size' => 'abc', 'unit' => 'em']));
    $dims = ['type' => 'dimensions', 'units' => ['px'], 'default' => Controls::dims()];
    $clean = Controls::clean($dims, ['top' => '-5', 'right' => '10', 'bottom' => 'x', 'left' => '', 'unit' => 'vh']);
    assert_same('0', $clean['top'], 'no negative padding');
    assert_same('10', $clean['right']);
    assert_same('', $clean['bottom']);
    assert_same('px', $clean['unit']);
    assert_same('#pricing', Controls::cleanUrl('#pricing'));
    assert_same('/contact', Controls::cleanUrl('/contact'));
    assert_same('', Controls::cleanUrl('javascript:alert(1)'));
    assert_same('', Controls::cleanUrl('//evil.example'));
});

test('custom CSS cannot escape its style element or load outside code', function (): void {
    $css = App\Builder\Code::css("selector{color:red}</style><script>alert(1)</script>@import url(//evil.example/x.css);a{background:url(javascript:alert(1))}x{width:expression(alert(1))}", '.el-ab12cd34');
    assert_contains('.el-ab12cd34{color:red}', $css);
    assert_not_contains('</style', $css);
    assert_not_contains('<script', $css);
    assert_not_contains('@import', $css);
    assert_not_contains('javascript:', $css);
    assert_not_contains('expression(', $css);
});

test('custom HTML: scripts get the page nonce live, and never reach the editor', function (): void {
    $html = '<div class="map"><iframe src="https://maps.example/embed"></iframe></div><script>window.x=1</script><img src=x onerror="alert(1)"><meta http-equiv="refresh" content="0;url=https://evil.example">';
    $live = App\Builder\Code::live($html);
    assert_contains('<script nonce="' . e(nonce()) . '">', $live);
    $editor = App\Builder\Code::editor($html);
    assert_not_contains('<script', $editor);
    assert_not_contains('onerror', $editor);
    assert_not_contains('<meta', $editor);
    assert_contains('<iframe src="https://maps.example/embed">', $editor);
});

test('without the code permission, custom code stays as it was', function (): void {
    $stored = Layout::normalize(['elements' => [['id' => 'cccc0001', 'type' => 'container', 'settings' => ['_custom_css' => 'selector{color:red}'], 'children' => [
        ['id' => 'hhhh0001', 'type' => 'html', 'settings' => ['code' => '<p>Original</p>']],
    ]]], 'settings' => ['custom_css' => 'body{margin:0}']]);
    $incoming = Layout::normalize(['elements' => [['id' => 'cccc0001', 'type' => 'container', 'settings' => ['_custom_css' => 'selector{display:none}', 'gap' => ['size' => 5, 'unit' => 'px']], 'children' => [
        ['id' => 'hhhh0001', 'type' => 'html', 'settings' => ['code' => '<script>steal()</script>']],
        ['id' => 'hhhh0002', 'type' => 'html', 'settings' => ['code' => '<script>new()</script>']],
        ['id' => 'tttt0001', 'type' => 'heading', 'settings' => ['title' => 'Allowed edit', '_custom_css' => 'selector{x:y}']],
    ]]], 'settings' => ['custom_css' => 'body{display:none}']]);
    $kept = Layout::keepCode($incoming, $stored);
    $con = $kept['elements'][0];
    assert_same('selector{color:red}', $con['settings']['_custom_css']);
    assert_same(['size' => 5, 'unit' => 'px'], $con['settings']['gap'], 'ordinary edits still apply');
    assert_same(2, count($con['children']), 'a new HTML widget is dropped');
    assert_same('<p>Original</p>', $con['children'][0]['settings']['code']);
    assert_false(isset($con['children'][1]['settings']['_custom_css']));
    assert_same('Allowed edit', $con['children'][1]['settings']['title']);
    assert_same('body{margin:0}', $kept['settings']['custom_css']);
});

test('layout files round-trip and imports get fresh ids', function (): void {
    $layout = Layout::normalize(['elements' => [['id' => 'abcd1234', 'type' => 'container', 'children' => [
        ['id' => 'ef567890', 'type' => 'heading', 'settings' => ['title' => 'Hello {brand}', 'tag' => 'h1']],
    ]]], 'settings' => ['custom_css' => '.x{color:red}']]);
    $file = App\Builder\Transfer::export($layout, 'page', 'Home page');
    $data = json_decode($file, true);
    assert_same('builder-layout', $data['format']);
    assert_same('page', $data['kind']);
    assert_same('home-page.json', App\Builder\Transfer::filename('Home page'));
    assert_same('layout.json', App\Builder\Transfer::filename('../..'));

    $in = App\Builder\Transfer::parse($file, true);
    assert_same('page', $in['kind']);
    assert_same('Home page', $in['name']);
    assert_same(2, $in['elements']);
    assert_same('Hello {brand}', $in['layout']['elements'][0]['children'][0]['settings']['title'], 'the brand token stays portable');
    assert_same('.x{color:red}', $in['layout']['settings']['custom_css']);
    assert_true($in['layout']['elements'][0]['id'] !== 'abcd1234', 'imported elements never reuse ids from the file');

    // A bare layout or a list of elements works too.
    assert_same(2, App\Builder\Transfer::parse(json_encode(['elements' => $layout['elements']]), true)['elements']);
    assert_same(2, App\Builder\Transfer::parse(json_encode($layout['elements']), true)['elements']);
    assert_same(2, App\Builder\Transfer::parse("\xEF\xBB\xBF" . $file, true)['elements'], 'a byte order mark is ignored');
});

test('layout files are untrusted: bad files are refused and code needs the permission', function (): void {
    foreach (['not json', '{"format":"something-else","layout":{"elements":[]}}', '{"hello":1}', '{"elements":[{"type":"nope"}]}', '{"format":"builder-layout","version":99,"layout":{"elements":[]}}'] as $bad) {
        assert_throws(App\Core\ValidationException::class, fn () => App\Builder\Transfer::parse($bad, true), $bad);
    }
    assert_throws(App\Core\ValidationException::class, fn () => App\Builder\Transfer::parse(str_repeat(' ', Layout::MAX_BYTES + 1), true));

    $file = json_encode(['format' => 'builder-layout', 'version' => 1, 'kind' => 'evil', 'name' => "<b>x</b>\nname", 'layout' => ['elements' => [['type' => 'container', 'children' => [
        ['type' => 'heading', 'settings' => ['title' => 'Kept', 'color' => 'red;}body{display:none', '_custom_css' => 'selector{color:red}']],
        ['type' => 'html', 'settings' => ['code' => '<script>alert(1)</script>']],
    ]]], 'settings' => ['custom_css' => 'body{display:none}']]]);
    $in = App\Builder\Transfer::parse($file, false);
    assert_same(null, $in['kind'], 'unknown kinds are ignored');
    assert_true($in['codeRemoved']);
    $children = $in['layout']['elements'][0]['children'];
    assert_same(1, count($children), 'the HTML widget is dropped');
    assert_same('Kept', $children[0]['settings']['title']);
    assert_true(!isset($children[0]['settings']['color']), 'invalid colours are cleaned');
    assert_true(!isset($children[0]['settings']['_custom_css']));
    assert_true(!isset($in['layout']['settings']), 'page CSS is dropped too');
    assert_not_contains('<', $in['name']);

    $withCode = App\Builder\Transfer::parse($file, true);
    assert_same(2, count($withCode['layout']['elements'][0]['children']));
    assert_true(!$withCode['codeRemoved']);
    assert_throws(App\Core\ValidationException::class, fn () => App\Builder\Transfer::parse('{"elements":[{"type":"container","children":[{"type":"html","settings":{"code":"<b>x</b>"}}]}]}', false), 'nothing left');
});
