<?php
use App\Cms\Icons;
use App\Core\View;
use App\Http\Form;

$v = $form ?? [];
$action = $form ? '/admin/forms/' . $form['id'] : '/admin/forms';
?>
<?= View::partial('admin/partials/page-head', ['heading' => $form ? $form['name'] : 'New form', 'crumbs' => [['Forms', '/admin/forms'], [$form ? 'Edit' : 'New', null]],
    'actions' => $form ? '<a class="btn btn-secondary" href="/admin/forms/' . (int) $form['id'] . '/submissions">' . Icons::svg('inbox', 'icon', 16) . ' Submissions</a>' : '']) ?>
<form method="post" action="<?= e($action) ?>" data-guard>
    <?= csrf_field() ?>
    <div data-tabs="form">
        <div class="tabs" role="tablist">
            <button class="tab" type="button" role="tab" data-tab="fields">Fields</button>
            <button class="tab" type="button" role="tab" data-tab="behaviour">After submit</button>
            <button class="tab" type="button" role="tab" data-tab="privacy">Consent & spam</button>
            <button class="tab" type="button" role="tab" data-tab="settings">Settings</button>
        </div>
        <div class="tab-panel" id="fields">
            <div class="card"><div class="card-body">
                <?php if ($e = (errors()['fields'] ?? '')): ?><div class="alert alert-error"><span><?= e($e) ?></span></div><?php endif; ?>
                <div data-repeater="fields" data-max="40">
                    <div data-repeater-items>
                        <?php foreach ($fields as $f): ?><?= View::partial('admin/forms/_field', ['f' => $f, 'types' => $types]) ?><?php endforeach; ?>
                    </div>
                    <template><?= View::partial('admin/forms/_field', ['f' => [], 'types' => $types]) ?></template>
                    <button class="btn btn-secondary" type="button" data-repeater-add><?= Icons::svg('plus', 'icon', 16) ?> Add field</button>
                </div>
                <p class="help mt-2">Validation is rebuilt on the server from these definitions for every submission, so a tampered request cannot skip a required field or invent an option. Name, Email, Phone and Company fields fill the lead's own columns; everything else is added to the lead's message.</p>
            </div></div>
        </div>
        <div class="tab-panel card" id="behaviour" hidden><div class="card-body stack">
            <?= Form::input('submit_label', 'Button label', $v['submit_label'] ?? 'Submit', ['required' => true, 'maxlength' => 80]) ?>
            <?= Form::textarea('success_message', 'Success message', $v['success_message'] ?? 'Thank you. Our team will contact you shortly.', ['required' => true, 'rows' => 2]) ?>
            <?= Form::input('redirect_url', 'Redirect after submit', $v['redirect_url'] ?? '', ['help' => 'Optional thank-you page, e.g. /thank-you.']) ?>
            <?= Form::toggle('creates_lead', 'Create a lead in the CRM', $v['creates_lead'] ?? 1) ?>
            <?= Form::input('lead_source', 'Lead source', $v['lead_source'] ?? 'Website Form') ?>
            <?= Form::select('default_product_id', 'Default product', $products, (string) ($v['default_product_id'] ?? ''), ['empty' => 'None', 'help' => 'Used when the form is not opened from a product button.']) ?>
            <?= Form::input('notify_emails', 'Also email these addresses', $v['notify_emails'] ?? '', ['help' => 'Comma-separated. Sales recipients from Settings → Email are notified of every lead regardless.']) ?>
        </div></div>
        <div class="tab-panel card" id="privacy" hidden><div class="card-body stack">
            <?= Form::textarea('consent_text', 'Enquiry consent checkbox text', $v['consent_text'] ?? '', ['rows' => 2, 'help' => 'If set, visitors must tick it. Links are allowed. The exact text is stored with each consent record.']) ?>
            <?= Form::select('lawful_basis', 'Lawful basis', ['CONSENT' => 'Consent', 'CONTRACT' => 'Contract', 'LEGITIMATE_INTEREST' => 'Legitimate interest', 'LEGAL_OBLIGATION' => 'Legal obligation'], $v['lawful_basis'] ?? 'CONSENT') ?>
            <?= Form::toggle('offer_marketing_consent', 'Offer an optional marketing opt-in', $v['offer_marketing_consent'] ?? 0) ?>
            <?= Form::toggle('require_terms_acceptance', 'Require accepting the terms of service', $v['require_terms_acceptance'] ?? 0) ?>
            <?= Form::toggle('require_captcha', 'Ask a simple security question', $v['require_captcha'] ?? 0, ['help' => 'Every form already has a honeypot, a minimum fill time and per-address rate limiting.']) ?>
        </div></div>
        <div class="tab-panel card" id="settings" hidden><div class="card-body stack">
            <?= Form::input('name', 'Form name', $v['name'] ?? '', ['required' => true, 'maxlength' => 160, 'data' => ['slug-source' => 'form-slug']]) ?>
            <?= Form::input('slug', 'Key', $v['slug'] ?? '', ['id' => 'form-slug', 'help' => 'How sections and popups refer to this form.']) ?>
            <?= Form::textarea('description', 'Description (shown above the form in dialogs)', $v['description'] ?? '', ['rows' => 2]) ?>
            <?= Form::select('columns', 'Layout', ['2' => 'Two columns (half-width fields side by side)', '1' => 'Single column'], (string) ($design['columns'] ?? 2)) ?>
            <?= Form::select('country_id', 'Market', $markets, (string) ($v['country_id'] ?? ''), ['empty' => 'All markets']) ?>
            <?= Form::toggle('is_active', 'Active', $v['is_active'] ?? 1) ?>
        </div></div>
    </div>
    <div class="sticky-actions"><a class="btn btn-ghost" href="/admin/forms">Cancel</a><button class="btn btn-primary" type="submit"><?= $form ? 'Save form' : 'Create form' ?></button></div>
</form>
