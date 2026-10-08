<?php /** @var array $s @var array $product */ use App\Cms\Ui; ?>
<?php if ($product['image_url']): ?><div class="bx-product-image"><?= Ui::img($product['image_url'], (string) ($product['image_alt'] ?: $product['name']), 'rounded', (bool) $s['lazy']) ?></div><?php endif; ?>
