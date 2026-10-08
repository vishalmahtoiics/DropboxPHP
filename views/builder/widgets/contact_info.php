<?php
/** @var array $s @var bool $editor */
use App\Cms\Icons;
use App\Services\Countries;
use App\Services\Settings;

$country = Countries::current();
$site = Settings::group('website');
$rows = [];
$phone = (string) (Countries::setting($country, 'sales_phone') ?: $site['contact_phone']);
$email = (string) (Countries::setting($country, 'sales_email') ?: $site['contact_email']);
$wa = (string) (Countries::setting($country, 'whatsapp') ?: $site['whatsapp_number']);
$address = (string) (Countries::setting($country, 'address') ?: $site['address']);
$hours = (string) Countries::setting($country, 'business_hours');
if ($s['phone'] && $phone !== '') $rows[] = ['phone', $phone, 'tel:' . preg_replace('/[^0-9+]/', '', $phone)];
if ($s['email'] && $email !== '') $rows[] = ['mail', $email, 'mailto:' . $email];
if ($s['whatsapp'] && $wa !== '') $rows[] = ['whatsapp', 'WhatsApp', 'https://wa.me/' . preg_replace('/\D/', '', $wa)];
if ($s['address'] && $address !== '') $rows[] = ['map-pin', $address, ''];
if ($s['hours'] && $hours !== '') $rows[] = ['clock', $hours, ''];
if (!$rows) {
    if ($editor) {
        echo '<div class="bx-note">No contact details are set for this market yet.</div>';
    }
    return;
}
?>
<ul class="bx-contact bx-<?= e($s['layout']) ?>">
    <?php foreach ($rows as [$icon, $text, $href]): ?>
        <li><?= Icons::svg($icon, 'icon', 18) ?><?= $href !== '' ? '<a href="' . e($href) . '"' . (str_starts_with($href, 'https:') ? ' target="_blank" rel="noopener"' : '') . '>' . e($text) . '</a>' : '<span>' . nl2br(e($text)) . '</span>' ?></li>
    <?php endforeach; ?>
</ul>
