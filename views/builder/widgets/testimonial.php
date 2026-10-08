<?php
/** @var array $s */
use App\Cms\Icons;
use App\Cms\Ui;

if (trim($s['quote']) === '') {
    return;
}
$stars = max(0, min(5, (int) $s['rating']));
?>
<figure class="bx-testimonial">
    <?php if ($stars): ?><div class="bx-stars" aria-label="<?= $stars ?> out of 5 stars"><?= str_repeat(Icons::svg('star', 'icon', 18), $stars) ?></div><?php endif; ?>
    <blockquote><?= nl2br(e($s['quote'])) ?></blockquote>
    <figcaption>
        <?= $s['photo'] ? Ui::img($s['photo'], $s['name'], 'bx-testimonial-photo') : '' ?>
        <span><strong class="bx-testimonial-name"><?= e($s['name']) ?></strong><?php if ($s['role'] !== ''): ?><br><span class="muted small"><?= e($s['role']) ?></span><?php endif; ?></span>
    </figcaption>
</figure>
