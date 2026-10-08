<?php
/** @var array $s @var array $product */
use App\Cms\Ui;
use App\Services\Content;

$urls = [];
foreach ($product['gallery_ids'] ?? [] as $id) {
    if ($url = Content::mediaUrl((int) $id)) {
        $urls[] = $url;
    }
}
if (!$urls) {
    return;
}
?>
<div class="bx-gallery" style="--cols:<?= (int) $s['columns'] ?>"><?php foreach ($urls as $u): ?><?= Ui::img($u, $product['name'], 'rounded') ?><?php endforeach; ?></div>
