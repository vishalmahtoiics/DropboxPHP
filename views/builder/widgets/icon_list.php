<?php
/** @var array $s */
use App\Cms\Icons;
use App\Cms\Ui;

if (!$s['items']) {
    return;
}
?>
<ul class="bx-iconlist bx-<?= e($s['layout']) ?>">
    <?php foreach ($s['items'] as $item): ?>
        <?php $href = $item['link'] !== '' ? Ui::href($item['link']) : ''; $body = Icons::svg($item['icon'], 'icon', 18) . '<span>' . e($item['text']) . '</span>'; ?>
        <li><?= $href !== '' ? '<a href="' . e($href) . '">' . $body . '</a>' : $body ?></li>
    <?php endforeach; ?>
</ul>
