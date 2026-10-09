<?php
use App\Cms\Icons;
use App\Http\Form;
use App\Http\Controllers\Admin\PopupController;
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Popups', 'sub' => 'Timed, scroll, exit-intent or button-triggered popups. Any button linking to #popup-ID opens one.', 'actions' => '<a class="btn btn-primary" href="/admin/popups/new">' . Icons::svg('plus', 'icon', 16) . ' New popup</a>']) ?>
<div class="card">
    <?php if (!$rows): ?><div class="empty"><?= Icons::svg('layers', 'icon', 32) ?><p>No popups yet.</p></div><?php else: ?>
    <?= Form::bulkBar('bulk-popups', '/admin/popups/bulk', ['activate' => 'Switch on', 'deactivate' => 'Switch off', 'delete' => 'Move to recycle bin'], 'popups') ?>
    <div class="table-wrap"><table class="table"><thead><tr><th class="check"><?= Form::bulkAll('bulk-popups') ?></th><th>Popup</th><th>Trigger</th><th>Pages</th><th>Link</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($rows as $p): $pat = json_decode((string) $p['url_patterns'], true) ?: []; ?>
            <tr><td class="check"><?= Form::bulkCheck('bulk-popups', (int) $p['id'], $p['name']) ?></td><td><a class="row-title" href="/admin/popups/<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a><div class="small muted"><?= e(PopupController::TYPES[$p['type']]) ?><?= $p['form_name'] ? ' · ' . e($p['form_name']) : '' ?><?= $p['country_name'] ? ' · ' . e($p['country_name']) : '' ?></div></td>
                <td class="small"><?= e(PopupController::TRIGGERS[$p['trigger']]) ?><?= $p['trigger'] === 'DELAY' ? ' (' . (int) $p['delay_seconds'] . 's)' : '' ?><?= $p['device'] !== 'ALL' ? ' · ' . strtolower($p['device']) : '' ?></td>
                <td class="small"><?= $pat ? e(implode(', ', $pat)) : 'All pages' ?></td>
                <td><code>#popup-<?= (int) $p['id'] ?></code></td>
                <td><?= $p['is_active'] ? Form::badge('ACTIVE', 'Live') : Form::badge('INACTIVE', 'Off') ?></td>
                <td class="actions"><?= Form::action('/admin/popups/' . $p['id'] . '/delete', 'Delete', 'btn btn-danger-ghost btn-sm', 'Delete this popup?') ?></td></tr>
        <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</div>
