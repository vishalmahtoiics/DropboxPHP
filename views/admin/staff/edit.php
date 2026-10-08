<?php
use App\Http\Form;

$v = $u ?? [];
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => $u ? $u['name'] : 'New staff member', 'crumbs' => [['Staff', '/admin/staff'], [$u ? 'Edit' : 'New', null]], 'sub' => $u ? $u['email'] . ' · ' . $u['role_name'] : '']) ?>
<?php if ($u && !$canManage && !$isSelf): ?><div class="alert alert-warning"><span>This account's role is at or above yours, so you can view it but not change it.</span></div><?php endif; ?>
<form class="card" method="post" action="<?= $u ? '/admin/staff/' . (int) $u['id'] : '/admin/staff' ?>"><div class="card-body stack">
    <?= csrf_field() ?>
    <div class="grid grid-2">
        <?= Form::input('name', 'Name', $v['name'] ?? '', ['required' => true]) ?>
        <?= Form::input('email', 'Email', $v['email'] ?? '', ['required' => true, 'type' => 'email']) ?>
        <?= Form::input('job_title', 'Job title', $v['job_title'] ?? '') ?>
        <?= Form::input('phone', 'Phone', $v['phone'] ?? '') ?>
        <?php if (!$isSelf): ?>
            <?= Form::select('role_id', 'Role', $roles + ($u && !isset($roles[$u['role_id']]) ? [$u['role_id'] => $u['role_name']] : []), (string) ($v['role_id'] ?? ''), ['required' => true, 'empty' => 'Choose a role', 'help' => 'Only roles below your own are offered.']) ?>
            <?php if ($u): ?><?= Form::select('status', 'Status', ['ACTIVE' => 'Active', 'SUSPENDED' => 'Suspended'], $v['status']) ?><?php endif; ?>
        <?php endif; ?>
        <?= Form::input('password', $u ? 'New password' : 'Password', '', ['type' => 'password', 'required' => !$u, 'autocomplete' => 'new-password', 'help' => 'At least 10 characters with upper- and lower-case letters and a number.' . ($u ? ' Leave blank to keep the current one.' : '')]) ?>
    </div>
    <?php if (!$isSelf): ?>
        <hr><h3>Markets</h3>
        <?= Form::toggle('all_countries', 'Can work in every market', $v['all_countries'] ?? 1, ['data' => ['field-name' => 'all_countries']]) ?>
        <div class="row" data-show-when='{"all_countries":[""]}'>
            <?php foreach ($markets as $m): ?><label class="check"><input type="checkbox" name="countries[]" value="<?= (int) $m['id'] ?>"<?= in_array((int) $m['id'], $userMarkets, true) ? ' checked' : '' ?>> <?= e($m['name']) ?></label><?php endforeach; ?>
        </div>
        <p class="help">Market access narrows what the role allows; it never widens it.</p>
    <?php endif; ?>
</div>
<?php if ($canManage || $isSelf): ?><div class="card-foot form-actions"><a class="btn btn-ghost" href="/admin/staff">Cancel</a><button class="btn btn-primary" type="submit"><?= $u ? 'Save' : 'Create account' ?></button></div><?php endif; ?>
</form>
<?php if ($u && ($canManage || $isSelf)): ?>
<div class="card"><div class="card-head"><h2>Security</h2></div><div class="card-body">
    <p>Two-factor: <?= (int) $u['two_factor_enabled'] ? '<strong>on</strong> since ' . e(format_date($u['two_factor_verified_at'])) : '<strong>not set up yet</strong>' ?>. Active sessions: <?= count($sessions) ?>.</p>
    <div class="row">
        <?php if (can('user.mfa.reset')): ?><?= Form::action('/admin/staff/' . $u['id'] . '/reset-mfa', 'Reset authenticator', 'btn btn-secondary', 'Remove this account\'s authenticator? They will enrol a new one at next sign-in.') ?><?php endif; ?>
        <?php if (!$isSelf): ?><?= Form::action('/admin/staff/' . $u['id'] . '/revoke-sessions', 'Sign out everywhere', 'btn btn-secondary') ?>
        <?= Form::action('/admin/staff/' . $u['id'] . '/delete', 'Delete account', 'btn btn-danger-ghost', 'Delete this account? Their leads become unassigned.') ?><?php endif; ?>
    </div>
</div></div>
<?php endif; ?>
