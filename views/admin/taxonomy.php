<?php
/**
 * Shared list + edit screen for simple taxonomies (page categories, product
 * categories, brands, blog categories, blog tags).
 */
use App\Http\Form;

$e = $edit ?? [];
$fields ??= [];
$tabs ??= [];
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => $heading, 'sub' => $sub ?? '', 'crumbs' => $crumbs ?? []]) ?>
<?php if ($tabs): ?>
    <nav class="tabs"><?php foreach ($tabs as [$label, $href]): ?><a class="tab<?= $href === $action ? ' active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach; ?></nav>
<?php endif; ?>
<div class="builder">
    <div class="card">
        <?php if (!$rows): ?>
            <div class="empty"><p>Nothing here yet. Add the first one.</p></div>
        <?php else: ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Name</th><th class="hide-sm">Slug</th><?php if (isset($parents)): ?><th class="hide-sm">Parent</th><?php endif; ?><th><?= e($countLabel) ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><a class="row-title" href="?edit=<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a><?php if (isset($r['is_active']) && !(int) $r['is_active']): ?> <span class="badge">inactive</span><?php endif; ?></td>
                        <td class="hide-sm small muted"><?= e($r['slug']) ?></td>
                        <?php if (isset($parents)): ?><td class="hide-sm small"><?= e($r['parent_name'] ?? '—') ?></td><?php endif; ?>
                        <td><?= (int) $r[$countKey] ?></td>
                        <td class="actions">
                            <a class="btn btn-ghost btn-sm" href="?edit=<?= (int) $r['id'] ?>">Edit</a>
                            <?= Form::action($action . '/' . $r['id'] . '/delete', 'Delete', 'btn btn-danger-ghost btn-sm', 'Delete "' . $r['name'] . '"? Items using it keep working but lose this ' . strtolower($heading) . ' link.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
    <form class="card" method="post" action="<?= e($action) ?>">
        <div class="card-head"><h2><?= $e ? 'Edit' : 'Add' ?></h2><?php if ($e): ?><a class="small" href="<?= e($action) ?>">New instead</a><?php endif; ?></div>
        <div class="card-body stack">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($e['id'] ?? '') ?>">
            <?= Form::input('name', 'Name', $e['name'] ?? '', ['required' => true, 'maxlength' => 160, 'data' => ['slug-source' => 'tax-slug']]) ?>
            <?= Form::input('slug', 'Slug', $e['slug'] ?? '', ['id' => 'tax-slug', 'help' => 'Used in addresses. Blank = from the name.']) ?>
            <?php if (isset($parents)): ?>
                <?= Form::select('parent_id', 'Parent', array_diff_key($parents, [(int) ($e['id'] ?? 0) => true]), (string) ($e['parent_id'] ?? ''), ['empty' => 'None (top level)']) ?>
            <?php endif; ?>
            <?php if (in_array('description', $fields, true)): ?><?= Form::textarea('description', 'Description', $e['description'] ?? '', ['rows' => 3]) ?><?php endif; ?>
            <?php if (in_array('website_url', $fields, true)): ?><?= Form::input('website_url', 'Website', $e['website_url'] ?? '') ?><?php endif; ?>
            <?php if (in_array('image', $fields, true)): ?><?= Form::media('image', 'Image / logo', $e['image_url'] ?? '', ['kind' => 'IMAGE']) ?><?php endif; ?>
            <?php if (in_array('seo', $fields, true)): ?>
                <?= Form::input('seo_title', 'SEO title', $e['seo_title'] ?? '', ['maxlength' => 255]) ?>
                <?= Form::textarea('seo_description', 'Meta description', $e['seo_description'] ?? '', ['rows' => 2, 'maxlength' => 500]) ?>
                <?= Form::toggle('no_index', 'Hide the archive from search engines', $e['no_index'] ?? 0) ?>
            <?php endif; ?>
            <?php if (in_array('is_active', $fields, true)): ?><?= Form::toggle('is_active', 'Active', $e['is_active'] ?? 1) ?><?php endif; ?>
            <?php if (!in_array('no_sort', $fields, true)): ?><?= Form::input('sort_order', 'Order', $e['sort_order'] ?? 0, ['type' => 'number']) ?><?php endif; ?>
        </div>
        <div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save</button></div>
    </form>
</div>
