<?php
use App\Cms\Icons;
use App\Http\Form;
use App\Http\Controllers\Admin\LeadMagnetController;
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Lead magnets', 'sub' => 'Guides, checklists and e-books offered in exchange for contact details.', 'actions' => '<a class="btn btn-primary" href="/admin/lead-magnets/new">' . Icons::svg('plus', 'icon', 16) . ' New lead magnet</a>']) ?>
<div class="card">
    <?php if (!$rows): ?><div class="empty"><?= Icons::svg('gift', 'icon', 32) ?><p>No lead magnets yet.</p></div><?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Title</th><th>Form</th><th>Leads</th><th>Downloads</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($rows as $m): ?><tr><td><a class="row-title" href="/admin/lead-magnets/<?= (int) $m['id'] ?>"><?= e($m['title']) ?></a><div class="small muted"><?= e(LeadMagnetController::KINDS[$m['kind']]) ?> · <?= e($m['slug']) ?></div></td>
            <td class="small"><?= e($m['form_name'] ?? 'Direct download') ?></td><td><?= (int) $m['leads'] ?></td><td><?= (int) $m['download_count'] ?></td>
            <td><?= $m['is_active'] ? Form::badge('ACTIVE', 'Active') : Form::badge('INACTIVE', 'Off') ?></td>
            <td class="actions"><?= Form::action('/admin/lead-magnets/' . $m['id'] . '/delete', 'Delete', 'btn btn-danger-ghost btn-sm', 'Delete this lead magnet?') ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</div>
