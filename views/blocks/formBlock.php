<?php
use App\Cms\Icons;
use App\Core\View;
use App\Services\Forms;

$form = Forms::bySlug($c['form_slug']);
if (!$form) {
    return;
}
$layout = $c['layout'];
?>
<div class="container form-block layout-<?= e($layout) ?>" id="form">
    <?php if ($c['heading'] || $c['text'] || $c['bullets']): ?>
        <div class="form-block-text">
            <?php if ($c['eyebrow']): ?><p class="eyebrow"><?= e($c['eyebrow']) ?></p><?php endif; ?>
            <?php if ($c['heading']): ?><<?= $h ?> class="section-title"><?= e($c['heading']) ?></<?= $h ?>><?php endif; ?>
            <?php if ($c['text']): ?><p class="section-intro"><?= nl2br(e($c['text'])) ?></p><?php endif; ?>
            <?php if ($c['bullets']): ?><ul class="check-list"><?php foreach ($c['bullets'] as $b): ?><li><?= Icons::svg('check-circle', 'icon', 20) ?><span><?= e($b) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="form-block-form card">
        <?= View::partial('site/partials/form', ['form' => $form, 'ctaLocation' => $c['cta_location'] ?: 'Form section']) ?>
    </div>
</div>
