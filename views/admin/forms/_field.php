<?php
/**
 * One field in the form builder (also the repeater template): a compact row
 * (label, type, badges, actions) that opens to its settings. New fields open.
 */
use App\Cms\Icons;

$f ??= [];
$types ??= [];
$val = static fn (string $k, $d = '') => e($f[$k] ?? $d);
$opts = is_array($f['options'] ?? null) ? implode("\n", $f['options']) : '';
$isNew = !$f;
$type = (string) ($f['type'] ?? 'TEXT');
// "Advanced" starts open when any of its settings is filled in.
$advanced = (bool) array_filter(['default_value', 'help_text', 'min_length', 'max_length', 'pattern'], static fn ($k) => (string) ($f[$k] ?? '') !== '');
?>
<div class="repeater-item field-row<?= $isNew ? ' is-open' : '' ?><?= ($f['is_enabled'] ?? 1) ? '' : ' is-off' ?>" data-repeater-item data-field-scope data-field-row>
    <div class="field-row-head">
        <button class="field-row-toggle" type="button" data-field-toggle aria-expanded="<?= $isNew ? 'true' : 'false' ?>">
            <?= Icons::svg('chevron-right', 'icon field-row-caret', 16) ?>
            <span class="field-row-index" data-item-index>#</span>
            <span class="field-row-title" data-field-title><?= e($f['label'] ?? '') ?: 'New field' ?></span>
            <span class="badge" data-field-type><?= e($types[$type] ?? $type) ?></span>
            <span class="badge red" data-badge="required"<?= !empty($f['is_required']) ? '' : ' hidden' ?>>Required</span>
            <span class="badge" data-badge="half"<?= ($f['width'] ?? 'full') === 'half' ? '' : ' hidden' ?>>Half width</span>
            <span class="badge" data-badge="off"<?= ($f['is_enabled'] ?? 1) ? ' hidden' : '' ?>>Off</span>
        </button>
        <span class="field-row-actions">
            <button class="btn btn-ghost btn-xs" type="button" data-repeater-action="up" aria-label="Move up" title="Move up"><?= Icons::svg('chevron-up', 'icon', 14) ?></button>
            <button class="btn btn-ghost btn-xs" type="button" data-repeater-action="down" aria-label="Move down" title="Move down"><?= Icons::svg('chevron-down', 'icon', 14) ?></button>
            <button class="btn btn-ghost btn-xs" type="button" data-repeater-action="duplicate" aria-label="Duplicate" title="Duplicate"><?= Icons::svg('copy', 'icon', 14) ?></button>
            <button class="btn btn-danger-ghost btn-xs" type="button" data-repeater-action="remove" aria-label="Remove" title="Remove"><?= Icons::svg('trash', 'icon', 14) ?></button>
        </span>
    </div>
    <div class="field-row-body">
        <div class="grid grid-3">
            <label class="field"><span class="label">Type</span><select class="select" data-name="type" data-field-name="type"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span class="label">Label</span><input class="input" data-name="label" value="<?= $val('label') ?>" maxlength="200"></label>
            <label class="field"><span class="label">Placeholder</span><input class="input" data-name="placeholder" value="<?= $val('placeholder') ?>"></label>
            <label class="field span-3" data-show-when='{"type":["SELECT","RADIO","MULTISELECT"]}'><span class="label">Options (one per line)</span><textarea class="textarea" data-name="options" rows="3"><?= e($opts) ?></textarea></label>
        </div>
        <div class="field-row-switches">
            <label class="switch"><input type="checkbox" data-name="is_required" value="1"<?= !empty($f['is_required']) ? ' checked' : '' ?>><span>Required</span></label>
            <input type="hidden" data-name="enabled_present" value="1"><label class="switch"><input type="checkbox" data-name="is_enabled" value="1"<?= ($f['is_enabled'] ?? 1) ? ' checked' : '' ?>><span>Enabled</span></label>
            <input type="hidden" data-name="show_label_present" value="1"><label class="switch"><input type="checkbox" data-name="show_label" value="1"<?= ($f['show_label'] ?? 1) ? ' checked' : '' ?>><span>Show label</span></label>
            <label class="field field-inline"><span class="label">Width</span><select class="select input-sm" data-name="width"><option value="full"<?= ($f['width'] ?? 'full') === 'full' ? ' selected' : '' ?>>Full</option><option value="half"<?= ($f['width'] ?? '') === 'half' ? ' selected' : '' ?>>Half</option></select></label>
        </div>
        <details class="field-row-more"<?= $advanced ? ' open' : '' ?>>
            <summary>Advanced: key, default, help text, validation</summary>
            <div class="grid grid-3">
                <label class="field"><span class="label">Key</span><input class="input" data-name="name" value="<?= $val('name') ?>" placeholder="auto" pattern="[a-z][a-z0-9_]*" maxlength="60"></label>
                <label class="field"><span class="label">Default value</span><input class="input" data-name="default_value" value="<?= $val('default_value') ?>"></label>
                <label class="field"><span class="label">Help text</span><input class="input" data-name="help_text" value="<?= $val('help_text') ?>"></label>
                <label class="field"><span class="label">Min length</span><input class="input" type="number" min="0" data-name="min_length" value="<?= $val('min_length') ?>"></label>
                <label class="field"><span class="label">Max length</span><input class="input" type="number" min="1" data-name="max_length" value="<?= $val('max_length') ?>"></label>
                <label class="field"><span class="label">Pattern (regex)</span><input class="input" data-name="pattern" value="<?= $val('pattern') ?>" placeholder="optional"></label>
            </div>
        </details>
    </div>
</div>
