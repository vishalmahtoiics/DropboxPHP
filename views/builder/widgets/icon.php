<?php
/** @var array $s */
use App\Cms\Icons;
use App\Cms\Ui;

$svg = Icons::svg($s['icon'], 'icon', 40, (string) $s['label']);
if ($svg === '') {
    return;
}
$cls = 'bx-icon bx-icon-' . e($s['view']) . ($s['view'] !== 'default' ? ' bx-shape-' . e($s['shape']) : '');
$href = $s['link'] !== '' ? Ui::href($s['link']) : '';
?>
<?= $href !== '' ? '<a class="' . $cls . '" href="' . e($href) . '">' . $svg . '</a>' : '<span class="' . $cls . '">' . $svg . '</span>' ?>
