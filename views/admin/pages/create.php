<?php use App\Http\Form; ?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'New page', 'crumbs' => [['Pages', '/admin/pages'], ['New', null]], 'sub' => 'In ' . $adminCountry['name'] . '. You will add sections in the page builder next.']) ?>
<form class="card" method="post" action="/admin/pages">
    <div class="card-body stack">
        <?= csrf_field() ?>
        <?= Form::input('title', 'Title', '', ['required' => true, 'maxlength' => 255, 'autofocus' => true, 'data' => ['slug-source' => 'slug']]) ?>
        <?= Form::input('slug', 'Address', '', ['id' => 'slug', 'prefix' => rtrim(base_url() . App\Services\Countries::href($adminCountry, '/'), '/') . '/', 'help' => 'Lowercase words separated by hyphens. Slashes make nested addresses, like plans/business.']) ?>
        <?php if ($categories): ?><?= Form::select('category_id', 'Category', $categories, '', ['empty' => 'None']) ?><?php endif; ?>
        <?= Form::select('template_id', 'Start from', $templates, '', ['empty' => 'A blank page', 'help' => 'Copies every section of the chosen page.']) ?>
    </div>
    <div class="card-foot form-actions"><a class="btn btn-ghost" href="/admin/pages">Cancel</a><button class="btn btn-primary" type="submit">Create page</button></div>
</form>
