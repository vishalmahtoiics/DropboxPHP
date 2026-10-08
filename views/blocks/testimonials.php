<?php use App\Cms\Icons; use App\Cms\Ui; ?>
<div class="container">
    <?= Ui::head($c, $h) ?>
    <div class="grid cols-<?= (int) $c['columns'] ?> testimonials">
        <?php foreach ($c['items'] as $item): ?>
            <figure class="testimonial">
                <?php if ((int) $item['rating'] > 0): ?>
                    <div class="stars" aria-label="<?= (int) $item['rating'] ?> out of 5"><?= str_repeat(Icons::svg('star', 'icon star', 16), (int) $item['rating']) ?></div>
                <?php endif; ?>
                <blockquote><p><?= nl2br(e($item['quote'])) ?></p></blockquote>
                <figcaption>
                    <?php if ($item['avatar']): ?><?= Ui::img($item['avatar'], '', 'avatar') ?><?php else: ?><span class="avatar"><?= e(mb_substr($item['name'], 0, 1)) ?></span><?php endif; ?>
                    <span><strong><?= e($item['name']) ?></strong><small><?= e(trim($item['role'] . ($item['company'] ? ', ' . $item['company'] : ''), ', ')) ?></small></span>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
</div>
