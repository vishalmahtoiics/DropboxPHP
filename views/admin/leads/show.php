<?php
use App\Cms\Icons;
use App\Http\Form;
use App\Services\Leads;

$l = $lead;
$canEdit = can('leads.edit');
$tlIcon = ['CREATED' => 'sparkles', 'STATUS_CHANGED' => 'refresh', 'ASSIGNED' => 'user', 'NOTE' => 'message', 'NOTE_ADDED' => 'message', 'EMAIL_SENT' => 'mail', 'CALL_LOGGED' => 'phone', 'MEETING_LOGGED' => 'calendar', 'FOLLOW_UP_SET' => 'clock', 'UPDATED' => 'edit', 'CONVERTED' => 'award'];
$actions = '';
if ($canEdit && can('customers.create') && !$l['customer_id']) {
    $actions .= Form::action('/admin/leads/' . $l['id'] . '/convert', 'Convert to customer', 'btn btn-primary', 'Mark this lead as won and create (or link) a customer?', 'award');
}
if (can('leads.delete')) {
    $actions .= ' ' . Form::action('/admin/leads/' . $l['id'] . '/delete', 'Delete', 'btn btn-danger-ghost', 'Move this lead to the recycle bin?', 'trash');
}
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => $l['name'], 'crumbs' => [['Leads', '/admin/leads'], ['#' . $l['id'], null]], 'sub' => trim(($l['company'] ? $l['company'] . ' · ' : '') . 'Received ' . format_datetime($l['created_at'])), 'actions' => $actions]) ?>
<div class="row mb-2"><?= Form::badge($l['status']) ?> <?= Form::badge($l['priority'], ucfirst(strtolower($l['priority'])) . ' priority') ?>
    <?php if ($l['customer_id']): ?><a class="badge green" href="/admin/customers/<?= (int) $l['customer_id'] ?>">Customer: <?= e($l['customer_name']) ?></a><?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="mailto:<?= e($l['email']) ?>"><?= Icons::svg('mail', 'icon', 14) ?> <?= e($l['email']) ?></a>
    <?php if ($l['phone']): ?><a class="btn btn-ghost btn-sm" href="tel:<?= e(preg_replace('/[^\d+]/', '', $l['phone'])) ?>"><?= Icons::svg('phone', 'icon', 14) ?> <?= e($l['phone']) ?></a>
    <a class="btn btn-ghost btn-sm" href="https://wa.me/<?= e(preg_replace('/\D/', '', $l['phone'])) ?>" target="_blank" rel="noopener"><?= Icons::svg('whatsapp', 'icon', 14) ?> WhatsApp</a><?php endif; ?>
</div>
<div class="grid grid-3">
    <div class="span-2">
        <form class="card" method="post" action="/admin/leads/<?= (int) $l['id'] ?>">
            <div class="card-head"><h2>Details</h2></div>
            <div class="card-body">
                <?= csrf_field() ?>
                <div class="grid grid-3">
                    <?= Form::select('status', 'Status', Leads::STATUSES, $l['status'], ['data' => ['field-name' => 'status']]) ?>
                    <?= Form::select('priority', 'Priority', Leads::PRIORITIES, $l['priority']) ?>
                    <?php if (can('leads.assign')): ?><?= Form::select('assigned_to_id', 'Owner', $staff, (string) $l['assigned_to_id'], ['empty' => 'Unassigned']) ?><?php else: ?><input type="hidden" name="assigned_to_id" value="<?= e($l['assigned_to_id']) ?>"><?php endif; ?>
                    <?= Form::input('name', 'Name', $l['name'], ['required' => true]) ?>
                    <?= Form::input('email', 'Email', $l['email'], ['required' => true, 'type' => 'email']) ?>
                    <?= Form::input('phone', 'Phone', $l['phone']) ?>
                    <?= Form::input('company', 'Company', $l['company']) ?>
                    <?= Form::input('job_title', 'Job title', $l['job_title']) ?>
                    <?= Form::select('product_id', 'Product', $products, (string) $l['product_id'], ['empty' => '—']) ?>
                    <?= Form::input('value', 'Deal value', $l['value'], ['inputmode' => 'decimal', 'prefix' => $adminCountry['currency_symbol']]) ?>
                    <?= Form::input('follow_up_at', 'Follow up on', $l['follow_up_at'] ? date('Y-m-d\TH:i', strtotime($l['follow_up_at'])) : '', ['type' => 'datetime-local']) ?>
                    <?= Form::input('source', 'Source', $l['source']) ?>
                </div>
                <input type="hidden" name="campaign" value="<?= e($l['campaign']) ?>">
                <?= Form::textarea('lost_reason', 'Why was it lost?', $l['lost_reason'], ['rows' => 2, 'showWhen' => ['status' => ['LOST']]]) ?>
                <?= Form::textarea('message', 'Message', $l['message'], ['rows' => 4]) ?>
            </div>
            <?php if ($canEdit): ?><div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save</button></div><?php endif; ?>
        </form>

        <div class="card" id="timeline">
            <div class="card-head"><h2>Timeline</h2></div>
            <div class="card-body">
                <?php if ($canEdit): ?>
                <div class="grid grid-2 mb-2">
                    <form method="post" action="/admin/leads/<?= (int) $l['id'] ?>/note" class="stack"><?= csrf_field() ?>
                        <textarea class="textarea" name="body" rows="3" placeholder="Add a note…" required maxlength="5000"></textarea>
                        <button class="btn btn-secondary btn-sm" type="submit">Add note</button></form>
                    <form method="post" action="/admin/leads/<?= (int) $l['id'] ?>/activity" class="stack"><?= csrf_field() ?>
                        <select class="select" name="type"><?php foreach (Leads::ACTIVITY_TYPES as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select>
                        <input class="input" name="summary" placeholder="What happened? (optional)" maxlength="2000">
                        <button class="btn btn-secondary btn-sm" type="submit">Log activity</button></form>
                </div>
                <?php endif; ?>
                <ul class="timeline">
                    <?php foreach ($timeline as $t): ?>
                        <li><div class="row-between"><strong class="small"><?= Icons::svg($tlIcon[$t['type']] ?? 'activity', 'icon', 14) ?> <?= e($t['kind'] === 'note' ? 'Note' : ucfirst(strtolower(str_replace('_', ' ', $t['type'])))) ?></strong><span class="small muted" title="<?= e($t['created_at']) ?>"><?= e(time_ago($t['created_at'])) ?><?= $t['actor'] ? ' · ' . e($t['actor']) : '' ?></span></div>
                            <div class="<?= $t['kind'] === 'note' ? '' : 'small muted' ?>" style="white-space:pre-wrap"><?= e($t['body']) ?></div></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <div>
        <div class="card"><div class="card-head"><h2>Attribution</h2></div><div class="card-body">
            <dl class="dl small" style="grid-template-columns:110px 1fr">
                <dt>Form</dt><dd><?= e($l['form_name'] ?? '—') ?></dd>
                <dt>Placement</dt><dd><?= e($l['cta_location'] ?: '—') ?><?= $l['cta_label'] ? ' (' . e($l['cta_label']) . ')' : '' ?></dd>
                <dt>Landing page</dt><dd><?= e($l['page_title'] ?? '') ?><?php if ($l['landing_url']): ?><div class="muted" style="word-break:break-all"><?= e($l['landing_url']) ?></div><?php endif; ?></dd>
                <dt>Last touch</dt><dd><?= e(implode(' / ', array_filter([$l['utm_source'], $l['utm_medium'], $l['utm_campaign']])) ?: '—') ?><?php if ($l['utm_term'] || $l['utm_content']): ?><div class="muted"><?= e(trim($l['utm_term'] . ' ' . $l['utm_content'])) ?></div><?php endif; ?></dd>
                <dt>First touch</dt><dd><?= e(implode(' / ', array_filter([$l['first_utm_source'], $l['first_utm_medium'], $l['first_utm_campaign']])) ?: '—') ?><?php if ($l['first_touch_at']): ?><div class="muted"><?= e(format_datetime($l['first_touch_at'])) ?></div><?php endif; ?></dd>
                <dt>Referrer</dt><dd style="word-break:break-all"><?= e($l['referrer'] ?: '—') ?></dd>
                <dt>Marketing</dt><dd><?= $l['marketing_consent'] ? 'Opted in' : 'Not opted in' ?></dd>
            </dl>
        </div></div>
        <?php if ($consents): ?>
        <div class="card"><div class="card-head"><h2>Consent records</h2><?php if (can('leads.manageConsent')): ?><?= Form::action('/admin/leads/' . $l['id'] . '/consent', 'Record withdrawal', 'btn btn-ghost btn-xs', 'Record that this person withdrew their marketing consent?') ?><?php endif; ?></div><div class="card-body small">
            <?php foreach ($consents as $c): ?>
                <p><strong><?= e(format_datetime($c['created_at'])) ?></strong> — enquiry <?= $c['enquiry_consent'] ? '✓' : '✗' ?>, marketing <?= $c['marketing_consent'] ? '✓' : '✗' ?>, terms <?= $c['terms_accepted'] ? '✓' : '✗' ?> · <?= e(strtolower(str_replace('_', ' ', $c['lawful_basis']))) ?><?php if ($c['notice_text']): ?><br><span class="muted">“<?= e(strip_tags($c['notice_text'])) ?>”</span><?php endif; ?><?php if ($c['withdrawn_at']): ?><br><span class="badge red">Withdrawn <?= e(format_date($c['withdrawn_at'])) ?></span><?php endif; ?></p>
            <?php endforeach; ?>
        </div></div>
        <?php endif; ?>
        <?php if ($submissions): ?>
        <div class="card"><div class="card-head"><h2>Submitted data</h2></div><div class="card-body small">
            <?php foreach ($submissions as $s): $data = json_decode((string) $s['data'], true) ?: []; $labels = json_decode((string) $s['field_labels'], true) ?: []; ?>
                <p class="muted"><?= e($s['form_name']) ?> · <?= e(format_datetime($s['created_at'])) ?></p>
                <dl class="dl" style="grid-template-columns:110px 1fr"><?php foreach ($data as $k => $v): ?><dt><?= e($labels[$k] ?? $k) ?></dt><dd><?= e(is_array($v) ? implode(', ', $v) : (string) $v) ?></dd><?php endforeach; ?></dl>
            <?php endforeach; ?>
        </div></div>
        <?php endif; ?>
        <?php if ($others): ?>
        <div class="card"><div class="card-head"><h2>Other enquiries</h2></div><div class="card-body small">
            <?php foreach ($others as $o): ?><p><a href="/admin/leads/<?= (int) $o['id'] ?>">#<?= (int) $o['id'] ?> <?= e($o['name']) ?></a> <?= Form::badge($o['status']) ?> <span class="muted"><?= e(format_date($o['created_at'])) ?></span></p><?php endforeach; ?>
        </div></div>
        <?php endif; ?>
    </div>
</div>
