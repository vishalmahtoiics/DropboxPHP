<?php /** @var array $s */ $n = is_numeric($s['number']) ? $s['number'] + 0 : 0; ?>
<div class="bx-counter">
    <div class="bx-counter-number"><?= e($s['prefix']) ?><span<?= $s['animate'] ? ' data-count-to="' . e((string) $n) . '"' : '' ?>><?= e(number_format((float) $n, is_float($n) ? 1 : 0)) ?></span><?= e($s['suffix']) ?></div>
    <?php if ($s['title'] !== ''): ?><div class="bx-counter-title"><?= e($s['title']) ?></div><?php endif; ?>
</div>
