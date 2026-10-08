<?php
use App\Cms\Ui;
use App\Core\View;
use App\Services\Content;
use App\Services\Countries;

$products = Content::productsForBlock(Countries::current(), $c);
if (!$products) {
    return;
}
$billing = $c['billing'] === 'toggle' ? 'annual' : $c['billing'];
?>
<div class="container" data-billing-scope>
    <?= Ui::head($c, $h) ?>
    <?php if ($c['billing'] === 'toggle'): ?>
        <div class="billing-toggle" role="group" aria-label="Billing period">
            <button type="button" data-billing="monthly" aria-pressed="false">Monthly</button>
            <button type="button" data-billing="annual" aria-pressed="true">Annual <span class="save">Save up to 20%</span></button>
        </div>
    <?php endif; ?>
    <div class="grid cols-<?= min((int) $c['columns'], max(1, count($products))) ?> product-grid">
        <?php foreach ($products as $p): ?>
            <?= View::partial('site/partials/product-card', ['p' => $p, 'billing' => $billing, 'showFeatures' => $c['show_features'], 'featureLimit' => (int) $c['feature_limit'], 'highlight' => $c['highlight_featured'], 'ctaLocation' => 'Product cards']) ?>
        <?php endforeach; ?>
    </div>
</div>
