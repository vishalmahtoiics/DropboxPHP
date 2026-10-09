<?php
use App\Http\Form;

$e = $edit ?? [];
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Redirects', 'crumbs' => [['SEO', '/admin/seo'], ['Redirects', null]], 'sub' => 'Followed only when an address would otherwise be "not found". Chains are collapsed and loops refused.', 'actions' => '<a class="btn btn-secondary" href="?export=csv">Export CSV</a>']) ?>
<div class="builder">
    <div>
        <div class="card">
            <form class="toolbar" method="get"><input class="input search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search paths"></form>
            <?php if (!$rows): ?><div class="empty"><p>No redirects yet.</p></div><?php else: ?>
            <?= Form::bulkBar('bulk-redirects', '/admin/redirects/bulk', ['activate' => 'Switch on', 'deactivate' => 'Switch off', 'delete' => 'Delete'], 'redirects') ?>
            <div class="table-wrap"><table class="table"><thead><tr><th class="check"><?= Form::bulkAll('bulk-redirects') ?></th><th>From</th><th>To</th><th>Type</th><th>Hits</th><th></th></tr></thead><tbody>
                <?php foreach ($rows as $r): ?><tr<?= (int) $r['is_active'] ? '' : ' style="opacity:.55"' ?>><td class="check"><?= Form::bulkCheck('bulk-redirects', (int) $r['id'], $r['source']) ?></td><td><code><?= e($r['source']) ?></code><?php if ($r['origin'] === 'AUTOMATIC'): ?> <span class="badge">auto</span><?php endif; ?></td><td class="small" style="word-break:break-all"><?= e($r['destination']) ?></td><td><?= $r['type'] === 'PERMANENT' ? '301' : '302' ?></td><td><?= (int) $r['hit_count'] ?></td>
                    <td class="actions"><a class="btn btn-ghost btn-sm" href="?edit=<?= (int) $r['id'] ?>">Edit</a><?= Form::action('/admin/redirects/' . $r['id'] . '/delete', 'Delete', 'btn btn-danger-ghost btn-sm', 'Delete this redirect?') ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </div>
        <?php if ($notFound): ?>
        <div class="card"><div class="card-head"><h2>Recent 404s</h2></div><div class="table-wrap"><table class="table"><tbody>
            <?php foreach ($notFound as $n): ?><tr><td><code><?= e($n['path']) ?></code></td><td><?= (int) $n['hits'] ?> hits</td><td class="actions"><a class="btn btn-secondary btn-sm" href="?from=<?= e(rawurlencode($n['path'])) ?>">Redirect</a>
                <form class="inline-form" method="post" action="/admin/redirects"><?= csrf_field() ?><input type="hidden" name="action" value="ignore"><input type="hidden" name="path" value="<?= e($n['path']) ?>"><button class="btn btn-ghost btn-sm" type="submit">Ignore</button></form></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
        <?php endif; ?>
    </div>
    <div>
        <form class="card" method="post" action="/admin/redirects">
            <div class="card-head"><h2><?= $e ? 'Edit redirect' : 'Add redirect' ?></h2></div>
            <div class="card-body stack">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($e['id'] ?? '') ?>">
                <?= Form::input('source', 'From path', $e['source'] ?? $prefill, ['required' => true, 'placeholder' => '/old-page']) ?>
                <?= Form::input('destination', 'To', $e['destination'] ?? '', ['required' => true, 'placeholder' => '/new-page or https://…']) ?>
                <?= Form::select('type', 'Type', ['PERMANENT' => 'Permanent (301)', 'TEMPORARY' => 'Temporary (302)'], $e['type'] ?? 'PERMANENT') ?>
                <?= Form::input('note', 'Note', $e['note'] ?? '') ?>
                <?= Form::toggle('is_active', 'Active', $e['is_active'] ?? 1) ?>
            </div>
            <div class="card-foot form-actions"><?php if ($e): ?><a class="btn btn-ghost" href="/admin/redirects">Cancel</a><?php endif; ?><button class="btn btn-primary" type="submit">Save</button></div>
        </form>
        <form class="card" method="post" action="/admin/redirects" enctype="multipart/form-data">
            <div class="card-head"><h2>Import CSV</h2></div>
            <div class="card-body stack"><?= csrf_field() ?><input type="hidden" name="action" value="import">
                <input class="input" type="file" name="csv" accept=".csv,text/csv" required>
                <p class="help">Columns: source, destination, type (PERMANENT or TEMPORARY). Each row is checked for loops.</p></div>
            <div class="card-foot form-actions"><button class="btn btn-secondary" type="submit">Import</button></div>
        </form>
    </div>
</div>
