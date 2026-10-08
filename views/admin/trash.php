<?php
use App\Cms\Icons;
use App\Http\Form;

$labels = ['page' => 'Pages', 'product' => 'Products', 'post' => 'Articles', 'form' => 'Forms', 'lead' => 'Leads', 'customer' => 'Customers', 'popup' => 'Popups', 'magnet' => 'Lead magnets', 'category' => 'Product categories', 'brand' => 'Brands'];
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Recycle bin', 'sub' => 'Deleted items keep their history (leads still point to them). Restore them, or delete them for good.']) ?>
<?php if (!$groups): ?><div class="card"><div class="empty"><?= Icons::svg('trash-restore', 'icon', 32) ?><p>The recycle bin is empty.</p></div></div><?php endif; ?>
<?php foreach ($groups as $type => $rows): ?>
    <div class="card"><div class="card-head"><h2><?= e($labels[$type]) ?> <span class="badge"><?= count($rows) ?></span></h2></div>
        <div class="table-wrap"><table class="table"><tbody>
            <?php foreach ($rows as $r): ?><tr><td><?= e($r['label']) ?></td><td class="small muted">Deleted <?= e(time_ago($r['deleted_at'])) ?></td>
                <td class="actions"><?= Form::action('/admin/trash/' . $type . '/' . $r['id'] . '/restore', 'Restore', 'btn btn-secondary btn-sm') ?><?= Form::action('/admin/trash/' . $type . '/' . $r['id'] . '/purge', 'Delete forever', 'btn btn-danger-ghost btn-sm', 'Delete permanently? This cannot be undone.') ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
<?php endforeach; ?>
