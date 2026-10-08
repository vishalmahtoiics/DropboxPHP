<?php
/** @var array $s @var bool $editor */
use App\Cms\Icons;
use App\Cms\Ui;

$embed = Ui::videoEmbed($s['url']);
if ($embed === '') {
    if ($editor) {
        echo '<div class="bx-placeholder-img">Paste a YouTube or Vimeo address</div>';
    }
    return;
}
$poster = $s['poster'] ?: Ui::youtubeThumb($s['url']);
?>
<figure class="video bx-video">
    <button class="video-facade video-frame" type="button" data-video="<?= e($embed) ?>" aria-label="Play video">
        <?= $poster ? Ui::img($poster, '') : '' ?>
        <span class="play"><?= Icons::svg('play', 'icon', 30) ?></span>
    </button>
</figure>
