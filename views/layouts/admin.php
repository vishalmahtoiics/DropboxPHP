<?php
use App\Cms\Icons;
use App\Core\View;
use App\Services\AdminNav;
use App\Services\Countries;
use App\Services\Settings;

$site = Settings::group('website');
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/admin'), PHP_URL_PATH) ?: '/admin';
$markets = Countries::accessible();
$activeHref = AdminNav::activeHref($path);
$initials = strtoupper(mb_substr($me['name'] ?? '?', 0, 1) . mb_substr(strstr(($me['name'] ?? '') . ' ', ' ') ?: '', 1, 1));
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(($title ?? 'Admin') . ' · ' . $site['site_name']) ?></title>
    <?php if ($site['favicon_url']): ?><link rel="icon" href="<?= e($site['favicon_url']) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <script nonce="<?= e(nonce()) ?>">try{var t=localStorage.getItem('admin-theme');if(t==='dark'||(t!=='light'&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.dataset.theme='dark';if(localStorage.getItem('admin-nav')==='collapsed')document.documentElement.classList.add('nav-collapsed')}catch(e){}</script>
</head>
<body>
<div class="shell">
    <aside class="sidebar" id="sidebar" aria-label="Admin navigation">
        <div class="sidebar-head">
            <a class="sidebar-brand" href="/admin" title="<?= e($site['site_name']) ?>">
                <?= App\Services\Brand::mark('brand-mark') ?>
                <span class="truncate"><?= e($site['site_name']) ?></span>
            </a>
            <button class="btn btn-ghost btn-icon sidebar-toggle" type="button" data-collapse-nav aria-label="Collapse menu" aria-controls="sidebar" title="Collapse or expand the menu"><?= Icons::svg('menu', 'icon', 20) ?></button>
        </div>
        <nav>
            <?php foreach (AdminNav::visible() as $group): ?>
                <div class="nav-group">
                    <div class="nav-group-title"><?= e($group['title']) ?></div>
                    <?php foreach ($group['items'] as $item): ?>
                        <a class="nav-link<?= $item['href'] === $activeHref ? ' active' : '' ?>" href="<?= e($item['href']) ?>" title="<?= e($item['label']) ?>">
                            <?= Icons::svg($item['icon'], 'icon', 18) ?><span><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="btn btn-ghost btn-icon menu-toggle" type="button" data-toggle-nav aria-label="Open menu" aria-controls="sidebar"><?= Icons::svg('menu') ?></button>
            <form class="topbar-search" action="/admin/search" method="get" role="search">
                <?= Icons::svg('search', 'icon', 16) ?>
                <input class="input" type="search" name="q" placeholder="Search pages, products, leads…" aria-label="Search admin" value="<?= e($_GET['q'] ?? '') ?>">
            </form>
            <div class="grow"></div>
            <?php if (count($markets) > 1): ?>
                <form method="post" action="/admin/country" class="hide-sm">
                    <?= csrf_field() ?>
                    <label class="sr-only" for="market-switch">Market</label>
                    <select class="select input-sm" id="market-switch" name="country_id" data-autosubmit>
                        <?php foreach ($markets as $m): ?>
                            <option value="<?= (int) $m['id'] ?>" <?= (int) $m['id'] === (int) ($adminCountry['id'] ?? 0) ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
            <a class="btn btn-ghost btn-icon" href="/" target="_blank" rel="noopener" title="View site"><?= Icons::svg('external', 'icon', 18) ?></a>
            <button class="btn btn-ghost btn-icon" type="button" data-theme-toggle title="Toggle dark mode"><?= Icons::svg('moon', 'icon', 18) ?></button>
            <div class="dropdown" data-dropdown>
                <button class="btn btn-ghost" type="button" data-dropdown-toggle aria-haspopup="true">
                    <span class="avatar"><?= e($initials) ?></span>
                    <span class="hide-sm"><?= e($me['name']) ?></span>
                    <?= Icons::svg('chevron-down', 'icon', 16) ?>
                </button>
                <div class="dropdown-menu" role="menu">
                    <div class="small muted" style="padding:.4rem .6rem"><?= e($me['email']) ?><br><?= e($me['role_name']) ?></div>
                    <a href="/admin/profile"><?= Icons::svg('user', 'icon', 16) ?> Profile & security</a>
                    <form method="post" action="/auth/logout">
                        <?= csrf_field() ?>
                        <button type="submit"><?= Icons::svg('log-out', 'icon', 16) ?> Sign out</button>
                    </form>
                </div>
            </div>
        </header>
        <main class="content<?= !empty($narrow) ? ' narrow' : '' ?>" id="main">
            <?= View::partial('partials/flash') ?>
            <?= $content ?>
        </main>
    </div>
</div>
<?= View::partial('admin/partials/media-dialog') ?>
<script nonce="<?= e(nonce()) ?>" src="<?= e(asset('js/admin.js')) ?>"></script>
</body>
</html>
