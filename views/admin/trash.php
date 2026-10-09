<?php
use App\Cms\Icons;
use App\Http\Form;

$labels = ['page' => 'Pages', 'product' => 'Products', 'post' => 'Articles', 'form' => 'Forms', 'lead' => 'Leads', 'customer' => 'Customers', 'popup' => 'Popups', 'magnet' => 'Lead magnets', 'category' => 'Product categories', 'brand' => 'Brands'];
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Recycle bin', 'sub' => 'Deleted items keep their history (leads still point to them). Restore them, or delete them for good.']) ?>
<?php if (!$groups): ?><div class="card"><div class="empty"><?= Icons::svg('trash-restore', 'icon', 32) ?><p>The recycle bin is empty.</p></div></div><?php endif; ?>
<?php foreach ($groups as $type => $rows): ?>
    <div class="card"><div class="card-head"><h2><?= e($labels[$type]) ?> <span class="badge"><?= count($rows) ?></span></h2></div>
        <?= Form::bulkBar('bulk-trash-' . $type, '/admin/trash/bulk', ['restore:' . $type => 'Restore', 'purge:' . $type => 'Delete forever'], strtolower($labels[$type])) ?>
        <div class="table-wrap"><table class="table"><thead><tr><th class="check"><?= Form::bulkAll('bulk-trash-' . $type) ?></th><th>Item</th><th></th><th></th></tr></thead><tbody>
            <?php foreach ($rows as $r): ?><tr><td class="check"><?= Form::bulkCheck('bulk-trash-' . $type, (int) $r['id'], (string) $r['label']) ?></td><td><?= e($r['label']) ?></td><td class="small muted">Deleted <?= e(time_ago($r['deleted_at'])) ?></td>
                <td class="actions"><?= Form::action('/admin/trash/' . $type . '/' . $r['id'] . '/restore', 'Restore', 'btn btn-secondary btn-sm') ?><?= Form::action('/admin/trash/' . $type . '/' . $r['id'] . '/purge', 'Delete forever', 'btn btn-danger-ghost btn-sm', 'Delete permanently? This cannot be undone.') ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
<?php endforeach; ?>
