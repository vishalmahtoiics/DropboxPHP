<?php
use App\Cms\Icons;
use App\Cms\Ui;

/** @var array $p @var string $billing @var bool $showFeatures @var int $featureLimit @var bool $highlight */
$annual = Ui::price($p, 'annual');
$monthly = Ui::price($p, 'monthly');
$initial = $billing === 'monthly' ? $monthly : $annual;
$featured = $highlight && (int) $p['is_featured'] === 1;
?>
<article class="product-card<?= $featured ? ' is-featured' : '' ?>">
    <?php if ($featured): ?><span class="product-badge">Most popular</span><?php endif; ?>
    <header>
        <h3><a href="<?= e($p['url']) ?>"><?= e($p['name']) ?></a></h3>
        <?php if ($p['short_description']): ?><p class="muted"><?= e($p['short_description']) ?></p><?php endif; ?>
    </header>
    <div class="price" data-price data-annual="<?= e($annual['amount']) ?>" data-annual-note="<?= e($annual['note']) ?>" data-monthly="<?= e($monthly['amount']) ?>" data-monthly-note="<?= e($monthly['note']) ?>">
        <?php if ($initial['amount'] !== ''): ?>
            <?php if ($p['compare_at_price']): ?><s class="compare"><?= e(App\Services\Money::format($p['compare_at_price'], $p['symbol'], $p['currency'])) ?></s><?php endif; ?>
            <span class="amount" data-amount><?= e($initial['amount']) ?></span>
        <?php else: ?>
            <span class="amount amount-text" data-amount>Custom</span>
        <?php endif; ?>
        <small data-note><?= e($initial['note']) ?></small>
    </div>
    <?php if ($p['storage']): ?><p class="spec-line"><?= Icons::svg('hard-drive', 'icon', 16) ?> <?= e($p['storage']) ?></p><?php endif; ?>
    <?php if ($showFeatures && $p['features']): ?>
        <ul class="check-list small"><?php foreach (array_slice($p['features'], 0, $featureLimit) as $f): ?><li><?= Icons::svg('check', 'icon', 16) ?><span><?= e($f) ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <footer>
        <?php if ($p['cta_url']): ?>
            <?= Ui::button((string) $p['cta_label'] ?: 'Get started', (string) $p['cta_url'], $featured ? 'primary' : 'secondary', 'btn-block') ?>
        <?php else: ?>
            <button class="btn btn-<?= $featured ? 'primary' : 'secondary' ?> btn-block" type="button" data-cta-form="<?= e((string) ($p['cta_form_id'] ?: '')) ?>" data-product-id="<?= (int) $p['id'] ?>" data-cta-label="<?= e($p['cta_label'] ?: 'Get started') ?>" data-cta-location="<?= e($ctaLocation ?? 'Product card') ?>"><?= e($p['cta_label'] ?: 'Get started') ?></button>
        <?php endif; ?>
        <a class="text-link center" href="<?= e($p['url']) ?>">View details</a>
    </footer>
</article>
