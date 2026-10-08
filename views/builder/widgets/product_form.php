<?php
/** @var array $s @var array $product */
use App\Core\View;
use App\Services\Forms;
use App\Services\Settings;

$form = $product['cta_form_id'] ? Forms::byId((int) $product['cta_form_id']) : null;
$form ??= Forms::bySlug((string) Settings::get('product', 'default_form_slug', ''));
if (!$form) {
    return;
}
$heading = strtr((string) $s['heading'], ['{product}' => $product['name']]);
?>
<div class="bx-form<?= $s['card'] ? ' card' : '' ?>" id="product-form">
    <?php if ($heading !== ''): ?><h2 class="form-heading"><?= e($heading) ?></h2><?php endif; ?>
    <?= View::partial('site/partials/form', ['form' => $form, 'productId' => (int) $product['id'], 'ctaLocation' => 'Product page', 'ctaLabel' => $product['cta_label'] ?: 'Get a quote']) ?>
</div>
