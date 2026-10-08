<?php use App\Cms\Icons; use App\Cms\Ui; ?>
<div class="container">
    <?= Ui::head($c, $h) ?>
    <ol class="steps cols-<?= min(4, max(1, count($c['items']))) ?>">
        <?php foreach ($c['items'] as $i => $item): ?>
            <li class="step">
                <span class="step-num"><?= $item['icon'] ? Icons::svg($item['icon'], 'icon', 22) : $i + 1 ?></span>
                <h3><?= e($item['title']) ?></h3>
                <?php if ($item['text']): ?><p><?= nl2br(e($item['text'])) ?></p><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
