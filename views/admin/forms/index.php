<?php
use App\Cms\Icons;
use App\Http\Form;
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Forms', 'sub' => 'One renderer draws every form on the site — sections, heroes, popups and product buttons.', 'actions' => can('forms.create') ? '<a class="btn btn-primary" href="/admin/forms/new">' . Icons::svg('plus', 'icon', 16) . ' New form</a>' : '']) ?>
<div class="card">
    <?php if (!$rows): ?><div class="empty"><?= Icons::svg('list', 'icon', 32) ?><p>No forms yet.</p></div><?php else: ?>
    <?php
    $bulk = can('forms.edit') ? ['activate' => 'Switch on', 'deactivate' => 'Switch off'] : [];
    if (can('forms.delete')) {
        $bulk['delete'] = 'Move to recycle bin';
    }
    ?>
    <?= Form::bulkBar('bulk-forms', '/admin/forms/bulk', $bulk, 'forms') ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><?php if ($bulk): ?><th class="check"><?= Form::bulkAll('bulk-forms') ?></th><?php endif; ?><th>Form</th><th>Key</th><th>Fields</th><th>Submissions</th><th class="hide-sm">Last 30 days</th><th>Status</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr>
                <?php if ($bulk): ?><td class="check"><?= Form::bulkCheck('bulk-forms', (int) $r['id'], $r['name']) ?></td><?php endif; ?>
                <td><a class="row-title" href="/admin/forms/<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a><?php if ($r['country_name']): ?><div class="small muted"><?= e($r['country_name']) ?> only</div><?php endif; ?></td>
                <td><code><?= e($r['slug']) ?></code></td>
                <td><?= (int) $r['fields'] ?></td>
                <td><a href="/admin/forms/<?= (int) $r['id'] ?>/submissions"><?= number_format((int) $r['submissions']) ?></a></td>
                <td class="hide-sm"><?= (int) $r['recent'] ?></td>
                <td><?= (int) $r['is_active'] ? Form::badge('ACTIVE', 'Active') : Form::badge('INACTIVE', 'Inactive') ?></td>
                <td class="actions">
                    <?php if (can('forms.create')): ?><?= Form::action('/admin/forms/' . $r['id'] . '/duplicate', 'Duplicate') ?><?php endif; ?>
                    <?php if (can('forms.delete')): ?><?= Form::action('/admin/forms/' . $r['id'] . '/delete', 'Delete', 'btn btn-danger-ghost btn-sm', 'Move this form to the recycle bin? Sections using it will show nothing.') ?><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div>
