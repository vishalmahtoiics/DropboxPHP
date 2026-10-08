<?php
use App\Cms\Ui;
use App\Core\DB;
use App\Core\View;
use App\Services\Forms;

$magnet = $c['magnet'] !== '' ? DB::first('SELECT lm.*, m.url AS image_url FROM lead_magnets lm LEFT JOIN media m ON m.id = lm.image_id WHERE lm.slug = ? AND lm.is_active = 1 AND lm.deleted_at IS NULL', [$c['magnet']]) : null;
if (!$magnet) {
    return;
}
$form = $magnet['form_id'] ? Forms::byId((int) $magnet['form_id']) : null;
?>
<div class="container split lead-magnet<?= $c['image_side'] === 'right' ? '' : ' image-left' ?>">
    <div class="split-content">
        <p class="eyebrow"><?= e(ucfirst(strtolower($magnet['kind']))) ?></p>
        <<?= $h ?> class="section-title"><?= e($c['heading'] ?: $magnet['title']) ?></<?= $h ?>>
        <p class="section-intro"><?= nl2br(e($c['text'] ?: (string) $magnet['description'])) ?></p>
        <?php if ($form): ?>
            <div class="card"><?= View::partial('site/partials/form', ['form' => $form, 'ctaLocation' => 'Lead magnet: ' . $magnet['title'], 'magnet' => $magnet['slug']]) ?></div>
            <input type="hidden" data-magnet-slug value="<?= e($magnet['slug']) ?>">
        <?php elseif ($magnet['external_url'] || $magnet['file_id']): ?>
            <a class="btn btn-primary btn-lg" href="/lead-magnet/<?= e($magnet['slug']) ?>/download"><?= e($magnet['cta_label']) ?></a>
        <?php endif; ?>
    </div>
    <div class="split-media"><?= $magnet['image_url'] ? Ui::img($magnet['image_url'], $magnet['title'], 'rounded shadow') : '' ?></div>
</div>
