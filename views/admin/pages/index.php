<?php
use App\Cms\Icons;
use App\Core\View;
use App\Http\Form;
use App\Services\Countries;

$actions = can('pages.create') ? '<a class="btn btn-primary" href="/admin/pages/new">' . Icons::svg('plus', 'icon', 16) . ' New page</a>' : '';
?>
<?= View::partial('admin/partials/page-head', ['heading' => 'Pages', 'sub' => 'Every page on the ' . $adminCountry['name'] . ' site, the homepage included.', 'actions' => $actions . (can('pages.edit') ? ' <a class="btn btn-secondary" href="/admin/page-categories">Categories</a>' : '')]) ?>
<div class="grid grid-4 mb-2">
    <?php foreach (['PUBLISHED' => ['Published', 'check-circle', 'green'], 'DRAFT' => ['Drafts', 'edit', 'orange'], 'SCHEDULED' => ['Scheduled', 'clock', 'violet'], 'ARCHIVED' => ['Archived', 'box', '']] as $st => [$label, $icon, $tone]): ?>
        <a class="card glass stat" href="?status=<?= e($st) ?>" style="color:inherit;text-decoration:none"><div class="stat-icon <?= e($tone) ?>"><?= Icons::svg($icon) ?></div><div><div class="stat-label"><?= e($label) ?></div><div class="stat-value"><?= (int) ($counts[$st] ?? 0) ?></div></div></a>
    <?php endforeach; ?>
</div>
<div class="card">
    <form class="toolbar" method="get">
        <input class="input search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search title or address">
        <select class="select" name="status" data-autosubmit><option value="">All statuses</option><?php foreach ($statuses as $k => $v): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
        <?php if ($categories): ?><select class="select" name="category" data-autosubmit><option value="">All categories</option><?php foreach ($categories as $k => $v): ?><option value="<?= (int) $k ?>"<?= $category === (int) $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select><?php endif; ?>
        <button class="btn btn-secondary" type="submit">Filter</button>
        <?php if ($q || $status || $category): ?><a class="btn btn-ghost" href="/admin/pages">Reset</a><?php endif; ?>
    </form>
    <?php if (!$rows): ?>
        <div class="empty"><?= Icons::svg('file-text', 'icon', 32) ?><p>No pages match.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><?= Form::sortTh('title', 'Title', $sort, $dir) ?><?= Form::sortTh('slug', 'Address', $sort, $dir) ?><?= Form::sortTh('status', 'Status', $sort, $dir) ?><th class="hide-sm">Sections</th><?= Form::sortTh('updated_at', 'Updated', $sort, $dir) ?><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $path = Countries::href($adminCountry, '/' . $r['slug']); ?>
            <tr>
                <td><a class="row-title" href="/admin/pages/<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a><?php if ($r['slug'] === ''): ?> <span class="badge blue">Homepage</span><?php endif; ?><?php if ($r['category_name']): ?><div class="small muted"><?= e($r['category_name']) ?></div><?php endif; ?></td>
                <td class="small"><a href="<?= e($path) ?>" target="_blank" rel="noopener"><?= e($path) ?></a></td>
                <td><?= Form::badge($r['status']) ?><?php if ((int) $r['no_index']): ?> <span class="badge">noindex</span><?php endif; ?></td>
                <td class="hide-sm"><?= (int) $r['sections'] ?></td>
                <td class="small muted"><?= e(time_ago($r['updated_at'])) ?><?php if ($r['editor']): ?><br><?= e($r['editor']) ?><?php endif; ?></td>
                <td class="actions">
                    <a class="btn btn-ghost btn-sm" href="/preview/page/<?= (int) $r['id'] ?>" target="_blank">Preview</a>
                    <?php if (can('pages.create')): ?><?= Form::action('/admin/pages/' . $r['id'] . '/duplicate', 'Duplicate') ?><?php endif; ?>
                    <?php if (can('pages.delete') && $r['slug'] !== ''): ?><?= Form::action('/admin/pages/' . $r['id'] . '/delete', 'Delete', 'btn btn-danger-ghost btn-sm', 'Move "' . $r['title'] . '" to the recycle bin?') ?><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= Form::pagination($pageNo, $pages, $total, 'pages') ?>
    <?php endif; ?>
</div>
