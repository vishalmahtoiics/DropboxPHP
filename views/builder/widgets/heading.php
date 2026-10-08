<?php
/** @var array $s */
use App\Cms\Ui;

$tag = in_array($s['tag'], ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span'], true) ? $s['tag'] : 'h2';
$text = nl2br(e($s['title']));
if ($s['title'] === '') {
    return;
}
$href = $s['link'] !== '' ? Ui::href($s['link']) : '';
?>
<<?= $tag ?> class="bx-heading<?= $s['size'] ? ' bx-size-' . e($s['size']) : '' ?>"><?= $href !== '' ? '<a href="' . e($href) . '">' . $text . '</a>' : $text ?></<?= $tag ?>>
