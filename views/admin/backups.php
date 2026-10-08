<?php
use App\Cms\Icons;
use App\Http\Form;

$size = static fn ($b) => $b === null ? '—' : ($b > 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB');
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Backup & restore', 'sub' => 'Database and media in one archive, stored outside the web root at ' . config('app.backup_dir') . '.']) ?>
<?php if (!$zip): ?><div class="alert alert-error"><span>The PHP zip extension is not installed. Ask your host to enable it.</span></div><?php endif; ?>
<?php if (!$dirOk): ?><div class="alert alert-error"><span>The backup directory is not writable. Set BACKUP_DIR to a writable folder outside public/.</span></div><?php endif; ?>
<div class="grid grid-3">
    <?php if (can('backup.create')): ?>
    <form class="card" method="post" action="/admin/backups"><div class="card-head"><h2>Back up now</h2></div><div class="card-body stack">
        <?= csrf_field() ?>
        <?= Form::select('type', 'What', ['FULL' => 'Everything (database + media)', 'DATABASE' => 'Database only', 'MEDIA' => 'Media only'], 'FULL') ?>
        <?= Form::input('note', 'Note', '', ['placeholder' => 'Before the redesign…']) ?>
    </div><div class="card-foot form-actions"><button class="btn btn-primary" type="submit"><?= Icons::svg('database', 'icon', 16) ?> Create backup</button></div></form>
    <?php endif; ?>
    <?php if (can('backup.settings')): ?>
    <form class="card" method="post" action="/admin/backups/settings"><div class="card-head"><h2>Schedule</h2></div><div class="card-body stack">
        <?= csrf_field() ?>
        <?= Form::toggle('enabled', 'Automatic backups', $s['enabled']) ?>
        <div class="grid grid-2">
            <?= Form::select('frequency', 'How often', ['daily' => 'Daily', 'weekly' => 'Weekly'], $s['frequency']) ?>
            <?= Form::input('hour', 'After (hour, 0–23)', $s['hour'], ['type' => 'number', 'min' => 0, 'max' => 23]) ?>
            <?= Form::input('retention', 'Keep the last', $s['retention'], ['type' => 'number', 'min' => 1, 'max' => 365]) ?>
        </div>
        <?= Form::toggle('include_media', 'Include media', $s['include_media']) ?>
        <p class="help">Needs the cron job: <code>*/15 * * * * php <?= e(BASE_PATH) ?>/bin/console cron</code><?= $cronUrl ? ' — or call ' . e($cronUrl) . ' every 15 minutes.' : '' ?></p>
    </div><div class="card-foot form-actions"><button class="btn btn-secondary" type="submit">Save schedule</button></div></form>
    <?php endif; ?>
    <?php if (can('backup.restore')): ?>
    <form class="card" method="post" action="/admin/backups" enctype="multipart/form-data"><div class="card-head"><h2>Import a backup</h2></div><div class="card-body stack">
        <?= csrf_field() ?><input type="hidden" name="action" value="import">
        <input class="input" type="file" name="backup" accept=".zip,application/zip" required>
        <p class="help">A ZIP downloaded from this screen (for example from another server). It is checked before it is listed.</p>
    </div><div class="card-foot form-actions"><button class="btn btn-secondary" type="submit">Upload</button></div></form>
    <?php endif; ?>
</div>
<div class="card mt-2"><div class="card-head"><h2>Backups</h2></div>
    <?php if (!$rows): ?><div class="empty"><?= Icons::svg('database', 'icon', 32) ?><p>No backups yet.</p></div><?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Created</th><th>Type</th><th>Size</th><th>Contents</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($rows as $b): ?>
            <tr><td class="small"><?= e(format_datetime($b['created_at'])) ?><div class="muted"><?= e(strtolower($b['origin'])) ?><?= $b['creator'] ? ' · ' . e($b['creator']) : '' ?><?= $b['note'] ? ' · ' . e($b['note']) : '' ?></div></td>
                <td><?= e(ucfirst(strtolower($b['type']))) ?></td><td><?= e($size($b['size'])) ?></td>
                <td class="small"><?= (int) $b['tables_count'] ?> tables, <?= number_format((int) $b['rows_count']) ?> rows, <?= (int) $b['media_count'] ?> files</td>
                <td><?= Form::badge($b['status']) ?><?php if ($b['error']): ?><div class="small error-text"><?= e(str_limit($b['error'], 120)) ?></div><?php endif; ?></td>
                <td class="actions">
                    <?php if ($b['status'] === 'COMPLETED' && can('backup.download')): ?><a class="btn btn-ghost btn-sm" href="/admin/backups/<?= (int) $b['id'] ?>/download">Download</a><?php endif; ?>
                    <?php if ($b['status'] === 'COMPLETED' && can('backup.restore')): ?>
                        <form class="inline-form" method="post" action="/admin/backups/<?= (int) $b['id'] ?>/restore" data-confirm="Restore the whole site from this backup? A safety backup is taken first. Visitors see a maintenance page while it runs."><?= csrf_field() ?><input class="input input-sm" name="confirm" placeholder="Type RESTORE" style="width:110px" required pattern="RESTORE"><button class="btn btn-secondary btn-sm" type="submit">Restore</button></form>
                    <?php endif; ?>
                    <?php if (can('backup.delete')): ?><?= Form::action('/admin/backups/' . $b['id'] . '/delete', 'Delete', 'btn btn-danger-ghost btn-sm', 'Delete this backup permanently?') ?><?php endif; ?>
                </td></tr>
        <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</div>
<?php if ($restores): ?>
<div class="card"><div class="card-head"><h2>Restore history</h2></div><div class="table-wrap"><table class="table"><tbody>
    <?php foreach ($restores as $r): ?><tr><td class="small"><?= e(format_datetime($r['started_at'])) ?></td><td>From #<?= (int) $r['backup_id'] ?> (safety #<?= (int) $r['safety_backup_id'] ?>)</td><td><?= e($r['who'] ?? '') ?></td><td><?= Form::badge($r['status']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
