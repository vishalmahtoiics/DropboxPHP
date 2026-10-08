<?php
/** @var array $s @var array $product */
use App\Cms\Icons;

$items = $product['benefits'] ?: [];
if (!$items) {
    return;
}
$heading = strtr((string) $s['heading'], ['{product}' => $product['name']]);
?>
<?php if ($heading !== ''): ?><h2 class="section-title center"><?= e($heading) ?></h2><?php endif; ?>
<div class="grid cols-<?= min(3, count($items)) ?> features style-cards"><?php foreach ($items as $b): ?><article class="feature"><span class="feature-icon"><?= Icons::svg('award', 'icon', 22) ?></span><h3><?= e($b) ?></h3></article><?php endforeach; ?></div>
