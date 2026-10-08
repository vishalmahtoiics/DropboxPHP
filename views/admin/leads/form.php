<?php use App\Http\Form; use App\Services\Leads; ?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Add a lead', 'crumbs' => [['Leads', '/admin/leads'], ['New', null]], 'sub' => 'For enquiries that came by phone, email or in person. Market: ' . $adminCountry['name'] . '.']) ?>
<form class="card" method="post" action="/admin/leads">
    <div class="card-body">
        <?= csrf_field() ?>
        <div class="grid grid-2">
            <?= Form::input('name', 'Name', '', ['required' => true]) ?>
            <?= Form::input('email', 'Email', '', ['required' => true, 'type' => 'email']) ?>
            <?= Form::input('phone', 'Phone', '') ?>
            <?= Form::input('company', 'Company', '') ?>
            <?= Form::input('job_title', 'Job title', '') ?>
            <?= Form::select('product_id', 'Product', $products, '', ['empty' => 'Not decided']) ?>
            <?= Form::select('priority', 'Priority', Leads::PRIORITIES, 'MEDIUM') ?>
            <?= Form::input('value', 'Estimated value', '', ['inputmode' => 'decimal']) ?>
            <?= Form::input('source', 'Source', 'Phone', ['help' => 'Phone, Email, Referral, Event…']) ?>
            <?= Form::input('campaign', 'Campaign', '') ?>
            <?= Form::input('follow_up_at', 'Follow up on', '', ['type' => 'datetime-local']) ?>
        </div>
        <?= Form::textarea('message', 'Notes from the enquiry', '', ['rows' => 4]) ?>
    </div>
    <div class="card-foot form-actions"><a class="btn btn-ghost" href="/admin/leads">Cancel</a><button class="btn btn-primary" type="submit">Add lead</button></div>
</form>
