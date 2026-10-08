<?php
use App\Cms\Icons;
use App\Http\Form;
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Staff', 'sub' => 'Two-factor authentication is required for every account.', 'actions' => '<a class="btn btn-primary" href="/admin/staff/new">' . Icons::svg('user-plus', 'icon', 16) . ' Add staff</a> <a class="btn btn-secondary" href="/admin/staff/roles">Roles & permissions</a>']) ?>
<div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Name</th><th>Role</th><th>Two-factor</th><th class="hide-sm">Open leads</th><th class="hide-sm">Last sign-in</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($rows as $u): ?>
        <tr><td><a class="row-title" href="/admin/staff/<?= (int) $u['id'] ?>"><?= e($u['name']) ?></a><?= (int) $u['id'] === (int) $me['id'] ? ' <span class="badge blue">You</span>' : '' ?><div class="small muted"><?= e($u['email']) ?></div></td>
            <td><?= e($u['role_name']) ?></td>
            <td><?= (int) $u['two_factor_enabled'] ? Form::badge('ACTIVE', 'On') : Form::badge('DRAFT', 'Not set up') ?></td>
            <td class="hide-sm"><?= (int) $u['open_leads'] ?></td>
            <td class="hide-sm small muted"><?= e($u['last_login_at'] ? time_ago($u['last_login_at']) : 'Never') ?></td>
            <td><?= Form::badge($u['status']) ?></td></tr>
    <?php endforeach; ?></tbody>
</table></div></div>
