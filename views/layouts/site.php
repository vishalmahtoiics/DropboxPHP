<?php
use App\Core\View;
use App\Services\BrandStyle;
use App\Services\Seo;
use App\Services\Tracking;

$seo ??= Seo::meta(['title' => $title ?? '']);
$graph ??= [];
$showHeader = !isset($page) || (int) ($page['show_header'] ?? 1) === 1;
$showFooter = !isset($page) || (int) ($page['show_footer'] ?? 1) === 1;
$lang = substr((string) ($country['locale'] ?? 'en'), 0, 2);
// Inside the builder's canvas: no tracking, popups or consent banner.
$canvas = !empty($builderCanvas);
?><!doctype html>
<html lang="<?= e($country['locale'] ?? 'en') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $canvas ? '' : Tracking::headTop($tracking, $consent) ?>
    <title><?= e($seo['title']) ?></title>
    <meta name="description" content="<?= e($seo['description']) ?>">
    <meta name="robots" content="<?= e($seo['robots']) ?>">
    <?php if ($seo['canonical']): ?><link rel="canonical" href="<?= e($seo['canonical']) ?>"><?php endif; ?>
    <?php foreach ($seo['alternates'] as $hreflang => $href): ?><link rel="alternate" hreflang="<?= e($hreflang) ?>" href="<?= e($href) ?>"><?php endforeach; ?>
    <meta property="og:type" content="<?= e($seo['og_type']) ?>">
    <meta property="og:title" content="<?= e($seo['og_title']) ?>">
    <meta property="og:description" content="<?= e($seo['og_description']) ?>">
    <meta property="og:site_name" content="<?= e($site['site_name']) ?>">
    <meta property="og:locale" content="<?= e($seo['locale']) ?>">
    <?php if ($seo['canonical']): ?><meta property="og:url" content="<?= e($seo['canonical']) ?>"><?php endif; ?>
    <?php if ($seo['image']): ?><meta property="og:image" content="<?= e($seo['image']) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= $seo['image'] ? 'summary_large_image' : 'summary' ?>">
    <?php if ($seo['twitter_handle']): ?><meta name="twitter:site" content="<?= e('@' . ltrim($seo['twitter_handle'], '@')) ?>"><?php endif; ?>
    <?php if ($seo['published']): ?><meta property="article:published_time" content="<?= e(date('c', strtotime((string) $seo['published']))) ?>"><?php endif; ?>
    <?php if ($seo['modified']): ?><meta property="article:modified_time" content="<?= e(date('c', strtotime((string) $seo['modified']))) ?>"><?php endif; ?>
    <?php if ($seo['google']): ?><meta name="google-site-verification" content="<?= e($seo['google']) ?>"><?php endif; ?>
    <?php if ($seo['bing']): ?><meta name="msvalidate.01" content="<?= e($seo['bing']) ?>"><?php endif; ?>
    <?php if ($site['favicon_url']): ?><link rel="icon" href="<?= e($site['favicon_url']) ?>"><?php endif; ?>
    <?= BrandStyle::fontLinks($site) ?>
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
    <style nonce="<?= e(nonce()) ?>"><?= BrandStyle::css($site) ?></style>
    <?php foreach (Seo::siteGraph() as $item): ?><?= Seo::jsonLd($item) ?><?php endforeach; ?>
    <?php foreach ($graph as $item): ?><?= Seo::jsonLd($item) ?><?php endforeach; ?>
    <?php if ($canvas): ?><link rel="stylesheet" href="<?= e(asset('css/builder-canvas.css')) ?>"><?php else: ?><?= Tracking::head($tracking, $consent) ?><?php endif; ?>
</head>
<body class="site">
<?= $canvas ? '' : Tracking::bodyStart($tracking, $consent) ?>
<a class="skip-link" href="#content">Skip to content</a>
<?php if ($showHeader): ?><?= App\Builder\Chrome::header($page ?? null) ?? View::partial('site/partials/header') ?><?php endif; ?>
<main id="content">
    <?= $content ?>
</main>
<?php if ($showFooter): ?><?= App\Builder\Chrome::footer($page ?? null) ?? View::partial('site/partials/footer') ?><?php endif; ?>
<?php if (!$canvas): ?>
<?= View::partial('site/partials/popups') ?>
<?= View::partial('site/partials/consent') ?>
<?php endif; ?>
<dialog class="form-dialog" id="cta-dialog" aria-label="Enquiry form">
    <button class="dialog-close" type="button" data-close-dialog aria-label="Close">&times;</button>
    <div data-cta-dialog-body></div>
</dialog>
<?php if ($canvas): ?>
<script nonce="<?= e(nonce()) ?>" src="<?= e(asset('js/builder-canvas.js')) ?>"></script>
<?php else: ?>
<script nonce="<?= e(nonce()) ?>" src="<?= e(asset('js/site.js')) ?>" defer></script>
<?= Tracking::bodyEnd($tracking, $consent) ?>
<?php endif; ?>
</body>
</html>
