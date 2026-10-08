<?php /** @var array $s */ use App\Cms\Icons; use App\Core\Sanitizer; ?>
<?php if ($s['items']): ?>
<div class="bx-accordion">
    <?php foreach ($s['items'] as $i => $item): ?>
        <details<?= $i === 0 && $s['first_open'] ? ' open' : '' ?>>
            <summary><span><?= e($item['title']) ?></span><?= Icons::svg('chevron-down', 'icon', 18) ?></summary>
            <div class="bx-accordion-body prose"><?= Sanitizer::html($item['content']) ?></div>
        </details>
    <?php endforeach; ?>
</div>
<?php endif; ?>
