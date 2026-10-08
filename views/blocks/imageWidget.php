<?php
use App\Cms\Ui;

if (!$c['image']) {
    return;
}
$img = Ui::img($c['image'], $c['alt'], trim(($c['rounded'] ? 'rounded ' : '') . ($c['shadow'] ? 'shadow' : '')), !$isFirst);
?>
<div class="container">
    <figure class="image-widget align-<?= e($c['align']) ?>"<?= $c['width'] ? ' style="--w:' . e($c['width']) . '"' : '' ?>>
        <?php if ($c['link']): ?><a href="<?= e(Ui::href($c['link'])) ?>"><?= $img ?></a><?php else: ?><?= $img ?><?php endif; ?>
        <?php if ($c['caption']): ?><figcaption><?= e($c['caption']) ?></figcaption><?php endif; ?>
    </figure>
</div>
