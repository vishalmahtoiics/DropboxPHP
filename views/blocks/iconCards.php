<?php use App\Cms\Icons; use App\Cms\Ui; ?>
<div class="container">
    <?= Ui::head($c, $h) ?>
    <div class="grid cols-<?= (int) $c['columns'] ?> icon-cards icon-<?= e($c['icon_style']) ?>">
        <?php foreach ($c['items'] as $item): ?>
            <?php $tag = $item['link_url'] ? 'a' : 'div'; ?>
            <<?= $tag ?> class="icon-card"<?= $item['link_url'] ? ' href="' . e(Ui::href($item['link_url'])) . '"' : '' ?>>
                <?php if ($item['icon']): ?><span class="icon-card-icon"><?= Icons::svg($item['icon'], 'icon', 28) ?></span><?php endif; ?>
                <h3><?= e($item['title']) ?></h3>
                <?php if ($item['text']): ?><p><?= nl2br(e($item['text'])) ?></p><?php endif; ?>
            </<?= $tag ?>>
        <?php endforeach; ?>
    </div>
</div>
