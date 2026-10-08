<?php use App\Cms\Icons; use App\Cms\Ui; ?>
<div class="container">
    <?= Ui::head($c, $h, $c['align']) ?>
    <ul class="check-list big cols-<?= (int) $c['columns'] ?>">
        <?php foreach ($c['items'] as $item): ?><li><?= Icons::svg($c['icon'] ?: 'check-circle', 'icon', 22) ?><span><?= e($item) ?></span></li><?php endforeach; ?>
    </ul>
</div>
