<?php use App\Cms\Icons; use App\Cms\Ui; ?>
<div class="container">
    <?= Ui::head($c, $h) ?>
    <div class="grid cols-<?= (int) $c['columns'] ?> image-cards">
        <?php foreach ($c['items'] as $item): ?>
            <article class="image-card">
                <?php if ($item['image']): ?><div class="ratio" style="aspect-ratio:<?= e($c['ratio']) ?>"><?= Ui::img($item['image'], $item['image_alt']) ?></div><?php endif; ?>
                <div class="image-card-body">
                    <h3><?= e($item['title']) ?></h3>
                    <?php if ($item['text']): ?><p><?= nl2br(e($item['text'])) ?></p><?php endif; ?>
                    <?php if ($item['link_url']): ?><a class="text-link stretched" href="<?= e(Ui::href($item['link_url'])) ?>"><?= e($item['link_label'] ?: 'Learn more') ?> <?= Icons::svg('arrow-right', 'icon', 14) ?></a><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>
