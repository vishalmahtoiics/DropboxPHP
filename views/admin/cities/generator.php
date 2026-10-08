<?php
use App\Http\Form;
use App\Services\Cities;
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'City Page Generator', 'crumbs' => [['Cities', '/admin/cities'], ['Generator', null]], 'sub' => 'Copy one page into many cities, filling placeholders. Each copy is independent afterwards; nothing syncs.']) ?>
<?php if (!$cities): ?><div class="alert alert-info"><span>Add cities first under <a href="/admin/cities">Cities</a>.</span></div><?php endif; ?>
<form class="card" method="post" action="/admin/cities/generator"><div class="card-body stack">
    <?= csrf_field() ?>
    <?= Form::select('template_id', 'Template page', $pages, '', ['required' => true, 'empty' => 'Choose a page to copy', 'help' => 'Write it with placeholders, e.g. "Dropbox resellers in {{city.name}}".']) ?>
    <div class="grid grid-2">
        <?= Form::input('slug_pattern', 'Address pattern', '{{city.slug}}', ['required' => true, 'help' => '{{city.slug}} for the city landing page, {{city.slug}}/dropbox-business for a sub-page.']) ?>
        <?= Form::input('title_pattern', 'Title pattern', 'Dropbox in {{city.name}}') ?>
    </div>
    <div class="field"><span class="label">Cities</span>
        <label class="check"><input type="checkbox" data-check-all="cities[]"> All</label>
        <div class="grid grid-4"><?php foreach ($cities as $c): ?><label class="check"><input type="checkbox" name="cities[]" value="<?= (int) $c['id'] ?>"> <?= e($c['name']) ?></label><?php endforeach; ?></div>
    </div>
    <?php if (can('pages.publish')): ?><?= Form::toggle('publish', 'Publish immediately (otherwise drafts)', false) ?><?php endif; ?>
    <p class="help">Placeholders: <?php foreach (Cities::TOKENS as $t): ?><code>{{<?= e($t) ?>}}</code> <?php endforeach; ?>— replaced in every section, the title and the SEO fields. Existing addresses are skipped, never overwritten.</p>
</div><div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Generate pages</button></div></form>
<?php if ($batches): ?>
<div class="card"><div class="card-head"><h2>Recent batches</h2></div><div class="table-wrap"><table class="table"><tbody>
    <?php foreach ($batches as $b): ?><tr><td class="small"><?= e(format_datetime($b['at'])) ?></td><td><?= (int) $b['n'] ?> pages from template <a href="/admin/pages/<?= (int) $b['tpl'] ?>">#<?= (int) $b['tpl'] ?></a></td><td><code><?= e($b['generation_batch']) ?></code></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
