<?php
use App\Http\Form;

$e = $edit ?? [];
$vendors = [
    ['ga4', 'Google Analytics 4', 'ga4_id', 'G-XXXXXXXXXX'],
    ['gtm', 'Google Tag Manager', 'gtm_id', 'GTM-XXXXXXX'],
    ['google_ads', 'Google Ads', 'google_ads_id', 'AW-123456789'],
    ['meta_pixel', 'Meta Pixel', 'meta_pixel_id', '123456789012345'],
    ['microsoft_uet', 'Microsoft Ads (UET)', 'microsoft_uet_id', '12345678'],
    ['hotjar', 'Hotjar', 'hotjar_id', '1234567'],
    ['linkedin', 'LinkedIn Insight Tag', 'linkedin_partner_id', '123456'],
    ['tiktok', 'TikTok Pixel', 'tiktok_pixel_id', 'C1ABCDEF2GHIJ3KLMNOP'],
];
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Tracking & tags', 'sub' => 'Tags load on the public site only — never in the admin.']) ?>
<div data-tabs="marketing">
    <div class="tabs" role="tablist"><button class="tab" type="button" role="tab" data-tab="vendors">Analytics & ads</button><button class="tab" type="button" role="tab" data-tab="scripts">Custom scripts</button></div>
    <div class="tab-panel" id="vendors">
        <form class="card" method="post" action="/admin/marketing"><div class="card-body">
            <?= csrf_field() ?>
            <p class="help mb-2">Enter only the ID. Each vendor's code comes from a fixed template, so this screen cannot be used to add arbitrary JavaScript.</p>
            <div class="table-wrap"><table class="table"><thead><tr><th>Vendor</th><th>ID</th><th>On</th></tr></thead><tbody>
                <?php foreach ($vendors as [$k, $label, $idKey, $ph]): ?>
                    <tr><td><strong><?= e($label) ?></strong></td><td><?= Form::input($idKey, '', $t[$idKey], ['placeholder' => $ph]) ?><?php if ($k === 'google_ads'): ?><?= Form::input('google_ads_label', '', $t['google_ads_label'], ['placeholder' => 'Conversion label (optional)']) ?><?php endif; ?></td>
                        <td><label class="switch"><input type="checkbox" name="<?= $k ?>_enabled" value="1"<?= $t[$k . '_enabled'] ? ' checked' : '' ?>><span class="sr-only">Enable <?= e($label) ?></span></label></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
            <hr><h3>Cookie consent</h3>
            <?= Form::toggle('consent_required', 'Ask for consent before loading any tag', $t['consent_required'], ['help' => 'Tags stay inert until the visitor clicks Accept. Recommended for GDPR / DPDP compliance.']) ?>
            <?= Form::textarea('consent_message', 'Banner message', $t['consent_message'], ['rows' => 2]) ?>
        </div><div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save</button></div></form>
    </div>
    <div class="tab-panel" id="scripts" hidden>
        <div class="alert alert-warning"><span>Custom scripts run on every public page with full access to the page. Only paste code from vendors you trust. Every change is recorded in the audit log, including the code.</span></div>
        <div class="builder">
            <div class="card">
                <?php if (!$scripts): ?><div class="empty"><p>No custom scripts.</p></div><?php else: ?>
                <div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Placement</th><th>Consent</th><th>Status</th><th></th></tr></thead><tbody>
                    <?php foreach ($scripts as $s): ?><tr><td><?= e($s['name']) ?></td><td class="small"><?= e(strtolower(str_replace('_', ' ', $s['placement']))) ?> · <?= e(strtolower($s['environment'])) ?></td><td><?= $s['requires_consent'] ? 'Required' : 'No' ?></td><td><?= $s['is_active'] ? Form::badge('ACTIVE', 'Live') : Form::badge('INACTIVE', 'Off') ?></td>
                        <td class="actions"><a class="btn btn-ghost btn-sm" href="?script=<?= (int) $s['id'] ?>#scripts">Edit</a><?= Form::action('/admin/marketing/scripts/' . $s['id'] . '/delete', 'Delete', 'btn btn-danger-ghost btn-sm', 'Delete this script?') ?></td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>
            </div>
            <form class="card" method="post" action="/admin/marketing/scripts"><div class="card-head"><h2><?= $e ? 'Edit script' : 'Add script' ?></h2></div><div class="card-body stack">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($e['id'] ?? '') ?>">
                <?= Form::input('name', 'Name', $e['name'] ?? '', ['required' => true]) ?>
                <?= Form::select('placement', 'Placement', ['HEAD' => 'In <head>', 'BODY_START' => 'Start of <body>', 'BODY_END' => 'End of <body>'], $e['placement'] ?? 'HEAD') ?>
                <?= Form::select('environment', 'Environment', ['ALL' => 'Everywhere', 'PRODUCTION' => 'Production only', 'DEVELOPMENT' => 'Development only'], $e['environment'] ?? 'PRODUCTION') ?>
                <?= Form::textarea('code', 'Code', $e['code'] ?? '', ['rows' => 8, 'code' => true, 'required' => true]) ?>
                <?= Form::toggle('requires_consent', 'Wait for cookie consent', $e['requires_consent'] ?? 1) ?>
                <?= Form::toggle('is_active', 'Active', $e['is_active'] ?? 0) ?>
            </div><div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save script</button></div></form>
        </div>
    </div>
</div>
