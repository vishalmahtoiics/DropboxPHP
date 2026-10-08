<?php
use App\Cms\Icons;
use App\Http\Form;
use App\Services\Leads;
use App\Services\Money;

$qs = $_GET;
unset($qs['page']);
$exportQs = http_build_query($qs);
$actions = (can('leads.create') ? '<a class="btn btn-primary" href="/admin/leads/new">' . Icons::svg('user-plus', 'icon', 16) . ' Add lead</a> ' : '')
    . (can('leads.export') ? '<a class="btn btn-secondary" href="/admin/leads/export?' . e($exportQs) . '">' . Icons::svg('download', 'icon', 16) . ' Export CSV</a> ' : '')
    . '<a class="btn btn-ghost" href="/admin/pipeline">' . Icons::svg('kanban', 'icon', 16) . ' Pipeline</a>';
$anyFilter = array_filter($f) !== [];
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Leads', 'sub' => number_format($total) . ' lead' . ($total === 1 ? '' : 's') . ' in ' . $adminCountry['name'] . ($anyFilter ? ' matching your filters' : ''), 'actions' => $actions]) ?>
<div class="row mb-2" style="gap:.4rem">
    <a class="btn btn-sm <?= $f['status'] === '' ? 'btn-primary' : 'btn-secondary' ?>" href="/admin/leads">All open</a>
    <?php foreach (Leads::STATUSES as $k => $label): ?>
        <a class="btn btn-sm <?= $f['status'] === $k ? 'btn-primary' : 'btn-secondary' ?>" href="?status=<?= $k ?>"><?= e($label) ?> <span class="badge"><?= (int) ($statusCounts[$k] ?? 0) ?></span></a>
    <?php endforeach; ?>
</div>
<div class="card">
    <form class="toolbar" method="get">
        <input type="hidden" name="status" value="<?= e($f['status']) ?>">
        <input class="input search" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Name, email, company, phone or #id">
        <select class="select" name="assignee" data-autosubmit><option value="">Any owner</option><option value="none"<?= $f['assignee'] === 'none' ? ' selected' : '' ?>>Unassigned</option><option value="<?= (int) $me['id'] ?>"<?= $f['assignee'] === (string) $me['id'] ? ' selected' : '' ?>>Mine</option><?php foreach ($staff as $k => $v): ?><option value="<?= (int) $k ?>"<?= $f['assignee'] === (string) $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
        <select class="select" name="priority" data-autosubmit><option value="">Any priority</option><?php foreach (Leads::PRIORITIES as $k => $v): ?><option value="<?= $k ?>"<?= $f['priority'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
        <select class="select" name="product" data-autosubmit><option value="">Any product</option><?php foreach ($products as $k => $v): ?><option value="<?= (int) $k ?>"<?= $f['product'] === (string) $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
        <select class="select" name="source" data-autosubmit><option value="">Any source</option><?php foreach ($sources as $s): ?><option<?= $f['source'] === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
        <?php if ($campaigns): ?><select class="select" name="campaign" data-autosubmit><option value="">Any campaign</option><?php foreach ($campaigns as $s): ?><option<?= $f['campaign'] === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select><?php endif; ?>
        <input class="input" type="date" name="from" value="<?= e($f['from']) ?>" aria-label="From date">
        <input class="input" type="date" name="to" value="<?= e($f['to']) ?>" aria-label="To date">
        <button class="btn btn-secondary" type="submit">Filter</button>
        <?php if ($anyFilter): ?><a class="btn btn-ghost" href="/admin/leads">Reset</a><?php endif; ?>
    </form>
    <?php if (!$rows): ?>
        <div class="empty"><?= Icons::svg('inbox', 'icon', 32) ?><p>No leads match.</p></div>
    <?php else: ?>
    <form method="post" action="/admin/leads/bulk" data-confirm="Apply this action to the selected leads?">
        <?= csrf_field() ?>
        <div class="toolbar">
            <span class="small muted">With selected:</span>
            <select class="select input-sm" name="action" required>
                <option value="">Choose an action</option>
                <?php if (can('leads.edit')): ?><optgroup label="Set status"><?php foreach (Leads::STATUSES as $k => $v): ?><option value="status:<?= $k ?>"><?= $v ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                <?php if (can('leads.assign')): ?><optgroup label="Assign to"><option value="assign:none">Nobody</option><?php foreach ($staff as $k => $v): ?><option value="assign:<?= (int) $k ?>"><?= e($v) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                <?php if (can('leads.delete')): ?><option value="delete">Move to recycle bin</option><?php endif; ?>
            </select>
            <button class="btn btn-secondary btn-sm" type="submit">Apply</button>
        </div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th style="width:32px"><input type="checkbox" data-check-all="ids[]" aria-label="Select all"></th><?= Form::sortTh('name', 'Lead', $sort, $dir) ?><?= Form::sortTh('status', 'Status', $sort, $dir) ?><th class="hide-sm">Product</th><th class="hide-sm">Source</th><th class="hide-sm">Owner</th><?= Form::sortTh('value', 'Value', $sort, $dir) ?><?= Form::sortTh('created_at', 'Received', $sort, $dir) ?></tr></thead>
            <tbody>
            <?php foreach ($rows as $l): ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= (int) $l['id'] ?>" aria-label="Select <?= e($l['name']) ?>"></td>
                    <td><a class="row-title" href="/admin/leads/<?= (int) $l['id'] ?>"><?= e($l['name']) ?></a><?php if ($l['priority'] === 'HIGH'): ?> <span class="badge HIGH">High</span><?php endif; ?>
                        <div class="small muted"><?= e($l['email']) ?><?= $l['company'] ? ' · ' . e($l['company']) : '' ?></div></td>
                    <td><?= Form::badge($l['status']) ?><?php if ($l['follow_up_at']): ?><div class="small <?= strtotime($l['follow_up_at']) < time() && !in_array($l['status'], ['WON', 'LOST', 'SPAM'], true) ? 'error-text' : 'muted' ?>">↻ <?= e(format_date($l['follow_up_at'])) ?></div><?php endif; ?></td>
                    <td class="hide-sm small"><?= e($l['product_name'] ?? '—') ?></td>
                    <td class="hide-sm small"><?= e($l['utm_source'] ?: ($l['source'] ?? '—')) ?><?php if ($l['utm_campaign']): ?><div class="muted"><?= e($l['utm_campaign']) ?></div><?php endif; ?></td>
                    <td class="hide-sm small"><?= e($l['assignee_name'] ?? 'Unassigned') ?></td>
                    <td class="small"><?= $l['value'] !== null ? e(Money::format($l['value'], $adminCountry['currency_symbol'], $adminCountry['currency'])) : '—' ?></td>
                    <td class="small muted nowrap" title="<?= e($l['created_at']) ?>"><?= e(time_ago($l['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </form>
    <?= Form::pagination($pageNo, $pages, $total, 'leads') ?>
    <?php endif; ?>
</div>
