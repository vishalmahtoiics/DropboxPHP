<?php
/** @var array $s */
use App\Cms\Ui;

$href = $s['link'] !== '' ? Ui::href($s['link']) : '';
$img = Ui::img($s['src'], $s['alt']);
$title = e($s['title']);
?>
<div class="bx-imagebox bx-pos-<?= e($s['position']) ?>">
    <?php if ($img !== ''): ?><div class="bx-imagebox-img"><?= $href !== '' ? '<a href="' . e($href) . '">' . $img . '</a>' : $img ?></div><?php endif; ?>
    <div class="bx-imagebox-body">
        <?php if ($s['title'] !== ''): ?><h3 class="bx-imagebox-title"><?= $href !== '' ? '<a href="' . e($href) . '">' . $title . '</a>' : $title ?></h3><?php endif; ?>
        <?php if ($s['text'] !== ''): ?><p class="bx-imagebox-text"><?= nl2br(e($s['text'])) ?></p><?php endif; ?>
    </div>
</div>
