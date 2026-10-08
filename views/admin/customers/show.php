<?php
use App\Http\Form;
use App\Services\Money;
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => $c['company'] ?: $c['name'], 'crumbs' => [['Customers', '/admin/customers'], ['#' . $c['id'], null]], 'sub' => $c['name'] . ' · ' . $c['email'],
    'actions' => can('customers.delete') ? Form::action('/admin/customers/' . $c['id'] . '/delete', 'Delete', 'btn btn-danger-ghost', 'Move this customer to the recycle bin?', 'trash') : '']) ?>
<div class="grid grid-3">
    <div class="span-2">
        <div class="card"><div class="card-head"><h2>Subscriptions & products</h2></div>
            <?php if ($items): ?><div class="table-wrap"><table class="table"><thead><tr><th>Product</th><th>Qty / seats</th><th>Unit price</th><th>Starts</th><th>Renews</th><th></th></tr></thead><tbody>
                <?php foreach ($items as $i): ?><tr><td><?= e($i['product_name']) ?><?php if ($i['notes']): ?><div class="small muted"><?= e($i['notes']) ?></div><?php endif; ?></td><td><?= (int) $i['quantity'] ?><?= $i['seats'] ? ' / ' . (int) $i['seats'] . ' seats' : '' ?></td><td><?= $i['unit_price'] !== null ? e(Money::format($i['unit_price'], $adminCountry['currency_symbol'], $adminCountry['currency'])) : '—' ?></td><td class="small"><?= e(format_date($i['starts_at'])) ?></td><td class="small <?= $i['renews_at'] && strtotime($i['renews_at']) < strtotime('+30 days') ? 'error-text' : '' ?>"><?= e(format_date($i['renews_at'])) ?></td>
                    <td class="actions"><?php if (can('customers.edit')): ?><?= Form::action('/admin/customers/' . $c['id'] . '/product/' . $i['id'] . '/delete', 'Remove', 'btn btn-danger-ghost btn-sm', 'Remove this subscription?') ?><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
            <?php if (can('customers.edit')): ?>
            <form class="card-body" method="post" action="/admin/customers/<?= (int) $c['id'] ?>/product"><?= csrf_field() ?>
                <div class="grid grid-4">
                    <?= Form::select('product_id', 'Product', $products, '', ['required' => true, 'empty' => 'Choose…']) ?>
                    <?= Form::input('quantity', 'Quantity', '1', ['type' => 'number', 'min' => 1]) ?>
                    <?= Form::input('seats', 'Seats', '', ['type' => 'number', 'min' => 0]) ?>
                    <?= Form::input('unit_price', 'Unit price', '', ['inputmode' => 'decimal']) ?>
                    <?= Form::input('starts_at', 'Starts', date('Y-m-d'), ['type' => 'date']) ?>
                    <?= Form::input('renews_at', 'Renews', date('Y-m-d', strtotime('+1 year')), ['type' => 'date']) ?>
                    <div class="span-2"><?= Form::input('notes', 'Notes', '') ?></div>
                </div>
                <button class="btn btn-secondary btn-sm mt-1" type="submit">Add subscription</button>
            </form>
            <?php endif; ?>
        </div>
        <form class="card" method="post" action="/admin/customers/<?= (int) $c['id'] ?>"><div class="card-head"><h2>Details</h2></div>
            <div class="card-body"><?= csrf_field() ?><?= App\Core\View::partial('admin/customers/_fields', ['c' => $c, 'staff' => $staff]) ?></div>
            <?php if (can('customers.edit')): ?><div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save</button></div><?php endif; ?>
        </form>
    </div>
    <div>
        <div class="card"><div class="card-head"><h2>Notes</h2></div><div class="card-body">
            <?php if (can('customers.edit')): ?><form method="post" action="/admin/customers/<?= (int) $c['id'] ?>/note" class="stack mb-2"><?= csrf_field() ?><textarea class="textarea" name="body" rows="3" required placeholder="Add a note…"></textarea><button class="btn btn-secondary btn-sm" type="submit">Add note</button></form><?php endif; ?>
            <ul class="timeline"><?php foreach ($notes as $n): ?><li><div class="small muted"><?= e(time_ago($n['created_at'])) ?> · <?= e($n['author'] ?? '') ?></div><div style="white-space:pre-wrap"><?= e($n['body']) ?></div></li><?php endforeach; ?></ul>
        </div></div>
        <div class="card"><div class="card-head"><h2>Leads</h2></div><div class="card-body small">
            <?php if (!$leads): ?><p class="muted">No linked leads.</p><?php endif; ?>
            <?php foreach ($leads as $l): ?><p><a href="/admin/leads/<?= (int) $l['id'] ?>">#<?= (int) $l['id'] ?> <?= e($l['name']) ?></a> <?= Form::badge($l['status']) ?> <span class="muted"><?= e(format_date($l['created_at'])) ?></span></p><?php endforeach; ?>
        </div></div>
    </div>
</div>
