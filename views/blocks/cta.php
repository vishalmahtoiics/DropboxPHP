<?php use App\Cms\Ui; ?>
<div class="container">
    <div class="cta cta-<?= e($c['style']) ?>">
        <div class="cta-text">
            <<?= $h ?> class="section-title"><?= e($c['heading']) ?></<?= $h ?>>
            <?php if ($c['text']): ?><p><?= nl2br(e($c['text'])) ?></p><?php endif; ?>
        </div>
        <?= Ui::buttons($c, 'btn-row cta-actions') ?>
    </div>
</div>
