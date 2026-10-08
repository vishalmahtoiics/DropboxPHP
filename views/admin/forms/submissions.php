<?php use App\Http\Form; ?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Submissions', 'sub' => $form['name'], 'crumbs' => [['Forms', '/admin/forms'], [$form['name'], '/admin/forms/' . $form['id']], ['Submissions', null]],
    'actions' => can('leads.export') && $total ? '<a class="btn btn-secondary" href="?export=csv">Export CSV</a>' : '']) ?>
<div class="card">
    <?php if (!$rows): ?><div class="empty"><p>No submissions yet.</p></div><?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Received</th><?php foreach (array_slice($fields, 0, 4) as $f): ?><th><?= e($f['label']) ?></th><?php endforeach; ?><th>Lead</th></tr></thead>
        <tbody><?php foreach ($rows as $s): $data = json_decode((string) $s['data'], true) ?: []; ?>
            <tr>
                <td class="small nowrap"><?= e(format_datetime($s['created_at'])) ?><div class="muted"><?= e($s['country_name'] ?? '') ?></div></td>
                <?php foreach (array_slice($fields, 0, 4) as $f): $val = $data[$f['name']] ?? ''; ?><td class="small"><?= e(str_limit(is_array($val) ? implode(', ', $val) : (string) $val, 80)) ?></td><?php endforeach; ?>
                <td><?php if ($s['lead_id'] && can('leads.view')): ?><a href="/admin/leads/<?= (int) $s['lead_id'] ?>">#<?= (int) $s['lead_id'] ?></a><?php else: ?>—<?php endif; ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= Form::pagination($pageNo, $pages, $total, 'submissions') ?>
    <?php endif; ?>
</div>
