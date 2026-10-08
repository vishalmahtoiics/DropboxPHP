<?php
/** @var array $s */
use App\Cms\Icons;
use App\Cms\Ui;

$tag = in_array($s['title_tag'], ['h2', 'h3', 'h4', 'div'], true) ? $s['title_tag'] : 'h3';
$href = $s['link'] !== '' ? Ui::href($s['link']) : '';
$title = e($s['title']);
?>
<div class="bx-iconbox bx-pos-<?= e($s['position']) ?>">
    <?php if ($s['icon']): ?><span class="bx-iconbox-icon"><?= Icons::svg($s['icon'], 'icon', 28) ?></span><?php endif; ?>
    <div class="bx-iconbox-body">
        <?php if ($s['title'] !== ''): ?><<?= $tag ?> class="bx-iconbox-title"><?= $href !== '' ? '<a href="' . e($href) . '">' . $title . '</a>' : $title ?></<?= $tag ?>><?php endif; ?>
        <?php if ($s['text'] !== ''): ?><p class="bx-iconbox-text"><?= nl2br(e($s['text'])) ?></p><?php endif; ?>
    </div>
</div>
