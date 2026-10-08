<?php
/** @var array $s @var array $product */
use App\Cms\Ui;
use App\Services\Money;

$p = $product;
$annual = Ui::price($p, 'annual');
$monthly = Ui::price($p, 'monthly');
$toggle = $s['toggle'] && $p['monthly_price'] !== null && $p['annual_price'] !== null;
?>
<div class="bx-price" data-billing-scope>
    <?php if ($toggle): ?>
        <div class="billing-toggle small" role="group" aria-label="Billing period">
            <button type="button" data-billing="monthly" aria-pressed="false">Monthly</button>
            <button type="button" data-billing="annual" aria-pressed="true">Annual</button>
        </div>
    <?php endif; ?>
    <div class="price" data-price data-annual="<?= e($annual['amount']) ?>" data-annual-note="<?= e($annual['note']) ?>" data-monthly="<?= e($monthly['amount']) ?>" data-monthly-note="<?= e($monthly['note']) ?>">
        <?php if ($s['compare'] && $p['compare_at_price']): ?><s class="compare"><?= e(Money::format($p['compare_at_price'], $p['symbol'], $p['currency'])) ?></s><?php endif; ?>
        <span class="amount" data-amount><?= e($annual['amount'] ?: 'Custom pricing') ?></span>
        <small data-note><?= e($annual['note']) ?></small>
    </div>
    <?php if ($p['discount_percent']): ?><p class="discount"><?= (int) $p['discount_percent'] ?>% off</p><?php endif; ?>
    <?php if ($s['note'] && $p['price_note']): ?><p class="muted small"><?= e($p['price_note']) ?></p><?php endif; ?>
</div>
