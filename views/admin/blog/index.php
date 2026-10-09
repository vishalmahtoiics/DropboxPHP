<?php
use App\Cms\Icons;
use App\Http\Form;

$actions = (can('blog.create') ? '<a class="btn btn-primary" href="/admin/blog/new">' . Icons::svg('plus', 'icon', 16) . ' New article</a> ' : '')
    . (can('blog.categories') ? '<a class="btn btn-secondary" href="/admin/blog/categories">Categories & tags</a> ' : '')
    . (can('blog.design') ? '<a class="btn btn-ghost" href="/admin/blog/settings">' . Icons::svg('settings', 'icon', 16) . '</a>' : '');
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Articles', 'sub' => 'Blog posts for ' . $adminCountry['name'] . '.', 'actions' => $actions]) ?>
<div class="grid grid-4 mb-2">
    <div class="card glass stat"><div class="stat-icon green"><?= Icons::svg('check-circle') ?></div><div><div class="stat-label">Published</div><div class="stat-value"><?= (int) ($counts['PUBLISHED'] ?? 0) ?></div></div></div>
    <div class="card glass stat"><div class="stat-icon orange"><?= Icons::svg('edit') ?></div><div><div class="stat-label">Drafts</div><div class="stat-value"><?= (int) ($counts['DRAFT'] ?? 0) ?></div></div></div>
    <div class="card glass stat"><div class="stat-icon violet"><?= Icons::svg('clock') ?></div><div><div class="stat-label">Scheduled</div><div class="stat-value"><?= (int) ($counts['SCHEDULED'] ?? 0) ?></div></div></div>
    <div class="card glass stat"><div class="stat-icon"><?= Icons::svg('eye') ?></div><div><div class="stat-label">Total views</div><div class="stat-value"><?= number_format($views) ?></div></div></div>
</div>
<div class="card">
    <form class="toolbar" method="get">
        <input class="input search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search articles">
        <select class="select" name="status" data-autosubmit><option value="">All statuses</option><?php foreach ($statuses as $k => $v): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
        <select class="select" name="category" data-autosubmit><option value="">All categories</option><?php foreach ($categories as $k => $v): ?><option value="<?= (int) $k ?>"<?= (int) ($_GET['category'] ?? 0) === (int) $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    </form>
    <?php if (!$rows): ?><div class="empty"><?= Icons::svg('pen', 'icon', 32) ?><p>No articles yet.</p></div><?php else: ?>
    <?php
    $bulk = [];
    if (can('blog.publish')) {
        $bulk += ['publish' => 'Publish', 'draft' => 'Move to draft', 'archive' => 'Archive'];
    }
    if (can('blog.delete')) {
        $bulk['delete'] = 'Move to recycle bin';
    }
    ?>
    <?= Form::bulkBar('bulk-posts', '/admin/blog/bulk', $bulk, 'articles') ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><?php if ($bulk): ?><th class="check"><?= Form::bulkAll('bulk-posts') ?></th><?php endif; ?><th>Title</th><th class="hide-sm">Category</th><th>Status</th><th class="hide-sm">Views</th><th>Date</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr>
                <?php if ($bulk): ?><td class="check"><?= Form::bulkCheck('bulk-posts', (int) $r['id'], $r['title']) ?></td><?php endif; ?>
                <td><a class="row-title" href="/admin/blog/<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a><?php if ((int) $r['is_featured']): ?> <span class="badge blue">Featured</span><?php endif; ?><div class="small muted"><?= e($r['author_name'] ?? '') ?> · <?= (int) $r['reading_time'] ?> min</div></td>
                <td class="hide-sm small"><?= e($r['category_name'] ?? '—') ?></td>
                <td><?= Form::badge($r['status']) ?></td>
                <td class="hide-sm"><?= number_format((int) $r['view_count']) ?></td>
                <td class="small muted"><?= e(format_date($r['published_at'] ?: $r['created_at'])) ?></td>
                <td class="actions"><a class="btn btn-ghost btn-sm" href="/preview/post/<?= (int) $r['id'] ?>" target="_blank">Preview</a><?php if (can('blog.delete')): ?><?= Form::action('/admin/blog/' . $r['id'] . '/delete', 'Delete', 'btn btn-danger-ghost btn-sm', 'Move this article to the recycle bin?') ?><?php endif; ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= Form::pagination($pageNo, $pages, $total, 'articles') ?>
    <?php endif; ?>
</div>
