<?php use App\Cms\Ui; ?>
<div class="container">
    <?= Ui::head($c, $h) ?>
    <dl class="grid cols-<?= (int) $c['columns'] ?> stats">
        <?php foreach ($c['items'] as $item): ?>
            <div class="stat"><dt><?= e($item['label']) ?></dt><dd><?= e($item['value']) ?></dd><?php if ($item['text']): ?><p><?= e($item['text']) ?></p><?php endif; ?></div>
        <?php endforeach; ?>
    </dl>
</div>
