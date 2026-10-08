<?php
use App\Http\Form;
use App\Http\Controllers\Admin\PopupController;

$v = $p ?? [];
$patterns = implode("\n", json_decode((string) ($v['url_patterns'] ?? '[]'), true) ?: []);
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => $p ? $p['name'] : 'New popup', 'crumbs' => [['Popups', '/admin/popups'], [$p ? 'Edit' : 'New', null]], 'sub' => $p ? 'Open from any button with the link #popup-' . $p['id'] : '']) ?>
<form method="post" action="<?= $p ? '/admin/popups/' . (int) $p['id'] : '/admin/popups' ?>" data-field-scope data-guard>
    <?= csrf_field() ?>
    <div class="grid grid-2">
        <div class="card"><div class="card-head"><h2>Content</h2></div><div class="card-body stack">
            <?= Form::input('name', 'Name (admin only)', $v['name'] ?? '', ['required' => true]) ?>
            <?= Form::select('type', 'Type', PopupController::TYPES, $v['type'] ?? 'LEAD_FORM') ?>
            <?= Form::input('heading', 'Heading', $v['heading'] ?? '') ?>
            <?= Form::textarea('body', 'Text', $v['body'] ?? '', ['rows' => 3]) ?>
            <?= Form::media('image', 'Image', $imageUrl, ['kind' => 'IMAGE']) ?>
            <?= Form::select('form_id', 'Form', $forms, (string) ($v['form_id'] ?? ''), ['empty' => 'No form']) ?>
            <?= Form::select('lead_magnet_id', 'Lead magnet', $magnets, (string) ($v['lead_magnet_id'] ?? ''), ['empty' => 'None']) ?>
            <div class="grid grid-2"><?= Form::input('cta_label', 'Button label (no form)', $v['cta_label'] ?? '') ?><?= Form::input('cta_url', 'Button link', $v['cta_url'] ?? '') ?></div>
        </div></div>
        <div class="card"><div class="card-head"><h2>When and where</h2></div><div class="card-body stack">
            <?= Form::select('trigger', 'Trigger', PopupController::TRIGGERS, $v['trigger'] ?? 'DELAY', ['data' => ['field-name' => 'trigger']]) ?>
            <?= Form::input('delay_seconds', 'Delay (seconds)', $v['delay_seconds'] ?? 5, ['type' => 'number', 'min' => 0, 'showWhen' => ['trigger' => ['DELAY']]]) ?>
            <?= Form::input('scroll_percent', 'Scroll depth (%)', $v['scroll_percent'] ?? 50, ['type' => 'number', 'min' => 0, 'max' => 100, 'showWhen' => ['trigger' => ['SCROLL']]]) ?>
            <div data-show-when='{"trigger":["DELAY","IMMEDIATE","SCROLL","EXIT_INTENT"]}'>
                <?= Form::textarea('url_patterns', 'Show on these pages', $patterns, ['rows' => 3, 'help' => 'One per line: / (homepage), /pricing, /blog/* (a section). Blank = every page. Paths are inside the market, without its prefix.']) ?>
                <?= Form::select('device', 'Devices', ['ALL' => 'All', 'DESKTOP' => 'Desktop only', 'MOBILE' => 'Mobile only'], $v['device'] ?? 'ALL') ?>
                <?= Form::input('frequency_days', 'Show again after (days)', $v['frequency_days'] ?? 7, ['type' => 'number', 'min' => 0]) ?>
            </div>
            <?= Form::select('country_id', 'Market', $markets, (string) ($v['country_id'] ?? ''), ['empty' => 'All markets']) ?>
            <div class="grid grid-2">
                <?= Form::input('starts_at', 'Starts', !empty($v['starts_at']) ? date('Y-m-d\TH:i', strtotime((string) $v['starts_at'])) : '', ['type' => 'datetime-local']) ?>
                <?= Form::input('ends_at', 'Ends', !empty($v['ends_at']) ? date('Y-m-d\TH:i', strtotime((string) $v['ends_at'])) : '', ['type' => 'datetime-local']) ?>
            </div>
            <?= Form::toggle('is_active', 'Active', $v['is_active'] ?? 0) ?>
        </div></div>
    </div>
    <div class="sticky-actions"><a class="btn btn-ghost" href="/admin/popups">Cancel</a><button class="btn btn-primary" type="submit">Save popup</button></div>
</form>
