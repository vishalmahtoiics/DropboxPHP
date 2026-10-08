<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? 'Install') . ' · Website setup') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="auth-body">
    <main class="auth-wrap install-wrap">
        <div class="auth-brand">
            <span class="brand-mark"><?= App\Cms\Icons::svg('settings', 'icon', 22) ?></span>
            <strong>Website setup</strong>
        </div>
        <div class="card auth-card">
            <?= App\Core\View::partial('partials/flash') ?>
            <?= $content ?>
        </div>
    </main>
    <script src="<?= e(asset('js/admin.js')) ?>" nonce="<?= e(nonce()) ?>"></script>
</body>
</html>
