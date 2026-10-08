<?php use App\Cms\Icons; use App\Cms\Ui; use App\Core\Sanitizer; ?>
<div class="container split<?= $c['image_side'] === 'left' ? ' image-left' : '' ?>">
    <div class="split-content">
        <?php if ($c['eyebrow']): ?><p class="eyebrow"><?= e($c['eyebrow']) ?></p><?php endif; ?>
        <?php if ($c['heading']): ?><<?= $h ?> class="section-title"><?= e($c['heading']) ?></<?= $h ?>><?php endif; ?>
        <div class="prose"><?= Sanitizer::html($c['body']) ?></div>
        <?php if ($c['bullets']): ?>
            <ul class="check-list"><?php foreach ($c['bullets'] as $b): ?><li><?= Icons::svg($c['bullet_icon'] ?: 'check-circle', 'icon', 20) ?><span><?= e($b) ?></span></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?= Ui::buttons($c) ?>
    </div>
    <div class="split-media">
        <?php if ($c['image']): ?>
            <?= Ui::img($c['image'], $c['image_alt'], $c['image_rounded'] ? 'rounded' : '', !$isFirst) ?>
        <?php else: ?>
            <div class="media-placeholder"><?= Icons::svg('image', 'icon', 40) ?></div>
        <?php endif; ?>
    </div>
</div>
