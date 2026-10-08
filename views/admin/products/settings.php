<?php use App\Http\Form; ?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Product page settings', 'crumbs' => [['Products', '/admin/products'], ['Settings', null]]]) ?>
<form class="card" method="post" action="/admin/products/settings">
    <div class="card-body stack">
        <?= csrf_field() ?>
        <?= Form::input('base_path', 'Address prefix', $s['base_path'], ['prefix' => '/', 'help' => 'Product pages live at /' . $s['base_path'] . '/product-name.']) ?>
        <?= Form::select('default_form_slug', 'Default enquiry form', $forms, $s['default_form_slug'], ['empty' => 'First available ("quote" or "contact")']) ?>
        <?= Form::input('benefits_heading', 'Benefits heading', $s['benefits_heading'], ['help' => '{product} is replaced by the product name.']) ?>
        <?= Form::toggle('show_compare_price', 'Show compare-at prices', $s['show_compare_price']) ?>
        <?= Form::toggle('show_features', 'Show the features list', $s['show_features']) ?>
        <?= Form::toggle('show_specs', 'Show specifications in the price box', $s['show_specs']) ?>
        <?= Form::toggle('show_benefits', 'Show benefits', $s['show_benefits']) ?>
        <?= Form::toggle('show_related', 'Show related products', $s['show_related']) ?>
    </div>
    <div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save</button></div>
</form>
