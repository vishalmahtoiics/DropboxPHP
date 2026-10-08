<?php
use App\Cms\Icons;
use App\Http\Form;
use App\Services\Countries;

$e = $edit ?? [];
$s = $e['settings'] ?? [];
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Cities', 'sub' => 'Local landing pages inside ' . $adminCountry['name'] . ': a city owns /city-name and everything under it.', 'actions' => can('pages.create') ? '<a class="btn btn-primary" href="/admin/cities/generator">' . Icons::svg('sparkles', 'icon', 16) . ' City Page Generator</a>' : '']) ?>
<div class="builder">
    <div class="card">
        <?php if (!$rows): ?><div class="empty"><?= Icons::svg('map-pin', 'icon', 32) ?><p>No cities yet. Add one, then generate its pages.</p></div><?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>City</th><th>Address</th><th>Pages</th><th>Status</th><th></th></tr></thead><tbody>
            <?php foreach ($rows as $c): ?><tr>
                <td><a class="row-title" href="?edit=<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a><?php if ($c['state']): ?><div class="small muted"><?= e($c['state']) ?></div><?php endif; ?></td>
                <td><code><?= e(Countries::href($adminCountry, '/' . $c['slug'])) ?></code><?php if ($c['landing_id']): ?> <a class="small" href="/admin/pages/<?= (int) $c['landing_id'] ?>">landing page</a><?php endif; ?></td>
                <td><a href="/admin/pages?q=<?= e(rawurlencode($c['slug'] . '/')) ?>"><?= (int) $c['pages'] ?></a></td>
                <td><?= (int) $c['is_active'] ? Form::badge('ACTIVE', 'Active') : Form::badge('INACTIVE', 'Offline') ?><?= (int) $c['no_index'] ? ' <span class="badge">noindex</span>' : '' ?></td>
                <td class="actions"><?php if (can('pages.delete')): ?><?= Form::action('/admin/cities/' . $c['id'] . '/delete', 'Delete', 'btn btn-danger-ghost btn-sm', 'Delete this city?') ?><?php endif; ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </div>
    <?php if (can('pages.edit')): ?>
    <form class="card" method="post" action="/admin/cities"><div class="card-head"><h2><?= $e ? 'Edit city' : 'Add a city' ?></h2><?php if ($e): ?><a class="small" href="/admin/cities">New instead</a><?php endif; ?></div><div class="card-body stack">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($e['id'] ?? '') ?>">
        <?= Form::input('name', 'Name', $e['name'] ?? '', ['required' => true, 'data' => ['slug-source' => 'city-slug']]) ?>
        <?= Form::input('slug', 'Address', $e['slug'] ?? '', ['id' => 'city-slug', 'prefix' => Countries::href($adminCountry, '/')]) ?>
        <?= Form::input('state', 'State / region', $e['state'] ?? '') ?>
        <?= Form::input('phone', 'Local phone', $s['phone'] ?? '', ['help' => '{{city.phone}} in generated pages. Blank = the market\'s.']) ?>
        <?= Form::input('email', 'Local email', $s['email'] ?? '') ?>
        <?= Form::textarea('address', 'Local address', $s['address'] ?? '', ['rows' => 2]) ?>
        <?= Form::toggle('is_active', 'Active (its pages are online)', $e['is_active'] ?? 1) ?>
        <?= Form::toggle('is_published', 'List in the sitemap', $e['is_published'] ?? 1) ?>
        <?= Form::toggle('no_index', 'Hide from search engines', $e['no_index'] ?? 0) ?>
    </div><div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save city</button></div></form>
    <?php endif; ?>
</div>
