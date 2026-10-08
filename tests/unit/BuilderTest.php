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
