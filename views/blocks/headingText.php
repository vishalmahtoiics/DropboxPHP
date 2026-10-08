<?php use App\Cms\Ui; use App\Core\Sanitizer; ?>
<div class="container narrow align-<?= e($c['align']) ?>">
    <?php if ($c['eyebrow']): ?><p class="eyebrow"><?= e($c['eyebrow']) ?></p><?php endif; ?>
    <?php if ($c['heading']): ?><<?= $h ?> class="section-title"><?= e($c['heading']) ?></<?= $h ?>><?php endif; ?>
    <div class="prose"><?= Sanitizer::html($c['body']) ?></div>
    <?= Ui::buttons($c) ?>
</div>
