<?php use App\Http\Form; ?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Profile & security', 'sub' => $me['email'] . ' · ' . $me['role_name']]) ?>
<div data-tabs="profile">
    <div class="tabs" role="tablist"><button class="tab" type="button" role="tab" data-tab="personal">Profile</button><button class="tab" type="button" role="tab" data-tab="security">Password & 2FA</button><button class="tab" type="button" role="tab" data-tab="sessions">Sessions</button><button class="tab" type="button" role="tab" data-tab="activity">Activity</button></div>
    <div class="tab-panel" id="personal">
        <form class="card" method="post" action="/admin/profile"><div class="card-body">
            <?= csrf_field() ?>
            <div class="grid grid-2">
                <?= Form::input('name', 'Name', $u['name'], ['required' => true]) ?>
                <?= Form::input('job_title', 'Job title', $u['job_title']) ?>
                <?= Form::input('department', 'Department', $u['department']) ?>
                <?= Form::input('phone', 'Phone', $u['phone']) ?>
                <?= Form::input('timezone', 'Timezone', $u['timezone'], ['placeholder' => 'Asia/Kolkata']) ?>
                <?= Form::input('linkedin_url', 'LinkedIn', $u['linkedin_url']) ?>
            </div>
            <?= Form::media('avatar_url', 'Photo (shown as the author on articles)', $u['avatar_url'], ['kind' => 'IMAGE']) ?>
            <?= Form::textarea('bio', 'Short bio (author box)', $u['bio'], ['rows' => 3]) ?>
        </div><div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save</button></div></form>
    </div>
    <div class="tab-panel" id="security" hidden>
        <form class="card" method="post" action="/admin/profile/password"><div class="card-head"><h2>Change password</h2></div><div class="card-body stack">
            <?= csrf_field() ?>
            <?= Form::input('current_password', 'Current password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
            <?= Form::input('password', 'New password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
            <?= Form::input('password_confirmation', 'Confirm new password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
            <p class="help">Changing your password signs out your other sessions.</p>
        </div><div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Change password</button></div></form>
        <div class="card"><div class="card-head"><h2>Two-factor authentication</h2><?= Form::badge('ACTIVE', 'On') ?></div><div class="card-body">
            <p>Enabled <?= e(format_date($u['two_factor_verified_at'])) ?>. You have <strong><?= (int) $codesLeft ?></strong> unused recovery codes.</p>
            <?= Form::action('/admin/profile/recovery-codes', 'Generate new recovery codes', 'btn btn-secondary', 'Replace your recovery codes? The old ones stop working.') ?>
            <p class="help mt-1">Lost your phone? Sign in with a recovery code, or ask a Super Admin to reset your authenticator.</p>
        </div></div>
    </div>
    <div class="tab-panel" id="sessions" hidden>
        <div class="card"><div class="card-head"><h2>Signed-in sessions</h2><?= Form::action('/admin/profile/sessions/revoke', 'Sign out all other sessions', 'btn btn-secondary btn-sm', 'Sign out everywhere else?') ?></div>
            <div class="table-wrap"><table class="table"><thead><tr><th>Device</th><th>Signed in</th><th>Last active</th><th></th></tr></thead><tbody>
                <?php foreach ($sessions as $s): ?><tr><td class="small"><?= e(str_limit((string) $s['user_agent'], 90)) ?><?= $s['id'] === $me['session_id'] ? ' <span class="badge blue">This device</span>' : '' ?></td><td class="small"><?= e(format_datetime($s['created_at'])) ?></td><td class="small"><?= e(time_ago($s['last_seen_at'])) ?></td>
                    <td class="actions"><?php if ($s['id'] !== $me['session_id']): ?><form class="inline-form" method="post" action="/admin/profile/sessions/revoke"><?= csrf_field() ?><input type="hidden" name="session" value="<?= e($s['id']) ?>"><button class="btn btn-ghost btn-sm" type="submit">Sign out</button></form><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div>
    </div>
    <div class="tab-panel" id="activity" hidden>
        <div class="card"><div class="table-wrap"><table class="table"><tbody>
            <?php foreach ($activity as $a): ?><tr><td class="small nowrap muted"><?= e(format_datetime($a['created_at'])) ?></td><td><strong><?= e($a['action']) ?></strong> <?= e($a['entity']) ?><?= $a['entity_id'] ? ' #' . e($a['entity_id']) : '' ?></td><td class="small"><?= e(str_limit((string) $a['summary'], 100)) ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
    </div>
</div>
