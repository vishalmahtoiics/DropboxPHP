<?php
/**
 * One generated editor control for a block field descriptor.
 *
 * @var array $field  [name, type, label, default, options]
 * @var string $base  input name prefix, e.g. content or content[items][0]
 * @var mixed $value
 * @var bool $template rendering inside a repeater <template> (uses data-name)
 */
use App\Cms\Icons;
use App\Http\Form;
use App\Services\Options;

[$fname, $type, $label] = $field;
$o = $field[4] ?? [];
$template ??= false;
$name = $base . '[' . $fname . ']';
// Inside repeaters the JS renumbers names from data-name.
$nameAttr = $template ? 'data-name="' . e($fname) . '"' : 'name="' . e($name) . '"';
$show = isset($o['showWhen']) ? ' data-show-when="' . e(json_encode($o['showWhen'])) . '"' : '';
$help = isset($o['help']) ? '<span class="help">' . e($o['help']) . '</span>' : '';
$fieldName = ' data-field-name="' . e($fname) . '"';

$selectOptions = match ($type) {
    'select' => $o['choices'] ?? [],
    'form' => Options::forms(),
    'magnet' => Options::magnets(),
    'category' => Options::productCategories(),
    'brand' => Options::brands(),
    'blogcategory' => Options::blogCategories(),
    default => null,
};
?>
<?php if ($type === 'repeater'): ?>
    <?php $sub = $o['fields'] ?? []; $items = is_array($value) ? $value : []; ?>
    <div class="field"<?= $show ?>>
        <span class="label"><?= e($label) ?></span>
        <div data-repeater="<?= e($name) ?>" data-max="<?= (int) ($o['limit'] ?? 50) ?>">
            <div data-repeater-items>
                <?php foreach ($items as $i => $item): ?>
                    <div class="repeater-item" data-repeater-item data-field-scope>
                        <div class="repeater-item-head">
                            <strong><span data-item-index>#<?= $i + 1 ?></span> <?= e(str_limit((string) ($item['title'] ?? $item['label'] ?? $item['question'] ?? $item['name'] ?? $item['value'] ?? ''), 50)) ?></strong>
                            <span class="row" style="gap:.2rem">
                                <button class="btn btn-ghost btn-xs" type="button" data-repeater-action="up" aria-label="Move up"><?= Icons::svg('chevron-up', 'icon', 14) ?></button>
                                <button class="btn btn-ghost btn-xs" type="button" data-repeater-action="down" aria-label="Move down"><?= Icons::svg('chevron-down', 'icon', 14) ?></button>
                                <button class="btn btn-ghost btn-xs" type="button" data-repeater-action="duplicate" aria-label="Duplicate"><?= Icons::svg('copy', 'icon', 14) ?></button>
                                <button class="btn btn-danger-ghost btn-xs" type="button" data-repeater-action="remove" aria-label="Remove"><?= Icons::svg('trash', 'icon', 14) ?></button>
                            </span>
                        </div>
                        <div class="grid grid-2">
                            <?php foreach ($sub as $sf): ?>
                                <div class="<?= in_array($sf[1], ['textarea', 'richtext', 'icon', 'list'], true) ? 'span-2' : '' ?>"><?= App\Core\View::partial('admin/partials/block-field', ['field' => $sf, 'base' => $name . '[' . $i . ']', 'value' => $item[$sf[0]] ?? null, 'template' => true]) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <template>
                <div class="repeater-item" data-repeater-item data-field-scope>
                    <div class="repeater-item-head">
                        <strong><span data-item-index>#</span> New item</strong>
                        <span class="row" style="gap:.2rem">
                            <button class="btn btn-ghost btn-xs" type="button" data-repeater-action="up" aria-label="Move up"><?= Icons::svg('chevron-up', 'icon', 14) ?></button>
                            <button class="btn btn-ghost btn-xs" type="button" data-repeater-action="down" aria-label="Move down"><?= Icons::svg('chevron-down', 'icon', 14) ?></button>
                            <button class="btn btn-danger-ghost btn-xs" type="button" data-repeater-action="remove" aria-label="Remove"><?= Icons::svg('trash', 'icon', 14) ?></button>
                        </span>
                    </div>
                    <div class="grid grid-2">
                        <?php foreach ($sub as $sf): ?>
                            <div class="<?= in_array($sf[1], ['textarea', 'richtext', 'icon', 'list'], true) ? 'span-2' : '' ?>"><?= App\Core\View::partial('admin/partials/block-field', ['field' => $sf, 'base' => $name . '[__i__]', 'value' => $sf[3] ?? null, 'template' => true]) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </template>
            <button class="btn btn-secondary btn-sm" type="button" data-repeater-add><?= Icons::svg('plus', 'icon', 14) ?> Add <?= e(strtolower(rtrim($label, 's'))) ?></button>
        </div>
        <?= $help ?>
    </div>
<?php elseif ($type === 'richtext'): ?>
    <div<?= $show ?>>
        <?php if ($template): ?>
            <div class="field"><span class="label"><?= e($label) ?></span>
                <div data-rte><div class="rte-toolbar"><button type="button" data-cmd="bold"><b>B</b></button><button type="button" data-cmd="italic"><i>I</i></button><button type="button" data-cmd="insertUnorderedList">•</button><button type="button" data-cmd="createLink"><?= Icons::svg('link', 'icon', 14) ?></button></div>
                <div class="rte" contenteditable="true" style="min-height:90px"></div><textarea data-name="<?= e($fname) ?>" hidden><?= e((string) $value) ?></textarea></div>
            </div>
        <?php else: ?>
            <?= Form::richtext($name, $label, (string) $value, $o) ?>
        <?php endif; ?>
    </div>
<?php elseif ($type === 'bool'): ?>
    <div class="field"<?= $show ?>><label class="switch"><input type="checkbox" <?= $nameAttr ?> value="1"<?= $value ? ' checked' : '' ?><?= $fieldName ?>><span><?= e($label) ?></span></label><?= $help ?></div>
<?php elseif ($type === 'icon'): ?>
    <div class="field" data-icon-field<?= $show ?>>
        <span class="label"><?= e($label) ?></span>
        <input type="hidden" <?= $nameAttr ?> value="<?= e((string) $value) ?>">
        <div class="icon-picker" style="max-height:132px">
            <button type="button" data-icon-choice=""<?= $value === '' ? ' class="selected"' : '' ?> title="None">—</button>
            <?php foreach (App\Cms\Icons::names() as $icon): ?><button type="button" data-icon-choice="<?= e($icon) ?>" title="<?= e($icon) ?>"<?= $icon === $value ? ' class="selected"' : '' ?>><?= Icons::svg($icon, 'icon', 18) ?></button><?php endforeach; ?>
        </div>
    </div>
<?php elseif ($type === 'image'): ?>
    <label class="field"<?= $show ?>><span class="label"><?= e($label) ?></span>
        <span class="media-input"><img class="thumb" src="<?= e((string) $value) ?>" alt=""<?= $value ? '' : ' hidden' ?>><input class="input" type="text" <?= $nameAttr ?> value="<?= e((string) $value) ?>" placeholder="Choose from the media library"><button class="btn btn-secondary btn-sm" type="button" data-media-pick="IMAGE"><?= Icons::svg('image', 'icon', 14) ?> Choose</button><button class="btn btn-ghost btn-sm" type="button" data-media-clear aria-label="Clear"><?= Icons::svg('x', 'icon', 14) ?></button></span><?= $help ?>
    </label>
<?php elseif ($selectOptions !== null): ?>
    <label class="field"<?= $show ?>><span class="label"><?= e($label) ?></span>
        <select class="select" <?= $nameAttr ?><?= $fieldName ?>>
            <?php if ($type !== 'select'): ?><option value="">— None —</option><?php endif; ?>
            <?php foreach ($selectOptions as $k => $v): ?><option value="<?= e((string) $k) ?>"<?= (string) $k === (string) $value ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select><?= $help ?>
        <?php if ($type === 'form' && !$selectOptions): ?><span class="help">No forms yet — create one in Forms.</span><?php endif; ?>
    </label>
<?php elseif ($type === 'products'): ?>
    <?php $all = Options::products(); $chosen = array_map('intval', (array) $value); ?>
    <fieldset class="field"<?= $show ?>><legend class="label"><?= e($label) ?></legend>
        <input type="hidden" <?= $template ? 'data-name="' . e($fname) . '"' : 'name="' . e($name) . '"' ?> value="<?= e(implode(',', $chosen)) ?>" data-products-value>
        <div class="choices" style="display:grid;gap:.35rem" data-products-picker>
            <?php foreach ($chosen as $pid): if (!isset($all[$pid])) continue; ?><label class="check"><input type="checkbox" value="<?= $pid ?>" checked> <?= e($all[$pid]) ?></label><?php endforeach; ?>
            <?php foreach ($all as $pid => $pname): if (in_array((int) $pid, $chosen, true)) continue; ?><label class="check"><input type="checkbox" value="<?= (int) $pid ?>"> <?= e($pname) ?></label><?php endforeach; ?>
        </div>
        <span class="help">Ticked products appear in the order they were ticked.</span>
    </fieldset>
<?php elseif ($type === 'textarea' || $type === 'list'): ?>
    <label class="field"<?= $show ?>><span class="label"><?= e($label) ?></span>
        <textarea class="textarea" <?= $nameAttr ?> rows="<?= $type === 'list' ? 4 : 3 ?>"<?= isset($o['max']) && $type === 'textarea' ? ' maxlength="' . (int) $o['max'] . '"' : '' ?>><?= e($type === 'list' ? implode("\n", (array) $value) : (string) $value) ?></textarea><?= $help ?><?php if ($type === 'list' && !$help): ?><span class="help">One item per line.</span><?php endif; ?>
    </label>
<?php elseif ($type === 'color'): ?>
    <label class="field"<?= $show ?>><span class="label"><?= e($label) ?></span>
        <span class="color-field"><input type="color" value="<?= e(preg_match('/^#[0-9a-f]{6}$/i', (string) $value) ? $value : '#000000') ?>"><input class="input" type="text" <?= $nameAttr ?> value="<?= e((string) $value) ?>" placeholder="#RRGGBB"></span>
    </label>
<?php else: ?>
    <label class="field"<?= $show ?>><span class="label"><?= e($label) ?></span>
        <input class="input" type="<?= $type === 'number' ? 'number' : 'text' ?>" <?= $nameAttr ?> value="<?= e(is_scalar($value) ? (string) $value : '') ?>"<?= $fieldName ?><?= $type === 'number' && isset($o['min']) ? ' min="' . e($o['min']) . '"' : '' ?><?= $type === 'number' && isset($o['max']) ? ' max="' . e($o['max']) . '"' : '' ?><?= $type !== 'number' && isset($o['max']) ? ' maxlength="' . (int) $o['max'] . '"' : '' ?><?= $type === 'url' ? ' placeholder="/page, https://…, #popup-ID"' : '' ?><?= $type === 'length' ? ' placeholder="e.g. 40px, 2rem, 80%"' : '' ?>>
        <?= $help ?>
    </label>
<?php endif; ?>
