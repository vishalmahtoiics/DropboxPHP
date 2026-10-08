<?php
/** @var array $s @var array|null $product */
use App\Core\View;
use App\Services\Forms;

$form = $s['form'] !== '' ? Forms::bySlug($s['form']) : null;
if (!$form) {
    return;
}
?>
<div class="bx-form<?= $s['card'] ? ' card' : '' ?>">
    <?php if ($s['heading'] !== ''): ?><h2 class="form-heading"><?= e($s['heading']) ?></h2><?php endif; ?>
    <?= View::partial('site/partials/form', ['form' => $form, 'ctaLocation' => $s['cta_location'] ?: 'Builder form'] + ($product ? ['productId' => (int) $product['id']] : [])) ?>
</div>
