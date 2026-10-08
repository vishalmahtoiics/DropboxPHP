<?php
use App\Cms\Icons;
use App\Cms\Ui;
use App\Services\Countries;
use App\Services\Settings;

$country = Countries::current();
$site = Settings::group('website');
$rows = array_filter([
    $c['show_phone'] && ($v = Countries::setting($country, 'sales_phone') ?: $site['contact_phone']) ? ['phone', 'Call us', $v, 'tel:' . preg_replace('/[^\d+]/', '', $v)] : null,
    $c['show_email'] && ($v = Countries::setting($country, 'sales_email') ?: $site['contact_email']) ? ['mail', 'Email', $v, 'mailto:' . $v] : null,
    $c['show_whatsapp'] && ($v = Countries::setting($country, 'whatsapp') ?: $site['whatsapp_number']) ? ['whatsapp', 'WhatsApp', $v, 'https://wa.me/' . preg_replace('/\D/', '', $v)] : null,
    $c['show_address'] && ($v = Countries::setting($country, 'address') ?: $site['address']) ? ['map-pin', 'Visit', $v, $c['map_url'] ?: null] : null,
    $c['show_hours'] && ($v = Countries::setting($country, 'business_hours')) ? ['clock', 'Hours', $v, null] : null,
]);
if (!$rows) {
    return;
}
?>
<div class="container">
    <?= Ui::head($c, $h) ?>
    <ul class="grid cols-<?= min(4, count($rows)) ?> contact-cards">
        <?php foreach ($rows as [$icon, $label, $value, $href]): ?>
            <li class="contact-card">
                <span class="feature-icon"><?= Icons::svg($icon, 'icon', 22) ?></span>
                <strong><?= e($label) ?></strong>
                <?php if ($href): ?><a href="<?= e($href) ?>"<?= str_starts_with($href, 'http') ? ' target="_blank" rel="noopener"' : '' ?>><?= e($value) ?></a><?php else: ?><span><?= nl2br(e($value)) ?></span><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
