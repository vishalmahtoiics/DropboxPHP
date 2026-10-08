<?php
use App\Cms\Icons;
use App\Cms\Ui;
use App\Core\View;
use App\Services\Countries;

$share = rawurlencode(absolute_url($url));
$title = rawurlencode($post['title']);
?>
<?php if (!empty($preview)): ?><div class="preview-bar">Preview — <?= e(ucfirst(strtolower($post['status']))) ?></div><?php endif; ?>
<article class="article">
    <header class="page-hero article-head">
        <div class="container medium">
            <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(Countries::href(null, '/')) ?>">Home</a> / <a href="<?= e($blogBase) ?>"><?= e($blogSettings['title']) ?></a><?php if ($post['category_name']): ?> / <a href="<?= e($blogBase . '/category/' . $post['category_slug']) ?>"><?= e($post['category_name']) ?></a><?php endif; ?></nav>
            <h1><?= e($post['title']) ?></h1>
            <?php if ($post['subtitle']): ?><p class="lead"><?= e($post['subtitle']) ?></p><?php endif; ?>
            <p class="post-meta">
                <?php if ($blogSettings['show_author'] && $post['author_name']): ?>By <?= e($post['author_name']) ?> · <?php endif; ?>
                <time datetime="<?= e(date('c', strtotime((string) ($post['published_at'] ?: $post['created_at'])))) ?>"><?= e(format_date($post['published_at'] ?: $post['created_at'])) ?></time>
                <?php if ($blogSettings['show_reading_time']): ?> · <?= (int) $post['reading_time'] ?> min read<?php endif; ?>
            </p>
        </div>
    </header>
    <?php if ($post['image_url']): ?><div class="container medium article-image"><?= Ui::img($post['image_url'], (string) ($post['image_alt'] ?? $post['title']), 'rounded', false) ?></div><?php endif; ?>
    <div class="container article-layout<?= $blogSettings['sidebar'] ? ' with-sidebar' : '' ?>">
        <div class="article-main">
            <?php if ($toc && count($toc) >= 3): ?>
                <nav class="toc" aria-label="On this page"><strong>On this page</strong>
                    <ol><?php foreach ($toc as $t): ?><li class="lvl-<?= (int) $t['level'] ?>"><a href="#<?= e($t['id']) ?>"><?= e($t['text']) ?></a></li><?php endforeach; ?></ol>
                </nav>
            <?php endif; ?>
            <div class="prose article-body"><?= $html ?></div>
            <?php if ($tags): ?>
                <ul class="chips mt"><?php foreach ($tags as $t): ?><li><a class="chip" href="<?= e($blogBase . '/tag/' . $t['slug']) ?>">#<?= e($t['name']) ?></a></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?php if ($blogSettings['show_share']): ?>
                <div class="share"><span>Share</span>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $share ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><?= Icons::svg('linkedin', 'icon', 18) ?></a>
                    <a href="https://twitter.com/intent/tweet?url=<?= $share ?>&amp;text=<?= $title ?>" target="_blank" rel="noopener" aria-label="Share on X"><?= Icons::svg('twitter', 'icon', 18) ?></a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $share ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><?= Icons::svg('facebook', 'icon', 18) ?></a>
                    <a href="https://wa.me/?text=<?= $title ?>%20<?= $share ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><?= Icons::svg('whatsapp', 'icon', 18) ?></a>
                    <button type="button" data-copy-link="<?= e(absolute_url($url)) ?>" aria-label="Copy link"><?= Icons::svg('link', 'icon', 18) ?></button>
                </div>
            <?php endif; ?>
            <?php if ($blogSettings['show_author'] && $post['author_name'] && $post['author_bio']): ?>
                <aside class="author-box">
                    <?php if ($post['author_avatar']): ?><?= Ui::img($post['author_avatar'], '', 'avatar') ?><?php else: ?><span class="avatar"><?= e(mb_substr($post['author_name'], 0, 1)) ?></span><?php endif; ?>
                    <div><strong><?= e($post['author_name']) ?></strong><?php if ($post['author_title']): ?><small><?= e($post['author_title']) ?></small><?php endif; ?><p><?= e($post['author_bio']) ?></p></div>
                </aside>
            <?php endif; ?>
        </div>
        <?php if ($blogSettings['sidebar']): ?>
            <aside class="article-sidebar">
                <?php if ($blogSettings['cta_heading']): ?>
                    <div class="card sidebar-cta">
                        <h2><?= e($blogSettings['cta_heading']) ?></h2>
                        <p><?= e($blogSettings['cta_text']) ?></p>
                        <?= Ui::button($blogSettings['cta_label'], $blogSettings['cta_url'], 'primary', 'btn-block') ?>
                    </div>
                <?php endif; ?>
                <?php if ($categories): ?>
                    <div class="card"><h2>Categories</h2><ul class="link-list"><?php foreach ($categories as $cat): ?><?php if ((int) $cat['post_count']): ?><li><a href="<?= e($blogBase . '/category/' . $cat['slug']) ?>"><?= e($cat['name']) ?> <span><?= (int) $cat['post_count'] ?></span></a></li><?php endif; ?><?php endforeach; ?></ul></div>
                <?php endif; ?>
            </aside>
        <?php endif; ?>
    </div>
    <?php if ($related): ?>
        <section class="cms-section preset-muted">
            <div class="container"><h2 class="section-title">Related articles</h2>
                <div class="grid cols-3 post-grid"><?php foreach ($related as $r): ?><?= View::partial('site/partials/post-card', ['post' => $r]) ?><?php endforeach; ?></div>
            </div>
        </section>
    <?php endif; ?>
</article>
