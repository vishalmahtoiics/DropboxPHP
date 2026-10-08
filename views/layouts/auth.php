<?php
use App\Services\Settings;

$site = Settings::group('website');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? 'Sign in') . ' · ' . $site['site_name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <script nonce="<?= e(nonce()) ?>">try{var t=localStorage.getItem('admin-theme');if(t==='dark'||(t!=='light'&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.dataset.theme='dark'}catch(e){}</script>
</head>
<body class="auth-body">
    <main class="auth-wrap">
        <div class="auth-brand">
            <?php if ($site['logo_url']): ?>
                <img src="<?= e($site['logo_url']) ?>" alt="<?= e($site['site_name']) ?>" height="36">
            <?php else: ?>
                <span class="brand-mark"><?= App\Cms\Icons::svg('dropbox', 'icon', 22) ?></span>
                <strong><?= e($site['site_name']) ?></strong>
            <?php endif; ?>
        </div>
        <div class="card auth-card">
            <?= App\Core\View::partial('partials/flash') ?>
            <?= $content ?>
        </div>
        <p class="auth-foot">Protected area. Activity is logged.</p>
    </main>
</body>
</html>
