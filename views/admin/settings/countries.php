<?php
use App\Cms\Icons;
use App\Http\Form;
use App\Services\Countries;

$e = $edit ?? [];
$s = $e['settings'] ?? [];
$showForm = $edit || $new;
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Countries', 'sub' => 'Each market has its own address prefix, currency, pages, menus, prices, contact details, SEO and leads.', 'actions' => '<a class="btn btn-primary" href="?new=1">' . Icons::svg('plus', 'icon', 16) . ' Add market</a>']) ?>
<div class="card mb-2">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Market</th><th>Address</th><th>Currency</th><th>Pages</th><th>Leads</th><th>Status</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr><td><a class="row-title" href="?edit=<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a> <span class="badge"><?= e($r['code']) ?></span><?php if ((int) $r['is_default']): ?> <span class="badge blue">Default</span><?php endif; ?></td>
                <td><code><?= e(Countries::href(['is_default' => $r['is_default'], 'slug' => $r['slug']], '/')) ?></code></td>
                <td><?= e($r['currency_symbol'] . ' ' . $r['currency']) ?></td><td><?= (int) $r['pages'] ?></td><td><?= (int) $r['leads'] ?></td>
                <td><?= (int) $r['is_active'] ? ((int) $r['is_published'] ? Form::badge('ACTIVE', 'Live') : Form::badge('DRAFT', 'Hidden from switcher')) : Form::badge('INACTIVE', 'Inactive') ?></td>
                <td class="actions"><a class="btn btn-ghost btn-sm" href="?edit=<?= (int) $r['id'] ?>">Edit</a><?php if (!(int) $r['is_default']): ?><?= Form::action('/admin/countries/' . $r['id'] . '/delete', 'Delete', 'btn btn-danger-ghost btn-sm', 'Delete this market? Only possible when it has no content.') ?><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</div>
<?php if ($showForm): ?>
<form class="card" method="post" action="/admin/countries">
    <div class="card-head"><h2><?= $edit ? 'Edit ' . e($e['name']) : 'New market' ?></h2></div>
    <div class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($e['id'] ?? '') ?>">
        <div class="grid grid-4">
            <?= Form::input('name', 'Name', $e['name'] ?? '', ['required' => true]) ?>
            <?= Form::input('code', 'ISO code', $e['code'] ?? '', ['required' => true, 'maxlength' => 2, 'placeholder' => 'AE']) ?>
            <?php if ((int) ($e['is_default'] ?? 0)): ?><?= Form::input('slug', 'Address prefix', '/', ['readonly' => true, 'help' => 'The default market is served from the root.']) ?><?php else: ?><?= Form::input('slug', 'Address prefix', $e['slug'] ?? '', ['prefix' => '/', 'placeholder' => 'ae']) ?><?php endif; ?>
            <?= Form::input('locale', 'Locale', $e['locale'] ?? 'en', ['placeholder' => 'en-AE']) ?>
            <?= Form::input('currency', 'Currency', $e['currency'] ?? '', ['required' => true, 'maxlength' => 3, 'placeholder' => 'AED']) ?>
            <?= Form::input('currency_symbol', 'Currency symbol', $e['currency_symbol'] ?? '', ['required' => true, 'maxlength' => 8]) ?>
            <?= Form::input('phone_code', 'Phone code', $e['phone_code'] ?? '', ['placeholder' => '+971']) ?>
            <?= Form::input('timezone', 'Timezone', $e['timezone'] ?? 'Asia/Kolkata') ?>
            <?= Form::input('sort_order', 'Order', $e['sort_order'] ?? 0, ['type' => 'number']) ?>
        </div>
        <div class="grid grid-4">
            <?= Form::toggle('is_active', 'Active', $e['is_active'] ?? 1) ?>
            <?= Form::toggle('is_published', 'Show in the country switcher', $e['is_published'] ?? 1) ?>
            <?= Form::toggle('noindex', 'Hide this market from search engines', $s['noindex'] ?? false) ?>
            <?= Form::toggle('exclude_from_sitemap', 'Leave out of the sitemap', $s['exclude_from_sitemap'] ?? false) ?>
        </div>
        <?php if (!$edit): ?>
            <?= Form::select('copy_from', 'Start with a copy of', array_column($rows, 'name', 'id'), '', ['empty' => 'Nothing (empty market)', 'help' => 'Copies pages (as drafts), menus and product availability. Copies are independent afterwards.']) ?>
        <?php endif; ?>
        <hr><h3>Company, contact and SEO for this market</h3>
        <div class="grid grid-3">
            <?php foreach ($fields as $k => $label): ?>
                <?php if (in_array($k, ['address', 'default_description', 'robots_disallow'], true)): ?>
                    <?= Form::textarea('s_' . $k, $label, $s[$k] ?? '', ['rows' => 2]) ?>
                <?php else: ?>
                    <?= Form::input('s_' . $k, $label, $s[$k] ?? '') ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-foot form-actions"><a class="btn btn-ghost" href="/admin/countries">Cancel</a><button class="btn btn-primary" type="submit">Save market</button></div>
</form>
<?php endif; ?>
