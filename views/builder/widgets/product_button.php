<?php
/** @var array $s @var array $product */
use App\Cms\Ui;

$p = $product;
$label = $s['text'] !== '' ? $s['text'] : ((string) $p['cta_label'] ?: ($p['cta_url'] ? 'Get started' : 'Get a quote'));
$size = ['sm' => 'btn-sm', 'md' => '', 'lg' => 'btn-lg'][$s['size']] ?? 'btn-lg';
$extra = trim('bx-btn ' . $size . ($s['full'] ? ' btn-block' : ''));
?>
<?php if ($p['cta_url']): ?>
    <?= Ui::button($label, (string) $p['cta_url'], 'primary', $extra) ?>
<?php else: ?>
    <button class="btn btn-primary <?= e($extra) ?>" type="button" data-scroll-to="product-form"><?= e($label) ?></button>
<?php endif; ?>
