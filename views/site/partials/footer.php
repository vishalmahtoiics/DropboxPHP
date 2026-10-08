<?php
use App\Cms\Icons;
use App\Services\Countries;

$fs = $footerSettings;
$phone = Countries::setting($country, 'sales_phone') ?: $site['contact_phone'];
$email = Countries::setting($country, 'sales_email') ?: $site['contact_email'];
$address = Countries::setting($country, 'address') ?: $site['address'];
$copyright = strtr((string) $fs['copyright'], ['{year}' => date('Y'), '{site}' => Countries::setting($country, 'company_name') ?: $site['site_name']]);
$socials = array_filter(['linkedin' => $site['linkedin_url'], 'twitter' => $site['twitter_url'], 'facebook' => $site['facebook_url'], 'instagram' => $site['instagram_url'], 'youtube' => $site['youtube_url']]);
$style = '';
if ($fs['background']) $style .= '--footer-bg:' . $fs['background'] . ';';
if ($fs['text_color']) $style .= '--footer-text:' . $fs['text_color'] . ';';
?>
<footer class="site-footer" style="<?= e($style) ?>">
    <div class="container footer-grid">
        <div class="footer-about">
            <a class="logo" href="<?= e(Countries::href($country, '/')) ?>">
                <?php if ($site['logo_dark_url'] ?: $site['logo_url']): ?>
                    <img src="<?= e($site['logo_dark_url'] ?: $site['logo_url']) ?>" alt="<?= e($site['site_name']) ?>" height="34" loading="lazy">
                <?php else: ?>
                    <?= App\Services\Brand::mark() ?><span class="logo-text"><?= e($site['site_name']) ?></span>
                <?php endif; ?>
            </a>
            <?php if ($fs['about']): ?><p><?= e($fs['about']) ?></p><?php endif; ?>
            <?php if ($fs['show_contact']): ?>
                <ul class="footer-contact">
                    <?php if ($phone): ?><li><?= Icons::svg('phone', 'icon', 16) ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
                    <?php if ($email): ?><li><?= Icons::svg('mail', 'icon', 16) ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
                    <?php if ($address): ?><li><?= Icons::svg('map-pin', 'icon', 16) ?><span><?= e($address) ?></span></li><?php endif; ?>
                </ul>
            <?php endif; ?>
            <?php if ($fs['show_social'] && $socials): ?>
                <div class="socials">
                    <?php foreach ($socials as $net => $url): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($net)) ?>"><?= Icons::svg($net, 'icon', 18) ?></a><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php foreach ($footerNav as $col): ?>
            <div class="footer-col">
                <h2 class="footer-title"><?= e($col['label']) ?></h2>
                <ul>
                    <?php foreach ($col['children'] ?: [$col] as $item): ?>
                        <li><a href="<?= e($item['href']) ?>"<?= (int) $item['open_in_new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($item['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
        <?php if ($fs['show_newsletter'] && $fs['newsletter_form_slug'] && ($nl = App\Services\Forms::bySlug($fs['newsletter_form_slug']))): ?>
            <div class="footer-col footer-newsletter">
                <h2 class="footer-title">Newsletter</h2>
                <?= App\Core\View::partial('site/partials/form', ['form' => $nl, 'ctaLocation' => 'Footer newsletter', 'compact' => true]) ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="container footer-bottom">
        <span><?= e($copyright) ?></span>
        <ul class="legal-links">
            <?php foreach (array_merge($footerSecondaryNav, $legalNav) as $item): ?><li><a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a></li><?php endforeach; ?>
            <?php if (($tracking['consent_required'] ?? false)): ?><li><button type="button" class="link-like" data-consent-open>Cookie settings</button></li><?php endif; ?>
        </ul>
    </div>
</footer>
