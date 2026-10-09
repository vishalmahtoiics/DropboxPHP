<?php
/**
 * Image slider. On the site one slide shows at a time (site.js moves them);
 * in the editor every slide is shown, one under the other, so each can be
 * seen and its image chosen.
 *
 * @var array $s @var array $el @var bool $editor
 */
use App\Cms\Icons;
use App\Cms\Ui;

$slides = [];
foreach ($s['slides'] as $i => $slide) {
    $img = Ui::img((string) $slide['image'], (string) $slide['alt'], '', $i > 0);
    if ($img === '' && !$editor) {
        continue; // a slide without an image is left out on the site
    }
    $slides[] = ['img' => $img, 'link' => $slide['link'] !== '' ? Ui::href((string) $slide['link']) : '', 'alt' => (string) $slide['alt']];
}
if (!$slides) {
    return;
}
$count = count($slides);
$multi = $count > 1 && !$editor;
?>
<div class="bx-slider<?= $editor ? ' is-editor' : '' ?>"<?= $multi ? ' data-bx-slider data-interval="' . ($s['autoplay'] ? max(2, (int) $s['interval']) * 1000 : 0) . '"' : '' ?> role="region" aria-roledescription="carousel" aria-label="<?= e($slides[0]['alt'] !== '' ? $slides[0]['alt'] : 'Slides') ?>">
    <div class="bx-slides">
        <?php foreach ($slides as $i => $slide): ?>
            <div class="bx-slide<?= $i === 0 ? ' is-active' : '' ?>" role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> of <?= $count ?>"<?= $multi && $i > 0 ? ' aria-hidden="true"' : '' ?>>
                <?php if ($slide['img'] === ''): ?>
                    <div class="bx-placeholder-img">Slide <?= $i + 1 ?> — choose an image in the panel</div>
                <?php elseif ($slide['link'] !== ''): ?>
                    <a href="<?= e($slide['link']) ?>"<?= $multi && $i > 0 ? ' tabindex="-1"' : '' ?>><?= $slide['img'] ?></a>
                <?php else: ?>
                    <?= $slide['img'] ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($multi && $s['arrows']): ?>
        <button type="button" class="bx-slider-nav is-prev" data-bx-prev aria-label="Previous slide"><?= Icons::svg('chevron-left', 'icon', 28) ?></button>
        <button type="button" class="bx-slider-nav is-next" data-bx-next aria-label="Next slide"><?= Icons::svg('chevron-right', 'icon', 28) ?></button>
    <?php endif; ?>
    <?php if ($multi && $s['dots']): ?>
        <div class="bx-slider-dots">
            <?php foreach ($slides as $i => $slide): ?>
                <button type="button" data-bx-dot="<?= $i ?>" aria-label="Go to slide <?= $i + 1 ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
