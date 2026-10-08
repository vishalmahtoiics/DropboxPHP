<?php
/** @var array $s */
use App\Cms\Icons;
use App\Core\View;
use App\Services\Countries;
use App\Services\Settings;

$site = Settings::group('website');
$country = Countries::current();
$name = (string) (Countries::setting($country, 'company_name') ?: $site['site_name']);
$logo = $s['dark'] && $site['logo_dark_url'] ? $site['logo_dark_url'] : $site['logo_url'];
$showLogo = $s['show'] !== 'name';
$showName = $s['show'] !== 'logo' || !$logo;
?>
<a class="bx-logo" href="<?= e(Countries::href(null, '/')) ?>" aria-label="<?= e($site['site_name']) ?> home">
    <?php if ($showLogo && $logo): ?><img src="<?= e($logo) ?>" alt="<?= $showName ? '' : e($site['site_name']) ?>" height="36"><?php elseif ($showLogo): ?><?= App\Services\Brand::mark() ?><?php endif; ?>
    <?php if ($showName): ?><span class="bx-logo-name"><?= e($site['site_name']) ?></span><?php endif; ?>
</a>
