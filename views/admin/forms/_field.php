<?php
/** One field row in the form builder (also used as the repeater template). */
use App\Cms\Icons;

$f ??= [];
$val = static fn (string $k, $d = '') => e($f[$k] ?? $d);
$opts = is_array($f['options'] ?? null) ? implode("\n", $f['options']) : '';
?>
<div class="repeater-item" data-repeater-item data-field-scope>
    <div class="repeater-item-head">
        <strong><span data-item-index>#</span> <?= e($f['label'] ?? 'New field') ?></strong>
        <span class="row" style="gap:.2rem">
            <button class="btn btn-ghost btn-xs" type="button" data-repeater-action="up" aria-label="Move up"><?= Icons::svg('chevron-up', 'icon', 14) ?></button>
            <button class="btn btn-ghost btn-xs" type="button" data-repeater-action="down" aria-label="Move down"><?= Icons::svg('chevron-down', 'icon', 14) ?></button>
            <button class="btn btn-ghost btn-xs" type="button" data-repeater-action="duplicate" aria-label="Duplicate"><?= Icons::svg('copy', 'icon', 14) ?></button>
            <button class="btn btn-danger-ghost btn-xs" type="button" data-repeater-action="remove" aria-label="Remove"><?= Icons::svg('trash', 'icon', 14) ?></button>
        </span>
    </div>
    <div class="grid grid-4">
        <label class="field"><span class="label">Type</span><select class="select" data-name="type" data-field-name="type"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"<?= ($f['type'] ?? 'TEXT') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label class="field span-2"><span class="label">Label</span><input class="input" data-name="label" value="<?= $val('label') ?>" maxlength="200"></label>
        <label class="field"><span class="label">Key</span><input class="input" data-name="name" value="<?= $val('name') ?>" placeholder="auto" pattern="[a-z][a-z0-9_]*" maxlength="60"></label>
        <label class="field"><span class="label">Placeholder</span><input class="input" data-name="placeholder" value="<?= $val('placeholder') ?>"></label>
        <label class="field"><span class="label">Default value</span><input class="input" data-name="default_value" value="<?= $val('default_value') ?>"></label>
        <label class="field span-2"><span class="label">Help text</span><input class="input" data-name="help_text" value="<?= $val('help_text') ?>"></label>
        <label class="field span-2" data-show-when='{"type":["SELECT","RADIO","MULTISELECT"]}'><span class="label">Options (one per line)</span><textarea class="textarea" data-name="options" rows="3"><?= e($opts) ?></textarea></label>
        <label class="field"><span class="label">Width</span><select class="select" data-name="width"><option value="full"<?= ($f['width'] ?? 'full') === 'full' ? ' selected' : '' ?>>Full</option><option value="half"<?= ($f['width'] ?? '') === 'half' ? ' selected' : '' ?>>Half</option></select></label>
        <label class="field"><span class="label">Min length</span><input class="input" type="number" min="0" data-name="min_length" value="<?= $val('min_length') ?>"></label>
        <label class="field"><span class="label">Max length</span><input class="input" type="number" min="1" data-name="max_length" value="<?= $val('max_length') ?>"></label>
        <label class="field"><span class="label">Pattern (regex)</span><input class="input" data-name="pattern" value="<?= $val('pattern') ?>" placeholder="optional"></label>
    </div>
    <div class="row">
        <label class="switch"><input type="checkbox" data-name="is_required" value="1"<?= !empty($f['is_required']) ? ' checked' : '' ?>><span>Required</span></label>
        <input type="hidden" data-name="enabled_present" value="1"><label class="switch"><input type="checkbox" data-name="is_enabled" value="1"<?= ($f['is_enabled'] ?? 1) ? ' checked' : '' ?>><span>Enabled</span></label>
        <input type="hidden" data-name="show_label_present" value="1"><label class="switch"><input type="checkbox" data-name="show_label" value="1"<?= ($f['show_label'] ?? 1) ? ' checked' : '' ?>><span>Show label</span></label>
    </div>
</div>
