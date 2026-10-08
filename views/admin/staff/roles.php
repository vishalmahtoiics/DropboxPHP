<?php
use App\Cms\Icons;
use App\Core\Permissions;
use App\Http\Form;

$e = $edit ?? [];
$groups = [];
foreach (Permissions::ALL as $key => [$group, $label]) {
    $groups[$group][$key] = $label;
}
$locked = $e && ((int) $e['rank'] <= (int) $me['role_rank'] || $e['slug'] === 'super-admin');
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Roles & permissions', 'crumbs' => [['Staff', '/admin/staff'], ['Roles', null]], 'sub' => 'Lower rank = more privileged. You can manage roles ranked below yours (' . (int) $me['role_rank'] . ').', 'actions' => '<a class="btn btn-primary" href="?new=1">' . Icons::svg('plus', 'icon', 16) . ' New role</a>']) ?>
<div class="builder" style="grid-template-columns:320px minmax(0,1fr)">
    <div class="card"><div class="card-body">
        <?php foreach ($roles as $r): ?>
            <a class="nav-link<?= (int) ($e['id'] ?? 0) === (int) $r['id'] ? ' active' : '' ?>" href="?edit=<?= (int) $r['id'] ?>"><?= Icons::svg('shield', 'icon', 16) ?> <span class="grow"><?= e($r['name']) ?></span> <span class="badge"><?= (int) $r['users'] ?></span></a>
        <?php endforeach; ?>
    </div></div>
    <?php if ($e || $new): ?>
    <form class="card" method="post" action="/admin/staff/roles">
        <div class="card-head"><h2><?= $e ? e($e['name']) : 'New role' ?></h2><?php if ($e && (int) $e['is_system']): ?><span class="badge">Built-in</span><?php endif; ?></div>
        <div class="card-body">
            <?php if ($locked): ?><div class="alert alert-info"><span>This role is at or above your rank and is read-only for you.</span></div><?php endif; ?>
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($e['id'] ?? '') ?>">
            <div class="grid grid-3">
                <?= Form::input('name', 'Name', $e['name'] ?? '', ['required' => true]) ?>
                <?= Form::input('rank', 'Rank', $e['rank'] ?? 50, ['type' => 'number', 'min' => (int) $me['role_rank'] + 1]) ?>
                <?= Form::input('description', 'Description', $e['description'] ?? '') ?>
            </div>
            <?php foreach ($groups as $group => $perms): ?>
                <fieldset><legend><?= e(Permissions::GROUP_LABELS[$group] ?? $group) ?></legend>
                    <div class="grid grid-3"><?php foreach ($perms as $key => $label): $mine = in_array($key, $me['permissions'], true); ?>
                        <label class="check"<?= $mine ? '' : ' title="You do not hold this permission"' ?>><input type="checkbox" name="permissions[]" value="<?= e($key) ?>"<?= in_array($key, $held, true) ? ' checked' : '' ?><?= $mine && !$locked ? '' : ' disabled' ?>> <?= e($label) ?></label>
                    <?php endforeach; ?></div>
                </fieldset>
            <?php endforeach; ?>
        </div>
        <?php if (!$locked): ?><div class="card-foot form-actions"><?php if ($e && !(int) $e['is_system']): ?><button class="btn btn-danger-ghost" type="submit" formaction="/admin/staff/roles/<?= (int) $e['id'] ?>/delete" formnovalidate>Delete role</button><?php endif; ?><button class="btn btn-primary" type="submit">Save role</button></div><?php endif; ?>
    </form>
    <?php else: ?><div class="card"><div class="empty"><p>Select a role to see its permissions.</p></div></div><?php endif; ?>
</div>
