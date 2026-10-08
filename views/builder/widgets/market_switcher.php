<?php
/** @var array $s @var bool $editor */
use App\Cms\Icons;
use App\Services\Countries;

$markets = Countries::published();
$country = Countries::current();
if (count($markets) < 2) {
    if ($editor) {
        echo '<div class="bx-note">Shown when more than one market is published.</div>';
    }
    return;
}
?>
<details class="market-switch">
    <summary aria-label="Change country"><?= Icons::svg('globe', 'icon', 16) ?> <span><?= e($s['label'] === 'name' ? $country['name'] : $country['code']) ?></span></summary>
    <ul>
        <?php foreach ($markets as $m): ?>
            <li><a href="<?= e(Countries::href($m, '/')) ?>"<?= (int) $m['id'] === (int) $country['id'] ? ' aria-current="true"' : '' ?>><?= e($m['name']) ?></a></li>
        <?php endforeach; ?>
    </ul>
</details>
