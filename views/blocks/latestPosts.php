<?php
use App\Cms\Icons;
use App\Cms\Ui;
use App\Core\DB;
use App\Core\View;
use App\Services\Content;
use App\Services\Countries;

$country = Countries::current();
$catId = $c['category'] !== '' ? DB::value('SELECT id FROM blog_categories WHERE slug = ?', [$c['category']]) : null;
$posts = Content::posts((int) $country['id'], ['category' => $catId], 1, (int) $c['count'])['items'];
if (!$posts) {
    return;
}
?>
<div class="container">
    <?= Ui::head($c, $h) ?>
    <div class="grid cols-<?= (int) $c['columns'] ?> post-grid">
        <?php foreach ($posts as $post): ?><?= View::partial('site/partials/post-card', ['post' => $post, 'excerpt' => $c['show_excerpt']]) ?><?php endforeach; ?>
    </div>
    <?php if ($c['link_label']): ?><p class="center mt"><a class="btn btn-secondary" href="<?= e(Content::blogBase($country)) ?>"><?= e($c['link_label']) ?> <?= Icons::svg('arrow-right', 'icon', 16) ?></a></p><?php endif; ?>
</div>
