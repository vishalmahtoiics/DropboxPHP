<?php
use App\Cms\Icons;
use App\Cms\Ui;
use App\Core\Sanitizer;
use App\Core\View;
use App\Services\Countries;
use App\Services\Money;

$p = $product;
$annual = Ui::price($p, 'annual');
$monthly = Ui::price($p, 'monthly');
$ps = $productSettings;
$benefitsHeading = strtr((string) $ps['benefits_heading'], ['{product}' => $p['name']]);
?>
<?php if (!empty($preview)): ?><div class="preview-bar">Preview</div><?php endif; ?>
<section class="page-hero product-hero">
    <div class="container product-layout">
        <div class="product-main">
            <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(Countries::href(null, '/')) ?>">Home</a> / <a href="<?= e(Countries::href(null, '/pricing')) ?>">Plans</a><?php if ($p['category_name']): ?> / <span><?= e($p['category_name']) ?></span><?php endif; ?></nav>
            <?php if ($p['brand_name']): ?><p class="eyebrow"><?= e($p['brand_name']) ?></p><?php endif; ?>
            <h1><?= e($p['name']) ?></h1>
            <?php if ($p['short_description']): ?><p class="lead"><?= e($p['short_description']) ?></p><?php endif; ?>
            <?php if ($p['image_url']): ?><div class="product-image"><?= Ui::img($p['image_url'], (string) ($p['image_alt'] ?: $p['name']), 'rounded', false) ?></div><?php endif; ?>
            <?php if ($gallery): ?><div class="product-gallery"><?php foreach ($gallery as $g): ?><?= Ui::img($g, $p['name'], 'rounded') ?><?php endforeach; ?></div><?php endif; ?>
        </div>
        <aside class="product-aside">
            <div class="card price-box" data-billing-scope>
                <?php if ($p['monthly_price'] !== null && $p['annual_price'] !== null): ?>
                    <div class="billing-toggle small" role="group" aria-label="Billing period">
                        <button type="button" data-billing="monthly" aria-pressed="false">Monthly</button>
                        <button type="button" data-billing="annual" aria-pressed="true">Annual</button>
                    </div>
                <?php endif; ?>
                <div class="price" data-price data-annual="<?= e($annual['amount']) ?>" data-annual-note="<?= e($annual['note']) ?>" data-monthly="<?= e($monthly['amount']) ?>" data-monthly-note="<?= e($monthly['note']) ?>">
                    <?php if ($ps['show_compare_price'] && $p['compare_at_price']): ?><s class="compare"><?= e(Money::format($p['compare_at_price'], $p['symbol'], $p['currency'])) ?></s><?php endif; ?>
                    <span class="amount" data-amount><?= e($annual['amount'] ?: 'Custom pricing') ?></span>
                    <small data-note><?= e($annual['note']) ?></small>
                </div>
                <?php if ($p['discount_percent']): ?><p class="discount"><?= (int) $p['discount_percent'] ?>% off</p><?php endif; ?>
                <?php if ($p['price_note']): ?><p class="muted small"><?= e($p['price_note']) ?></p><?php endif; ?>
                <?php if ($ps['show_specs'] && $p['specs']): ?>
                    <dl class="spec-list"><?php foreach ($p['specs'] as $s): ?><div><dt><?= e($s['label'] ?? '') ?></dt><dd><?= e($s['value'] ?? '') ?></dd></div><?php endforeach; ?></dl>
                <?php endif; ?>
                <?php if ($p['cta_url']): ?>
                    <?= Ui::button((string) ($p['cta_label'] ?: 'Get started'), (string) $p['cta_url'], 'primary', 'btn-block btn-lg') ?>
                <?php elseif ($form): ?>
                    <button class="btn btn-primary btn-block btn-lg" type="button" data-scroll-to="product-form"><?= e($p['cta_label'] ?: 'Get a quote') ?></button>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>
<?php if ($ps['show_features'] && $p['features']): ?>
    <section class="cms-section"><div class="container medium">
        <h2 class="section-title">What's included</h2>
        <ul class="check-list big cols-2"><?php foreach ($p['features'] as $f): ?><li><?= Icons::svg('check-circle', 'icon', 22) ?><span><?= e($f) ?></span></li><?php endforeach; ?></ul>
    </div></section>
<?php endif; ?>
<?php if ($p['description']): ?>
    <section class="cms-section"><div class="container medium"><div class="prose"><?= Sanitizer::html($p['description']) ?></div></div></section>
<?php endif; ?>
<?php if ($ps['show_benefits'] && $p['benefits']): ?>
    <section class="cms-section preset-muted"><div class="container">
        <h2 class="section-title center"><?= e($benefitsHeading) ?></h2>
        <div class="grid cols-<?= min(3, count($p['benefits'])) ?> features style-cards"><?php foreach ($p['benefits'] as $b): ?><article class="feature"><span class="feature-icon"><?= Icons::svg('award', 'icon', 22) ?></span><h3><?= e($b) ?></h3></article><?php endforeach; ?></div>
    </div></section>
<?php endif; ?>
<?php if ($form && !$p['cta_url']): ?>
    <section class="cms-section" id="product-form"><div class="container form-block layout-split">
        <div class="form-block-text"><h2 class="section-title">Get <?= e($p['name']) ?></h2><p class="section-intro">Tell us about your team and we will send pricing and next steps.</p></div>
        <div class="form-block-form card"><?= View::partial('site/partials/form', ['form' => $form, 'productId' => (int) $p['id'], 'ctaLocation' => 'Product page', 'ctaLabel' => $p['cta_label'] ?: 'Get a quote']) ?></div>
    </div></section>
<?php endif; ?>
<?php if ($related): ?>
    <section class="cms-section preset-muted"><div class="container"><h2 class="section-title center">You may also like</h2>
        <div class="grid cols-<?= count($related) ?> product-grid"><?php foreach ($related as $r): ?><?= View::partial('site/partials/product-card', ['p' => $r, 'billing' => 'annual', 'showFeatures' => true, 'featureLimit' => 4, 'highlight' => false]) ?><?php endforeach; ?></div>
    </div></section>
<?php endif; ?>
