<?php
use App\Cms\Icons;
use App\Http\Form;
use App\Services\Money;

$actions = (can('products.create') ? '<a class="btn btn-primary" href="/admin/products/new">' . Icons::svg('plus', 'icon', 16) . ' New product</a> ' : '')
    . (can('products.edit') ? '<a class="btn btn-secondary" href="/admin/products/order">Order & featured</a> ' : '')
    . (can('settings.manage') ? '<a class="btn btn-ghost" href="/admin/products/settings">' . Icons::svg('settings', 'icon', 16) . '</a>' : '');
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Products', 'sub' => 'The catalogue is shared by every market; availability and prices are set per market. Prices shown for ' . $adminCountry['name'] . '.', 'actions' => $actions]) ?>
<div class="card">
    <form class="toolbar" method="get">
        <input class="input search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, address or SKU">
        <select class="select" name="status" data-autosubmit><option value="">All statuses</option><?php foreach (['DRAFT', 'PUBLISHED', 'ARCHIVED'] as $s): ?><option<?= $status === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
        <?php if ($categories): ?><select class="select" name="category" data-autosubmit><option value="">All categories</option><?php foreach ($categories as $k => $v): ?><option value="<?= (int) $k ?>"<?= (int) ($_GET['category'] ?? 0) === (int) $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select><?php endif; ?>
        <?php if ($brands): ?><select class="select" name="brand" data-autosubmit><option value="">All brands</option><?php foreach ($brands as $k => $v): ?><option value="<?= (int) $k ?>"<?= (int) ($_GET['brand'] ?? 0) === (int) $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select><?php endif; ?>
    </form>
    <?php if (!$rows): ?><div class="empty"><?= Icons::svg('package', 'icon', 32) ?><p>No products yet.</p></div><?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Product</th><th class="hide-sm">Category</th><th>Status</th><th>In <?= e($adminCountry['name']) ?></th><th class="hide-sm">Price (annual / monthly)</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><div class="row" style="gap:.6rem;flex-wrap:nowrap">
                    <?php if ($r['image_url']): ?><img src="<?= e($r['image_url']) ?>" alt="" width="36" height="36" style="border-radius:8px;object-fit:cover"><?php endif; ?>
                    <div><a class="row-title" href="/admin/products/<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a><?php if ((int) $r['pc_featured']): ?> <span class="badge blue">Featured</span><?php endif; ?><div class="small muted"><?= e($r['sku'] ?: $r['slug']) ?></div></div>
                </div></td>
                <td class="hide-sm small"><?= e($r['category_name'] ?? '—') ?><?php if ($r['brand_name']): ?><div class="muted"><?= e($r['brand_name']) ?></div><?php endif; ?></td>
                <td><?= Form::badge($r['status']) ?></td>
                <td><?= $r['pc_id'] ? Form::badge($r['pc_status'], $r['pc_status'] === 'PUBLISHED' ? 'On sale' : ucfirst(strtolower($r['pc_status']))) : '<span class="badge">Not sold</span>' ?></td>
                <td class="hide-sm small"><?= $r['pc_id'] ? e(($r['pc_annual'] !== null ? Money::format($r['pc_annual'], $r['pc_currency'] . ' ', $r['pc_currency']) : '—') . ' / ' . ($r['pc_monthly'] !== null ? Money::format($r['pc_monthly'], $r['pc_currency'] . ' ', $r['pc_currency']) : '—')) : '' ?></td>
                <td class="actions">
                    <a class="btn btn-ghost btn-sm" href="/preview/product/<?= (int) $r['id'] ?>" target="_blank">Preview</a>
                    <?php if (can('products.create')): ?><?= Form::action('/admin/products/' . $r['id'] . '/duplicate', 'Duplicate') ?><?php endif; ?>
                    <?php if (can('products.delete')): ?><?= Form::action('/admin/products/' . $r['id'] . '/delete', 'Delete', 'btn btn-danger-ghost btn-sm', 'Move "' . $r['name'] . '" to the recycle bin?') ?><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
