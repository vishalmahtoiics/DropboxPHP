<?php
/** @var array $report */
$labels = ['created' => ['green', 'New draft'], 'draft' => ['orange', 'Builder draft'], 'skipped' => ['gray', 'Skipped']];
$counts = array_count_values(array_column($report['pages'], 'action'));
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Site imported', 'sub' => sprintf('%d new pages, %d existing pages with a new draft, %d skipped · %d images · %d redirects.',
    $counts['created'] ?? 0, $counts['draft'] ?? 0, $counts['skipped'] ?? 0, $report['media'], $report['redirects'])]) ?>
<?php if ($report['warnings']): ?>
    <div class="card mb-2"><div class="card-head"><h2>Notes</h2></div><div class="card-body"><ul class="small">
        <?php foreach (array_slice($report['warnings'], 0, 100) as $w): ?><li><?= e($w) ?></li><?php endforeach; ?>
        <?php if (count($report['warnings']) > 100): ?><li>… and <?= count($report['warnings']) - 100 ?> more.</li><?php endif; ?>
    </ul></div></div>
<?php endif; ?>
<div class="card mb-2">
    <div class="card-head"><h2>Pages</h2></div>
    <table class="table">
        <thead><tr><th>Page</th><th>Address</th><th>Result</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($report['pages'] as $p): [$tone, $label] = $labels[$p['action']]; ?>
            <tr>
                <td><strong><?= e($p['title']) ?></strong><?php if ($p['note']): ?><br><span class="small muted"><?= e($p['note']) ?></span><?php endif; ?></td>
                <td class="small">/<?= e($p['slug']) ?></td>
                <td><span class="badge <?= $tone ?>"><?= $label ?></span></td>
                <td class="row" style="justify-content:flex-end;gap:.25rem">
                    <?php if ($p['id'] && $p['action'] !== 'skipped'): ?>
                        <a class="btn btn-primary btn-sm" href="/admin/builder/page/<?= (int) $p['id'] ?>">Open in builder</a>
                        <a class="btn btn-ghost btn-sm" href="/preview/page/<?= (int) $p['id'] ?>" target="_blank" rel="noopener">Preview</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="grid grid-3">
    <div class="card"><div class="card-head"><h2>Templates</h2></div><div class="card-body small">
        <?php if (!$report['templates']): ?><p class="muted">None.</p><?php endif; ?>
        <?php foreach ($report['templates'] as $t): ?><p><a href="/admin/builder/template/<?= (int) $t['id'] ?>"><?= e($t['name']) ?></a> <span class="muted">(<?= e($t['kind']) ?>)</span></p><?php endforeach; ?>
        <?php if ($report['templates']): ?><p><a href="/admin/builder/templates">Make them active in Builder templates →</a></p><?php endif; ?>
    </div></div>
    <div class="card"><div class="card-head"><h2>Forms</h2></div><div class="card-body small">
        <?php if (!$report['forms']): ?><p class="muted">None.</p><?php endif; ?>
        <?php foreach ($report['forms'] as $f): ?><p><?= e($f['name']) ?> <span class="muted">(<?= $f['action'] === 'created' ? 'created' : 'already existed, kept' ?>)</span></p><?php endforeach; ?>
    </div></div>
    <div class="card"><div class="card-head"><h2>Menus</h2></div><div class="card-body small">
        <?php if (!$report['menus']): ?><p class="muted">Not changed.</p><?php else: ?><p>Replaced: <?= e(implode(', ', $report['menus'])) ?>. <a href="/admin/navigation">Review in Navigation →</a></p><?php endif; ?>
    </div></div>
</div>
