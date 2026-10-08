<?php use App\Http\Form; ?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Audit log', 'sub' => 'Every change, who made it and when. Secrets are redacted. Append-only.']) ?>
<div class="card">
    <form class="toolbar" method="get">
        <input class="input search" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search summary, id or email">
        <select class="select" name="actor" data-autosubmit><option value="">Anyone</option><?php foreach ($staff as $k => $v): ?><option value="<?= (int) $k ?>"<?= (int) ($_GET['actor'] ?? 0) === (int) $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
        <select class="select" name="entity" data-autosubmit><option value="">Any area</option><?php foreach ($entities as $v): ?><option<?= ($_GET['entity'] ?? '') === $v ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
        <select class="select" name="action" data-autosubmit><option value="">Any action</option><?php foreach ($actions as $v): ?><option<?= ($_GET['action'] ?? '') === $v ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
        <input class="input" type="date" name="from" value="<?= e($_GET['from'] ?? '') ?>"><input class="input" type="date" name="to" value="<?= e($_GET['to'] ?? '') ?>">
        <button class="btn btn-secondary" type="submit">Filter</button>
    </form>
    <div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>Who</th><th>What</th><th>Summary</th><th></th></tr></thead><tbody>
        <?php foreach ($rows as $a): ?><tr><td class="small nowrap"><?= e(format_datetime($a['created_at'])) ?></td><td class="small"><?= e($a['actor_email'] ?? 'System / visitor') ?></td><td><span class="badge"><?= e($a['action']) ?></span> <?= e($a['entity']) ?><?= $a['entity_id'] ? ' #' . e($a['entity_id']) : '' ?></td><td class="small"><?= e(str_limit((string) $a['summary'], 120)) ?></td>
            <td class="actions"><?php if ($a['before_data'] || $a['after_data']): ?><a class="btn btn-ghost btn-sm" href="?<?= e(http_build_query(array_merge($_GET, ['show' => $a['id']]))) ?>">Details</a><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?= Form::pagination($pageNo, $pages, $total, 'entries') ?>
</div>
<?php if ($detail): ?>
<div class="card"><div class="card-head"><h2>Entry #<?= (int) $detail['id'] ?></h2></div><div class="card-body grid grid-2">
    <div><h3>Before</h3><pre class="code-block"><?= e(json_encode(json_decode((string) $detail['before_data'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></div>
    <div><h3>After</h3><pre class="code-block"><?= e(json_encode(json_decode((string) $detail['after_data'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></div>
</div></div>
<?php endif; ?>
