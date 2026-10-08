<?php use App\Cms\Icons; use App\Cms\Ui; use App\Core\Sanitizer; ?>
<div class="container medium">
    <?= Ui::head($c, $h) ?>
    <?php if ($c['layout'] === 'two-column'): ?>
        <div class="grid cols-2 faq-open">
            <?php foreach ($c['items'] as $item): ?>
                <div><h3><?= e($item['question']) ?></h3><div class="prose"><?= Sanitizer::html($item['answer']) ?></div></div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="faq-list">
            <?php foreach ($c['items'] as $i => $item): ?>
                <details class="faq-item"<?= $i === 0 ? ' open' : '' ?>>
                    <summary><h3><?= e($item['question']) ?></h3><?= Icons::svg('chevron-down', 'icon', 20) ?></summary>
                    <div class="prose"><?= Sanitizer::html($item['answer']) ?></div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
