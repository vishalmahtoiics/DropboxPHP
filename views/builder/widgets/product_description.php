<?php /** @var array $product */ use App\Core\Sanitizer; ?>
<?php if ($product['description']): ?><div class="prose"><?= Sanitizer::html($product['description']) ?></div><?php endif; ?>
