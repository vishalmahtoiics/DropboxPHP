<?php
/** @var list<array> $templates @var array<int,int> $usage */
use App\Cms\Icons;
use App\Http\Form;

$kinds = ['product' => 'Product page template', 'page' => 'Page template', 'section' => 'Section'];
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Builder templates', 'sub' => 'Reusable layouts for the visual builder. A product template designs every product page that uses it — set one as the default and all products follow it.']) ?>
<div class="grid grid-3">
    <div class="span-2">
        <div class="card">
            <?php if (!$templates): ?>
                <div class="card-body"><p class="muted">No templates yet. Create a product template to design all product pages at once, or save any container from the builder's right-click menu.</p></div>
            <?php else: ?>
            <table class="table">
                <thead><tr><th>Name</th><th>Type</th><th>Updated</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($templates as $t): ?>
                    <tr>
                        <td><a href="/admin/builder/template/<?= (int) $t['id'] ?>"><strong><?= e($t['name']) ?></strong></a>
                            <?php if ((int) $t['is_default']): ?> <span class="badge green">Default for products</span><?php endif; ?>
                            <?php if ((int) $t['has_draft']): ?> <span class="badge orange">Draft</span><?php endif; ?>
                            <?php if (!empty($usage[$t['id']])): ?><br><span class="small muted">Chosen by <?= (int) $usage[$t['id']] ?> product(s)</span><?php endif; ?>
                        </td>
                        <td><?= e($kinds[$t['kind']] ?? $t['kind']) ?></td>
                        <td class="small muted"><?= e(time_ago((string) $t['updated_at'])) ?><?= $t['editor'] ? ' · ' . e($t['editor']) : '' ?></td>
                        <td class="row" style="justify-content:flex-end;gap:.25rem">
                            <a class="btn btn-primary btn-sm" href="/admin/builder/template/<?= (int) $t['id'] ?>"><?= Icons::svg('edit', 'icon', 14) ?> Edit</a>
                            <?php if ($t['kind'] === 'product'): ?>
                                <form method="post" action="/admin/builder/templates/<?= (int) $t['id'] ?>"><?= csrf_field() ?><input type="hidden" name="action" value="<?= (int) $t['is_default'] ? 'undefault' : 'default' ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= (int) $t['is_default'] ? 'Unset default' : 'Make default' ?></button></form>
                            <?php endif; ?>
                            <form method="post" action="/admin/builder/templates/<?= (int) $t['id'] ?>"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><button class="btn btn-ghost btn-sm" type="submit" title="Duplicate"><?= Icons::svg('copy', 'icon', 14) ?></button></form>
                            <form method="post" action="/admin/builder/templates/<?= (int) $t['id'] ?>" data-confirm="Delete this template? Products using it fall back to the default template."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-danger-ghost btn-sm" type="submit" title="Delete"><?= Icons::svg('trash', 'icon', 14) ?></button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <form method="post" action="/admin/builder/templates" class="card">
        <?= csrf_field() ?>
        <div class="card-head"><h2>New template</h2></div>
        <div class="card-body stack">
            <?= Form::input('name', 'Name', '', ['required' => true, 'maxlength' => 160, 'placeholder' => 'e.g. Product page']) ?>
            <?= Form::select('kind', 'Type', $kinds, 'product') ?>
            <p class="help">A new product template starts as a copy of the classic product page, built from product widgets you can rearrange.</p>
        </div>
        <div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Create and open the builder</button></div>
    </form>
</div>
