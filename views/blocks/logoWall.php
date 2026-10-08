<?php
use App\Cms\Ui;

$logos = array_values(array_filter($c['items'], static fn ($l) => $l['image'] !== ''));
if (!$logos) {
    return;
}
?>
<div class="container">
    <?php if ($c['heading']): ?><p class="logo-wall-title"><?= e($c['heading']) ?></p><?php endif; ?>
    <ul class="logo-wall<?= $c['grayscale'] ? ' grayscale' : '' ?>">
        <?php foreach ($logos as $logo): ?>
            <li><?php if ($logo['url']): ?><a href="<?= e(Ui::href($logo['url'])) ?>" target="_blank" rel="noopener"><?= Ui::img($logo['image'], $logo['alt']) ?></a><?php else: ?><?= Ui::img($logo['image'], $logo['alt']) ?><?php endif; ?></li>
        <?php endforeach; ?>
    </ul>
</div>
