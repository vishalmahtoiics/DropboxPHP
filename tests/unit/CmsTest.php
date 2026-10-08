<?php

declare(strict_types=1);

use App\Cms\Blocks;
use App\Cms\Design;

test('every block has defaults that survive normalisation', function (): void {
    foreach (array_keys(Blocks::all()) as $type) {
        $defaults = Blocks::defaults($type);
        assert_same($defaults, Blocks::normalize($type, $defaults), "Block $type");
    }
});

test('block normalisation drops unknown keys and coerces types', function (): void {
    $c = Blocks::normalize('featureGrid', [
        'heading' => str_repeat('x', 500), 'columns' => '9', 'evil' => '<script>', 'style' => 'cards',
        'items' => [['icon' => 'not-an-icon', 'title' => '<b>T</b>', 'link_url' => 'javascript:alert(1)']],
    ]);
    assert_false(isset($c['evil']));
    assert_same(240, mb_strlen($c['heading']));
    assert_same('3', $c['columns'], 'Invalid select falls back to the default');
    assert_same('', $c['items'][0]['icon']);
    assert_same('T', $c['items'][0]['title']);
    assert_same('', $c['items'][0]['link_url']);
});

test('rich text in blocks is sanitised', function (): void {
    $c = Blocks::normalize('richText', ['body' => '<p>ok</p><script>bad()</script>']);
    assert_same('<p>ok</p>', $c['body']);
});

test('section design emits CSS only for what is set', function (): void {
    $d = Design::normalize([]);
    $r = Design::render($d, 's1');
    assert_same('', $r['style']);
    assert_same('', $r['css']);
    $d = Design::normalize(['desktop' => ['padding_top' => '60'], 'mobile' => ['padding_top' => '2rem'], 'anchor' => 'Pricing!', 'bg_type' => 'color', 'bg_color' => '#fff;}']);
    $r = Design::render($d, 's1');
    assert_contains('--s-pt:60px', $r['style']);
    assert_contains('@media (max-width:640px)', $r['css']);
    assert_same('', $r['id'], 'Invalid anchors are dropped');
    assert_not_contains('--s-bg', $r['style'], 'An invalid colour never reaches CSS');
});
