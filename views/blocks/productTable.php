<?php
use App\Cms\Icons;
use App\Cms\Ui;
use App\Services\Content;
use App\Services\Countries;

$products = Content::productsForBlock(Countries::current(), $c);
if (!$products) {
    return;
}
$specValue = static function (array $p, string $label): string {
    foreach ($p['specs'] as $spec) {
        if (strcasecmp(trim((string) ($spec['label'] ?? '')), trim($label)) === 0) {
            return (string) ($spec['value'] ?? '');
        }
    }
    return match (strtolower($label)) {
        'storage' => (string) ($p['storage'] ?? ''),
        'users' => $p['min_users'] ? $p['min_users'] . ($p['max_users'] ? '–' . $p['max_users'] : '+') : '',
        default => '',
    };
};
$allFeatures = [];
foreach ($products as $p) {
    foreach ($p['features'] as $f) {
        $allFeatures[$f] = true;
    }
}
?>
<div class="container">
    <?= Ui::head($c, $h) ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Plan comparison">
        <table class="compare-table">
            <thead>
                <tr><th scope="col"><span class="sr-only">Feature</span></th>
                    <?php foreach ($products as $p): ?><th scope="col"><a href="<?= e($p['url']) ?>"><?= e($p['name']) ?></a></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($c['show_price']): ?>
                    <tr><th scope="row">Price</th>
                        <?php foreach ($products as $p): $pr = Ui::price($p, 'annual'); ?>
                            <td><strong><?= e($pr['amount'] ?: 'Custom') ?></strong><br><small class="muted"><?= e($pr['note']) ?></small></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endif; ?>
                <?php foreach ($c['rows'] as $row): ?>
                    <tr><th scope="row"><?= e($row) ?></th><?php foreach ($products as $p): ?><td><?= Ui::cell($specValue($p, $row)) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
                <?php foreach (array_keys($allFeatures) as $feature): ?>
                    <tr><th scope="row"><?= e($feature) ?></th>
                        <?php foreach ($products as $p): ?><td><?= in_array($feature, $p['features'], true) ? Ui::cell('yes') : Ui::cell('no') ?></td><?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <tr><th scope="row"><span class="sr-only">Choose</span></th>
                    <?php foreach ($products as $p): ?>
                        <td><button class="btn btn-secondary btn-sm" type="button" data-cta-form="<?= e((string) ($p['cta_form_id'] ?: '')) ?>" data-product-id="<?= (int) $p['id'] ?>" data-cta-label="<?= e($p['cta_label'] ?: 'Get started') ?>" data-cta-location="Comparison table"><?= e($p['cta_label'] ?: 'Get started') ?></button></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>
</div>
