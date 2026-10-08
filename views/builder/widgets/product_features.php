<?php
/** @var array $s @var array $product */
use App\Cms\Icons;

$items = $product['features'] ?: [];
if ((int) $s['limit'] > 0) {
    $items = array_slice($items, 0, (int) $s['limit']);
}
if (!$items) {
    return;
}
?>
<?php if ($s['heading'] !== ''): ?><h2 class="section-title"><?= e($s['heading']) ?></h2><?php endif; ?>
<ul class="check-list big cols-<?= (int) $s['columns'] ?>"><?php foreach ($items as $f): ?><li><?= Icons::svg('check-circle', 'icon', 22) ?><span><?= e($f) ?></span></li><?php endforeach; ?></ul>
