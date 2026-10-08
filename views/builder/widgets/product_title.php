<?php /** @var array $s @var array $product */ $tag = in_array($s['tag'], ['h1', 'h2', 'h3', 'div'], true) ? $s['tag'] : 'h1'; ?>
<<?= $tag ?> class="bx-product-title"><?= e($product['name']) ?></<?= $tag ?>>
