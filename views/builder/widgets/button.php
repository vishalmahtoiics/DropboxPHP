<?php
/** @var array $s */
use App\Cms\Icons;
use App\Cms\Ui;

if (trim($s['text']) === '') {
    return;
}
$href = Ui::href($s['link']) ?: '#';
$attrs = '';
if (preg_match('/^#popup-(\d+)$/', $href, $m)) {
    $attrs .= ' data-popup-open="' . (int) $m[1] . '"';
}
if ($s['new_tab']) {
    $attrs .= ' target="_blank" rel="noopener"';
}
if ($s['cta_location'] !== '') {
    $attrs .= ' data-cta-location="' . e($s['cta_location']) . '"';
}
$variant = in_array($s['variant'], ['primary', 'secondary', 'ghost', 'white', 'dark'], true) ? $s['variant'] : 'primary';
$size = ['sm' => 'btn-sm', 'md' => '', 'lg' => 'btn-lg', 'xl' => 'btn-lg bx-btn-xl'][$s['size']] ?? '';
$icon = $s['icon'] ? Icons::svg($s['icon'], 'icon', 18) : '';
?>
<div class="bx-btn-wrap"><a class="btn btn-<?= e($variant) ?> bx-btn <?= e($size) ?>" href="<?= e($href) ?>"<?= $attrs ?>><?= $s['icon_position'] === 'before' ? $icon : '' ?><span><?= e($s['text']) ?></span><?= $s['icon_position'] !== 'before' ? $icon : '' ?></a></div>
