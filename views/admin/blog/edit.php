<?php
use App\Cms\Icons;
use App\Http\Form;

$v = $post ?? [];
$action = $post ? '/admin/blog/' . $post['id'] : '/admin/blog';
$actions = $post ? '<a class="btn btn-secondary" href="/preview/post/' . (int) $post['id'] . '" target="_blank">' . Icons::svg('eye', 'icon', 16) . ' Preview</a>' . ($post['status'] === 'PUBLISHED' ? ' <a class="btn btn-ghost" href="' . e($publicUrl) . '" target="_blank">View live</a>' : '') : '';
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => $post ? $post['title'] : 'New article', 'crumbs' => [['Articles', '/admin/blog'], [$post ? 'Edit' : 'New', null]], 'actions' => $actions]) ?>
<form method="post" action="<?= e($action) ?>" data-guard>
    <?= csrf_field() ?>
    <div class="builder">
        <div>
            <div class="card"><div class="card-body">
                <?= Form::input('title', 'Title', $v['title'] ?? '', ['required' => true, 'maxlength' => 255, 'data' => ['slug-source' => 'post-slug']]) ?>
                <?= Form::input('subtitle', 'Subtitle', $v['subtitle'] ?? '', ['maxlength' => 255]) ?>
                <?= Form::input('slug', 'Address', $v['slug'] ?? '', ['id' => 'post-slug', 'prefix' => App\Services\Content::blogBase($adminCountry) . '/']) ?>
                <?= Form::richtext('content', 'Content', $v['content'] ?? '', ['minHeight' => '420px', 'help' => 'Use Heading 2 and 3 for sections — they build the table of contents.']) ?>
                <?= Form::textarea('excerpt', 'Excerpt', $v['excerpt'] ?? '', ['rows' => 3, 'maxlength' => 1000, 'help' => 'Shown on cards. Blank = the first lines of the article.']) ?>
            </div></div>
            <div class="card" data-tabs="post-seo">
                <div class="card-head"><h2>Search & sharing</h2></div>
                <div class="card-body">
                    <?= Form::input('seo_title', 'SEO title', $v['seo_title'] ?? '', ['maxlength' => 255, 'data' => ['count' => 60]]) ?>
                    <?= Form::textarea('seo_description', 'Meta description', $v['seo_description'] ?? '', ['rows' => 2, 'maxlength' => 500, 'data' => ['count' => 160]]) ?>
                    <?= Form::input('focus_keyword', 'Focus keyword(s)', $v['focus_keyword'] ?? '', ['help' => 'Comma-separated.']) ?>
                    <?= Form::input('canonical_url', 'Canonical URL', $v['canonical_url'] ?? '') ?>
                    <div class="grid grid-2"><?= Form::toggle('no_index', 'noindex', $v['no_index'] ?? 0) ?><?= Form::toggle('no_follow', 'nofollow', $v['no_follow'] ?? 0) ?></div>
                    <?= Form::input('og_title', 'Share title', $v['og_title'] ?? '') ?>
                    <?= Form::textarea('og_description', 'Share description', $v['og_description'] ?? '', ['rows' => 2]) ?>
                    <?= Form::media('og_image', 'Share image', $ogUrl, ['kind' => 'IMAGE', 'help' => 'Blank = featured image.']) ?>
                    <?php if ($report): ?><?= App\Core\View::partial('admin/partials/seo-checks', ['report' => $report]) ?><?php endif; ?>
                </div>
            </div>
        </div>
        <aside>
            <div class="card"><div class="card-head"><h2>Publish</h2></div><div class="card-body stack">
                <?= Form::select('status', 'Status', $statuses, $v['status'] ?? 'DRAFT') ?>
                <?= Form::input('published_at', 'Publish date', !empty($v['published_at']) ? date('Y-m-d\TH:i', strtotime((string) $v['published_at'])) : '', ['type' => 'datetime-local']) ?>
                <?= Form::select('author_id', 'Author', $authors, (string) ($v['author_id'] ?? $me['id'])) ?>
                <?= Form::toggle('is_featured', 'Featured article', $v['is_featured'] ?? 0) ?>
                <?php if (can($post ? 'blog.edit' : 'blog.create')): ?><button class="btn btn-primary btn-block" type="submit"><?= $post ? 'Save article' : 'Create article' ?></button><?php endif; ?>
            </div></div>
            <div class="card"><div class="card-head"><h2>Organise</h2></div><div class="card-body stack">
                <?= Form::select('category_id', 'Category', $categories, (string) ($v['category_id'] ?? ''), ['empty' => 'Uncategorised']) ?>
                <div class="field"><span class="label">Tags</span>
                    <div style="max-height:180px;overflow:auto;display:grid;gap:.3rem">
                        <?php foreach ($tags as $t): ?><label class="check"><input type="checkbox" name="tags[]" value="<?= (int) $t['id'] ?>"<?= in_array((int) $t['id'], $selectedTags, true) ? ' checked' : '' ?>> <?= e($t['name']) ?></label><?php endforeach; ?>
                    </div>
                </div>
                <?php if (can('blog.tags')): ?><?= Form::input('new_tags', 'New tags', '', ['placeholder' => 'Comma-separated']) ?><?php endif; ?>
            </div></div>
            <div class="card"><div class="card-head"><h2>Featured image</h2></div><div class="card-body"><?= Form::media('image', '', $imageUrl, ['kind' => 'IMAGE']) ?></div></div>
        </aside>
    </div>
</form>
