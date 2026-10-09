<?php
/** @var list<array> $templates @var array<int,int> $usage */
use App\Cms\Icons;
use App\Http\Form;

$kinds = ['header' => 'Site header', 'footer' => 'Site footer', 'product' => 'Product page template', 'page' => 'Page template', 'section' => 'Section'];
$activeLabel = ['header' => 'Active header', 'footer' => 'Active footer', 'product' => 'Default for products'];
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Builder templates', 'sub' => 'Reusable layouts for the visual builder. Build the site header and footer here, and product templates that design every product page. Nothing changes on the live site until you make a template active.']) ?>
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
                            <?php if ((int) $t['is_default']): ?> <span class="badge green"><?= e($activeLabel[$t['kind']] ?? 'Default') ?></span><?php endif; ?>
                            <?php if ((int) $t['has_draft']): ?> <span class="badge orange">Draft</span><?php endif; ?>
                            <?php if (!empty($usage[$t['id']])): ?><br><span class="small muted">Chosen by <?= (int) $usage[$t['id']] ?> product(s)</span><?php endif; ?>
                        </td>
                        <td><?= e($kinds[$t['kind']] ?? $t['kind']) ?></td>
                        <td class="small muted"><?= e(time_ago((string) $t['updated_at'])) ?><?= $t['editor'] ? ' · ' . e($t['editor']) : '' ?></td>
                        <td class="row" style="justify-content:flex-end;gap:.25rem">
                            <a class="btn btn-primary btn-sm" href="/admin/builder/template/<?= (int) $t['id'] ?>"><?= Icons::svg('edit', 'icon', 14) ?> Edit</a>
                            <?php if (isset($activeLabel[$t['kind']])): ?>
                                <?php $verb = $t['kind'] === 'product' ? 'default' : 'active'; ?>
                                <form method="post" action="/admin/builder/templates/<?= (int) $t['id'] ?>"<?= (int) $t['is_default'] ? '' : ' data-confirm="' . e($t['kind'] === 'product' ? 'Use this template for every product page that uses the default template?' : 'Show this ' . $t['kind'] . ' on every page of the live site?') . '"' ?>><?= csrf_field() ?><input type="hidden" name="action" value="<?= (int) $t['is_default'] ? 'undefault' : 'default' ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= (int) $t['is_default'] ? 'Turn off' : 'Make ' . $verb ?></button></form>
                            <?php endif; ?>
                            <a class="btn btn-ghost btn-sm" href="/admin/builder/template/<?= (int) $t['id'] ?>/export" title="Export as a .json file"><?= Icons::svg('download', 'icon', 14) ?></a>
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
    <div class="stack">
    <form method="post" action="/admin/builder/templates" class="card">
        <?= csrf_field() ?>
        <div class="card-head"><h2>New template</h2></div>
        <div class="card-body stack">
            <?= Form::input('name', 'Name', '', ['required' => true, 'maxlength' => 160, 'placeholder' => 'e.g. Product page']) ?>
            <?= Form::select('kind', 'Type', $kinds, 'header') ?>
            <p class="help">Headers, footers and product templates start from a ready layout you can change — logo, menu and button; menus, contact details and copyright; or the classic product page rebuilt in widgets.</p>
        </div>
        <div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Create and open the builder</button></div>
    </form>
    <form method="post" action="/admin/builder/templates/import" enctype="multipart/form-data" class="card">
        <?= csrf_field() ?>
        <div class="card-head"><h2>Import a template</h2></div>
        <div class="card-body stack">
            <label class="field"><span class="label">Layout file (.json)</span><input class="input" type="file" name="file" accept=".json,application/json" required></label>
            <?= Form::input('name', 'Name', '', ['maxlength' => 160, 'placeholder' => 'Blank = the name in the file']) ?>
            <?= Form::select('kind', 'Type', ['' => 'As saved in the file'] + $kinds, '') ?>
            <p class="help">A file exported from any builder screen — a page, product, header, footer or section. It is added as a new template; nothing on the live site changes until you make it active or insert it.</p>
        </div>
        <div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Import and open the builder</button></div>
    </form>
    </div>
</div>
