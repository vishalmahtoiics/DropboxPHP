<?php
/** @var array $s */
use App\Cms\Icons;
use App\Cms\Ui;

$names = ['linkedin' => 'LinkedIn', 'twitter' => 'X', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp', 'mail' => 'Email', 'phone' => 'Phone', 'globe' => 'Website'];
?>
<div class="bx-social">
    <?php foreach ($s['items'] as $item): ?>
        <?php $href = Ui::href($item['link']); if ($href === '') continue; ?>
        <a href="<?= e($href) ?>" target="_blank" rel="noopener" aria-label="<?= e($names[$item['icon']] ?? 'Link') ?>"><?= Icons::svg($item['icon'], 'icon', 20) ?></a>
    <?php endforeach; ?>
</div>
