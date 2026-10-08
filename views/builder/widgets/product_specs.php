<?php /** @var array $s @var array $product */ ?>
<?php if ($product['specs']): ?>
    <?php if ($s['heading'] !== ''): ?><h3><?= e($s['heading']) ?></h3><?php endif; ?>
    <dl class="spec-list"><?php foreach ($product['specs'] as $sp): ?><div><dt><?= e($sp['label'] ?? '') ?></dt><dd><?= e($sp['value'] ?? '') ?></dd></div><?php endforeach; ?></dl>
<?php endif; ?>
