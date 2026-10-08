<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'New customer', 'crumbs' => [['Customers', '/admin/customers'], ['New', null]]]) ?>
<form class="card" method="post" action="/admin/customers"><div class="card-body"><?= csrf_field() ?><?= App\Core\View::partial('admin/customers/_fields', ['c' => null, 'staff' => $staff]) ?></div>
<div class="card-foot form-actions"><a class="btn btn-ghost" href="/admin/customers">Cancel</a><button class="btn btn-primary" type="submit">Add customer</button></div></form>
