<?php
/** @var array $s @var array|null $page @var array|null $product */
use App\Services\Countries;

$title = $product['name'] ?? ($page['title'] ?? '');
?>
<nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(Countries::href(null, '/')) ?>"><?= e($s['home']) ?></a><?php if ($title !== ''): ?> / <span aria-current="page"><?= e($title) ?></span><?php endif; ?></nav>
