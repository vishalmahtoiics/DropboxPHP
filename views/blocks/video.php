<?php
use App\Cms\Icons;
use App\Cms\Ui;

$embed = Ui::videoEmbed($c['url']);
if ($embed === '') {
    return;
}
$poster = $c['poster'] ?: Ui::youtubeThumb($c['url']);
?>
<div class="container medium">
    <?= Ui::head($c, $h) ?>
    <figure class="video">
        <button class="video-facade" type="button" data-video="<?= e($embed) ?>" aria-label="Play video<?= $c['heading'] ? ': ' . e($c['heading']) : '' ?>">
            <?= $poster ? Ui::img($poster, '') : '' ?>
            <span class="play"><?= Icons::svg('play', 'icon', 30) ?></span>
        </button>
        <?php if ($c['caption']): ?><figcaption><?= e($c['caption']) ?></figcaption><?php endif; ?>
    </figure>
</div>
