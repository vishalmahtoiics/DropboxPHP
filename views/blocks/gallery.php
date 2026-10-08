<?php
use App\Cms\Icons;
use App\Cms\Ui;

$items = array_values(array_filter($c['items'], static fn ($i) => $i['image'] !== ''));
if (!$items) {
    return;
}
?>
<div class="container">
    <?= Ui::head($c, $h) ?>
    <?php if ($c['mode'] === 'grid'): ?>
        <div class="grid cols-3 gallery-grid">
            <?php foreach ($items as $item): ?>
                <figure><?= Ui::img($item['image'], $item['alt'], 'rounded') ?><?php if ($item['caption']): ?><figcaption><?= e($item['caption']) ?></figcaption><?php endif; ?></figure>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="slider" data-slider data-autoplay="<?= $c['autoplay'] ? (int) $c['interval'] * 1000 : 0 ?>" aria-roledescription="carousel">
            <div class="slider-track">
                <?php foreach ($items as $i => $item): ?>
                    <figure class="slide" aria-roledescription="slide" aria-label="<?= $i + 1 ?> of <?= count($items) ?>">
                        <?php $img = Ui::img($item['image'], $item['alt'], '', $i > 0 || !$isFirst); ?>
                        <?= $item['link'] ? '<a href="' . e(Ui::href($item['link'])) . '">' . $img . '</a>' : $img ?>
                        <?php if ($item['caption']): ?><figcaption><?= e($item['caption']) ?></figcaption><?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
            <?php if (count($items) > 1): ?>
                <button class="slider-btn prev" type="button" data-slide="-1" aria-label="Previous"><?= Icons::svg('chevron-left') ?></button>
                <button class="slider-btn next" type="button" data-slide="1" aria-label="Next"><?= Icons::svg('chevron-right') ?></button>
                <div class="slider-dots"><?php foreach ($items as $i => $_): ?><button type="button" data-goto="<?= $i ?>" aria-label="Go to slide <?= $i + 1 ?>"></button><?php endforeach; ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
