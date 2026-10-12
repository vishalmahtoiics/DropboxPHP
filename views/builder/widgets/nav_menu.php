<?php
/** @var array $s @var array $el */
use App\Cms\Icons;
use App\Core\View;
use App\Services\Countries;
use App\Services\Navigation;

$country = Countries::current();
$items = Navigation::tree((int) $country['id'], (string) $s['menu']);
if (!$items && $s['menu'] === 'MOBILE') {
    $items = Navigation::tree((int) $country['id'], 'HEADER');
}
if (!$items) {
    if ($editor) {
        echo '<div class="bx-note">This menu is empty for ' . e($country['name']) . '. Add links in Admin → Navigation.</div>';
    }
    return;
}
$current = (string) (View::shared('requestPath') ?? '');
$home = Countries::href($country, '/');
$active = static fn (array $i) => $i['href'] && $i['href'] !== '#' && ($i['href'] === $current || ($i['href'] !== $home && str_starts_with($current, rtrim((string) $i['href'], '/') . '/')));
$id = 'bxnav-' . $el['id'];
$link = static fn (array $i, string $extra = '') => '<a href="' . e((string) $i['href']) . '"' . ($extra !== '' ? ' class="' . $extra . '"' : '') . ((int) $i['open_in_new_tab'] ? ' target="_blank" rel="noopener"' : '') . '>';
?>
<nav class="bx-nav bx-nav-<?= e($s['layout']) ?> bx-collapse-<?= e($s['collapse']) ?>" aria-label="<?= e($s['menu'] === 'HEADER' ? 'Main' : ucfirst(strtolower(str_replace('_', ' ', $s['menu'])))) ?>" data-bx-nav>
    <?php if ($s['collapse'] !== 'none'): ?>
        <button class="bx-nav-toggle" type="button" aria-expanded="false" aria-controls="<?= e($id) ?>" aria-label="<?= e($s['toggle_label'] !== '' ? $s['toggle_label'] : 'Menu') ?>" data-bx-nav-toggle><?= Icons::svg('menu', 'icon', 22) ?><?php if ($s['toggle_label'] !== ''): ?><span><?= e($s['toggle_label']) ?></span><?php endif; ?></button>
    <?php endif; ?>
    <ul class="bx-nav-list" id="<?= e($id) ?>">
        <?php foreach ($items as $item): ?>
            <?php $kids = $s['submenus'] ? $item['children'] : []; ?>
            <?php $subId = $id . '-' . (int) $item['id']; ?>
            <li class="<?= $kids ? 'has-sub' : '' ?>">
                <?= $link($item, trim(($active($item) ? 'active' : '') . ((int) $item['is_highlighted'] ? ' highlight' : ''))) ?><?= e($item['label']) ?><?= $kids ? Icons::svg('chevron-down', 'icon bx-caret', 14) : '' ?></a>
                <?php if ($kids): ?>
                    <?php /* Only shown in the opened phone/tablet menu, where sub-menus start closed. */ ?>
                    <button class="bx-sub-toggle" type="button" aria-expanded="false" aria-controls="<?= e($subId) ?>" aria-label="<?= e('Show ' . $item['label'] . ' links') ?>" data-bx-sub-toggle><?= Icons::svg('chevron-down', 'icon', 18) ?></button>
                    <ul class="bx-sub" id="<?= e($subId) ?>">
                        <?php foreach ($kids as $child): ?>
                            <li><?= $link($child) ?><?php if ($child['icon']): ?><?= Icons::svg($child['icon'], 'icon', 18) ?><?php endif; ?><span><strong><?= e($child['label']) ?></strong><?php if ($child['description']): ?><small><?= e($child['description']) ?></small><?php endif; ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
