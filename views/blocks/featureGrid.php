<?php use App\Cms\Icons; use App\Cms\Ui; ?>
<div class="container">
    <?= Ui::head($c, $h, $c['align']) ?>
    <div class="grid cols-<?= (int) $c['columns'] ?> features style-<?= e($c['style']) ?>">
        <?php foreach ($c['items'] as $item): ?>
            <article class="feature">
                <?php if ($item['icon']): ?><span class="feature-icon"><?= Icons::svg($item['icon'], 'icon', 24) ?></span><?php endif; ?>
                <h3><?= e($item['title']) ?></h3>
                <?php if ($item['text']): ?><p><?= nl2br(e($item['text'])) ?></p><?php endif; ?>
                <?php if ($item['link_label'] && $item['link_url']): ?><a class="text-link" href="<?= e(Ui::href($item['link_url'])) ?>"><?= e($item['link_label']) ?> <?= Icons::svg('arrow-right', 'icon', 14) ?></a><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>
