<?php use App\Http\Form; use App\Http\Controllers\Admin\CustomerController; $v = $c ?? []; ?>
<div class="grid grid-2">
    <?= Form::input('name', 'Contact name', $v['name'] ?? '', ['required' => true]) ?>
    <?= Form::input('company', 'Company', $v['company'] ?? '') ?>
    <?= Form::input('email', 'Email', $v['email'] ?? '', ['required' => true, 'type' => 'email']) ?>
    <?= Form::input('phone', 'Phone', $v['phone'] ?? '') ?>
    <?= Form::input('website', 'Website', $v['website'] ?? '') ?>
    <?= Form::input('gstin', 'GSTIN / tax number', $v['gstin'] ?? '') ?>
    <?= Form::select('status', 'Status', CustomerController::STATUSES, $v['status'] ?? 'ACTIVE') ?>
    <?= Form::select('assigned_to_id', 'Account owner', $staff, (string) ($v['assigned_to_id'] ?? ''), ['empty' => 'Nobody']) ?>
</div>
<?= Form::textarea('address', 'Address', $v['address'] ?? '', ['rows' => 2]) ?>
