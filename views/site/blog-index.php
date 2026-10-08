<?php
use App\Cms\Icons;
use App\Core\View;

$qs = static fn (int $n) => $basePath . ($n > 1 || $q !== '' ? '?' . http_build_query(array_filter(['q' => $q, 'page' => $n > 1 ? $n : null])) : '');
?>
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(App\Services\Countries::href(null, '/')) ?>">Home</a> / <?php if ($heading !== $blogSettings['title']): ?><a href="<?= e($blogBase) ?>"><?= e($blogSettings['title']) ?></a> / <?php endif; ?><span><?= e($heading) ?></span></nav>
        <h1><?= e($heading) ?></h1>
        <?php if ($intro): ?><p class="lead"><?= e($intro) ?></p><?php endif; ?>
        <form class="blog-search" action="<?= e($blogBase) ?>" method="get" role="search">
            <label class="sr-only" for="blog-q">Search articles</label>
            <input id="blog-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search articles" maxlength="100">
            <button class="btn btn-primary" type="submit" aria-label="Search"><?= Icons::svg('search', 'icon', 18) ?></button>
        </form>
        <?php if ($categories): ?>
            <ul class="chips">
                <li><a class="chip<?= !$activeCategory ? ' active' : '' ?>" href="<?= e($blogBase) ?>">All</a></li>
                <?php foreach ($categories as $cat): ?><?php if ((int) $cat['post_count'] === 0) continue; ?>
                    <li><a class="chip<?= (int) $activeCategory === (int) $cat['id'] ? ' active' : '' ?>" href="<?= e($blogBase . '/category/' . $cat['slug']) ?>"><?= e($cat['name']) ?> <span><?= (int) $cat['post_count'] ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
<section class="cms-section">
    <div class="container">
        <?php if ($q !== ''): ?><p class="muted"><?= (int) $total ?> result<?= $total === 1 ? '' : 's' ?> for “<?= e($q) ?>”.</p><?php endif; ?>
        <?php if (!$posts): ?>
            <p class="empty-state">No articles yet<?= $q !== '' ? ' match your search' : '' ?>.</p>
        <?php else: ?>
            <div class="grid cols-3 post-grid">
                <?php foreach ($posts as $post): ?><?= View::partial('site/partials/post-card', ['post' => $post]) ?><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php if ($pageNo > 1): ?><a href="<?= e($qs($pageNo - 1)) ?>" rel="prev">← Newer</a><?php endif; ?>
                <span>Page <?= (int) $pageNo ?> of <?= (int) $pages ?></span>
                <?php if ($pageNo < $pages): ?><a href="<?= e($qs($pageNo + 1)) ?>" rel="next">Older →</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>
