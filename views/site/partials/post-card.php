<?php
use App\Cms\Ui;
use App\Services\Content;

$url = Content::postUrl($post);
?>
<article class="post-card">
    <?php if (!empty($post['image_url'])): ?><a class="post-card-image" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true"><?= Ui::img($post['image_url'], (string) ($post['image_alt'] ?? '')) ?></a><?php endif; ?>
    <div class="post-card-body">
        <?php if (!empty($post['category_name'])): ?><span class="eyebrow small"><?= e($post['category_name']) ?></span><?php endif; ?>
        <h3><a href="<?= e($url) ?>"><?= e($post['title']) ?></a></h3>
        <?php if (($excerpt ?? true) && $post['excerpt']): ?><p class="muted"><?= e(str_limit($post['excerpt'], 160)) ?></p><?php endif; ?>
        <p class="post-meta"><time datetime="<?= e(date('c', strtotime((string) $post['published_at']))) ?>"><?= e(format_date($post['published_at'])) ?></time> · <?= (int) $post['reading_time'] ?> min read</p>
    </div>
</article>
