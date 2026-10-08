<?php
/** @var array $s */
use App\Services\Countries;
use App\Services\Settings;

$country = Countries::current();
$site = (string) (Countries::setting($country, 'company_name') ?: Settings::get('website', 'site_name', ''));
?>
<p class="bx-copyright"><?= e(strtr((string) $s['text'], ['{year}' => date('Y'), '{site}' => $site])) ?></p>
