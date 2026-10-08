<?php
/** @var array $s @var bool $editor */
use App\Cms\Ui;

$img = Ui::img($s['src'], $s['alt'], '', (bool) $s['lazy']);
if ($img === '') {
    if ($editor) {
        echo '<div class="bx-placeholder-img">Choose an image</div>';
    }
    return;
}
$href = $s['link'] !== '' ? Ui::href($s['link']) : '';
?>
<figure class="bx-image">
    <?= $href !== '' ? '<a href="' . e($href) . '">' . $img . '</a>' : $img ?>
    <?php if ($s['caption'] !== ''): ?><figcaption><?= e($s['caption']) ?></figcaption><?php endif; ?>
</figure>
