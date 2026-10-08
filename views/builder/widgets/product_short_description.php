<?php /** @var array $product */ ?>
<?php if ($product['short_description']): ?><p class="bx-product-text lead"><?= nl2br(e($product['short_description'])) ?></p><?php endif; ?>
