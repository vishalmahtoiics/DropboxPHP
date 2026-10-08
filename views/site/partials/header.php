<?php
use App\Cms\Icons;
use App\Services\Countries;

$home = Countries::href($country, '/');
$ctaLabel = Countries::setting($country, 'header_cta_label') ?: $site['header_cta_label'];
$ctaUrl = Countries::setting($country, 'header_cta_url') ?: $site['header_cta_url'];
$link = static fn (string $url) => str_starts_with($url, '/') ? Countries::href($country, $url) : $url;
$current = $requestPath ?? '/';
$isActive = static fn (array $item) => $item['href'] && $item['href'] !== '#' && ($item['href'] === $current || ($item['href'] !== $home && str_starts_with($current, rtrim($item['href'], '/') . '/')));
$mobileItems = $mobileNav ?? $headerNav;
?>
<?php if ($site['announcement_enabled'] && $site['announcement_text']): ?>
    <div class="announcement">
        <?php if ($site['announcement_url']): ?>
            <a href="<?= e($link($site['announcement_url'])) ?>"><?= e($site['announcement_text']) ?> <?= Icons::svg('arrow-right', 'icon', 14) ?></a>
        <?php else: ?>
            <span><?= e($site['announcement_text']) ?></span>
        <?php endif; ?>
    </div>
<?php endif; ?>
<header class="site-header<?= $site['header_sticky'] ? ' is-sticky' : '' ?><?= $site['header_glass'] ? ' is-glass' : '' ?>" data-header>
    <div class="container header-inner">
        <a class="logo" href="<?= e($home) ?>" aria-label="<?= e($site['site_name']) ?> home">
            <?php if ($site['logo_url']): ?>
                <img src="<?= e($site['logo_url']) ?>" alt="<?= e($site['site_name']) ?>" height="36">
            <?php else: ?>
                <?= App\Services\Brand::mark() ?><span class="logo-text"><?= e($site['site_name']) ?></span>
            <?php endif; ?>
        </a>
        <nav class="main-nav" aria-label="Main">
            <ul>
                <?php foreach ($headerNav as $item): ?>
                    <?php $hasKids = $item['children'] !== []; ?>
                    <li class="<?= $hasKids ? 'has-menu' : '' ?><?= (int) $item['mega_menu'] ? ' is-mega' : '' ?>">
                        <a href="<?= e($item['href']) ?>" class="<?= $isActive($item) ? 'active' : '' ?><?= (int) $item['is_highlighted'] ? ' highlight' : '' ?>"<?= (int) $item['open_in_new_tab'] ? ' target="_blank" rel="noopener"' : '' ?><?= $hasKids ? ' aria-haspopup="true"' : '' ?>>
                            <?= e($item['label']) ?><?= $hasKids ? Icons::svg('chevron-down', 'icon caret', 14) : '' ?>
                        </a>
                        <?php if ($hasKids): ?>
                            <div class="submenu" style="--cols:<?= (int) $item['mega_columns'] ?: 1 ?>">
                                <ul>
                                    <?php foreach ($item['children'] as $child): ?>
                                        <li>
                                            <a href="<?= e($child['href']) ?>"<?= (int) $child['open_in_new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>>
                                                <?php if ($child['icon']): ?><span class="submenu-icon"><?= Icons::svg($child['icon'], 'icon', 18) ?></span><?php endif; ?>
                                                <span><strong><?= e($child['label']) ?></strong><?php if ($child['description']): ?><small><?= e($child['description']) ?></small><?php endif; ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="header-actions">
            <?php if ($site['header_show_markets'] && count($markets) > 1): ?>
                <details class="market-switch">
                    <summary aria-label="Change country"><?= Icons::svg('globe', 'icon', 16) ?> <span><?= e($country['code']) ?></span></summary>
                    <ul>
                        <?php foreach ($markets as $m): ?>
                            <li><a href="<?= e(Countries::href($m, '/')) ?>"<?= (int) $m['id'] === (int) $country['id'] ? ' aria-current="true"' : '' ?>><?= e($m['name']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
            <?php if ($site['header_secondary_cta_label'] && $site['header_secondary_cta_url']): ?>
                <a class="btn btn-ghost hide-mobile" href="<?= e($link($site['header_secondary_cta_url'])) ?>"><?= e($site['header_secondary_cta_label']) ?></a>
            <?php endif; ?>
            <?php if ($ctaLabel && $ctaUrl): ?>
                <a class="btn btn-primary hide-mobile" href="<?= e($link($ctaUrl)) ?>"><?= e($ctaLabel) ?></a>
            <?php endif; ?>
            <button class="nav-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-nav" data-nav-toggle><?= Icons::svg('menu') ?></button>
        </div>
    </div>
    <nav class="mobile-nav" id="mobile-nav" aria-label="Mobile" hidden>
        <ul>
            <?php foreach ($mobileItems as $item): ?>
                <li>
                    <?php if ($item['children']): ?>
                        <details><summary><?= e($item['label']) ?><?= Icons::svg('chevron-down', 'icon', 16) ?></summary>
                            <ul><?php if ($item['href'] !== '#'): ?><li><a href="<?= e($item['href']) ?>"><?= e($item['label']) ?> overview</a></li><?php endif; ?>
                                <?php foreach ($item['children'] as $child): ?><li><a href="<?= e($child['href']) ?>"><?= e($child['label']) ?></a></li><?php endforeach; ?>
                            </ul>
                        </details>
                    <?php else: ?>
                        <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($ctaLabel && $ctaUrl): ?><a class="btn btn-primary btn-block" href="<?= e($link($ctaUrl)) ?>"><?= e($ctaLabel) ?></a><?php endif; ?>
    </nav>
</header>
