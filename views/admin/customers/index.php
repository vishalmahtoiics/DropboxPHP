<?php
use App\Cms\Icons;
use App\Http\Form;
use App\Http\Controllers\Admin\CustomerController;
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Customers', 'sub' => 'Won leads become customers; a customer is reused when the same email enquires again.', 'actions' => can('customers.create') ? '<a class="btn btn-primary" href="/admin/customers/new">' . Icons::svg('plus', 'icon', 16) . ' New customer</a>' : '']) ?>
<div class="grid grid-4 mb-2">
    <div class="card glass stat"><div class="stat-icon green"><?= Icons::svg('briefcase') ?></div><div><div class="stat-label">Active</div><div class="stat-value"><?= (int) ($counts['ACTIVE'] ?? 0) ?></div></div></div>
    <div class="card glass stat"><div class="stat-icon"><?= Icons::svg('user') ?></div><div><div class="stat-label">Prospects</div><div class="stat-value"><?= (int) ($counts['PROSPECT'] ?? 0) ?></div></div></div>
    <div class="card glass stat"><div class="stat-icon orange"><?= Icons::svg('refresh') ?></div><div><div class="stat-label">Renewals in 30 days</div><div class="stat-value"><?= (int) $renewals ?></div></div></div>
    <div class="card glass stat"><div class="stat-icon violet"><?= Icons::svg('archive') ?: Icons::svg('box') ?></div><div><div class="stat-label">Inactive / former</div><div class="stat-value"><?= (int) ($counts['INACTIVE'] ?? 0) + (int) ($counts['FORMER'] ?? 0) ?></div></div></div>
</div>
<div class="card">
    <form class="toolbar" method="get">
        <input class="input search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search customers">
        <select class="select" name="status" data-autosubmit><option value="">All statuses</option><?php foreach (CustomerController::STATUSES as $k => $v): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
    </form>
    <?php if (!$rows): ?><div class="empty"><?= Icons::svg('briefcase', 'icon', 32) ?><p>No customers yet.</p></div><?php else: ?>
    <?php
    $bulk = [];
    if (can('customers.edit')) {
        $bulk['Set status'] = array_combine(array_map(static fn ($k) => 'status:' . $k, array_keys(CustomerController::STATUSES)), CustomerController::STATUSES);
    }
    if (can('customers.delete')) {
        $bulk['delete'] = 'Move to recycle bin';
    }
    ?>
    <?= Form::bulkBar('bulk-customers', '/admin/customers/bulk', $bulk, 'customers') ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><?php if ($bulk): ?><th class="check"><?= Form::bulkAll('bulk-customers') ?></th><?php endif; ?><th>Customer</th><th>Status</th><th class="hide-sm">Subscriptions</th><th class="hide-sm">Next renewal</th><th class="hide-sm">Owner</th><th>Since</th></tr></thead>
        <tbody><?php foreach ($rows as $c): ?>
            <tr><?php if ($bulk): ?><td class="check"><?= Form::bulkCheck('bulk-customers', (int) $c['id'], $c['company'] ?: $c['name']) ?></td><?php endif; ?><td><a class="row-title" href="/admin/customers/<?= (int) $c['id'] ?>"><?= e($c['company'] ?: $c['name']) ?></a><div class="small muted"><?= e($c['company'] ? $c['name'] . ' · ' : '') ?><?= e($c['email']) ?></div></td>
                <td><?= Form::badge($c['status']) ?></td><td class="hide-sm"><?= (int) $c['products'] ?></td>
                <td class="hide-sm small"><?= e($c['next_renewal'] ? format_date($c['next_renewal']) : '—') ?></td>
                <td class="hide-sm small"><?= e($c['owner'] ?? '—') ?></td><td class="small muted"><?= e(format_date($c['created_at'])) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= Form::pagination($pageNo, $pages, $total, 'customers') ?>
    <?php endif; ?>
</div>
