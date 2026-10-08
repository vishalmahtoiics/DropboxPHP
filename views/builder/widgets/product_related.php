<?php
/** @var array $s @var array $product */
use App\Core\View;
use App\Services\Content;
use App\Services\Countries;

if (!$product['category_slug']) {
    return;
}
$related = array_values(array_filter(
    Content::productsForBlock(Countries::current(), ['source' => 'category', 'category' => $product['category_slug'], 'limit' => (int) $s['limit'] + 1]),
    static fn ($p) => (int) $p['id'] !== (int) $product['id']
));
$related = array_slice($related, 0, (int) $s['limit']);
if (!$related) {
    return;
}
?>
<?php if ($s['heading'] !== ''): ?><h2 class="section-title center"><?= e($s['heading']) ?></h2><?php endif; ?>
<div class="grid cols-<?= min(4, count($related)) ?> product-grid"><?php foreach ($related as $r): ?><?= View::partial('site/partials/product-card', ['p' => $r, 'billing' => 'annual', 'showFeatures' => true, 'featureLimit' => 4, 'highlight' => false]) ?><?php endforeach; ?></div>
