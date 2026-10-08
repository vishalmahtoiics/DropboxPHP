<?php use App\Cms\Icons; ?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Product order', 'crumbs' => [['Products', '/admin/products'], ['Order', null]], 'sub' => 'Drag to set the order in ' . $adminCountry['name'] . '. Saved as soon as you drop.']) ?>
<div class="grid grid-2">
    <div class="card"><div class="card-head"><h2>Catalogue order</h2></div><div class="card-body">
        <ul class="section-list" data-sortable="/admin/products/order?type=catalogue">
            <?php foreach ($rows as $r): ?><li class="section-item" draggable="true" data-sort-id="<?= (int) $r['id'] ?>"><span class="drag-handle" tabindex="0"><?= Icons::svg('drag', 'icon', 18) ?></span><span class="section-meta"><strong><?= e($r['name']) ?></strong><span class="small muted"><?= e(strtolower($r['status'])) ?></span></span></li><?php endforeach; ?>
        </ul>
    </div></div>
    <div class="card"><div class="card-head"><h2>Featured order</h2></div><div class="card-body">
        <?php if (!$featured): ?><p class="muted">No featured products in this market. Tick "Featured" on a product's Pricing & markets tab.</p><?php endif; ?>
        <ul class="section-list" data-sortable="/admin/products/order?type=featured">
            <?php foreach ($featured as $r): ?><li class="section-item" draggable="true" data-sort-id="<?= (int) $r['id'] ?>"><span class="drag-handle" tabindex="0"><?= Icons::svg('drag', 'icon', 18) ?></span><span class="section-meta"><strong><?= e($r['name']) ?></strong></span></li><?php endforeach; ?>
        </ul>
    </div></div>
</div>
