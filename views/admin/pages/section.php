<?php
use App\Cms\Design;
use App\Cms\Icons;
use App\Core\View;
use App\Http\Form;

$d = $design;
$bpLabels = ['desktop' => 'Desktop', 'tablet' => 'Tablet (≤1024px)', 'mobile' => 'Mobile (≤640px)'];
$spacingLabels = ['padding_top' => 'Padding top', 'padding_bottom' => 'Padding bottom', 'margin_top' => 'Margin top', 'margin_bottom' => 'Margin bottom', 'min_height' => 'Min height', 'gap' => 'Gap between items', 'heading_size' => 'Heading size', 'text_size' => 'Text size'];
?>
<?= View::partial('admin/partials/page-head', [
    'heading' => $def['label'],
    'crumbs' => [['Pages', '/admin/pages'], [$p['title'], '/admin/pages/' . $p['id']], [$def['label'], null]],
    'sub' => $def['description'],
    'actions' => '<a class="btn btn-secondary" href="/preview/page/' . (int) $p['id'] . '#' . e($d['anchor']) . '" target="_blank">' . Icons::svg('eye', 'icon', 16) . ' Preview page</a>',
]) ?>
<form method="post" action="/admin/sections/<?= (int) $section['id'] ?>" data-guard>
    <?= csrf_field() ?>
    <div data-tabs="section">
        <div class="tabs" role="tablist">
            <button class="tab" role="tab" type="button" data-tab="content">Content</button>
            <button class="tab" role="tab" type="button" data-tab="design">Design</button>
            <button class="tab" role="tab" type="button" data-tab="advanced">Advanced</button>
        </div>
        <div class="tab-panel card" id="content"><div class="card-body" data-field-scope>
            <div class="grid grid-2">
                <?php foreach ($def['fields'] as $field): ?>
                    <?php $wide = in_array($field[1], ['repeater', 'richtext', 'textarea', 'list', 'products'], true); ?>
                    <div class="<?= $wide ? 'span-2' : '' ?>"><?= View::partial('admin/partials/block-field', ['field' => $field, 'base' => 'content', 'value' => $content[$field[0]] ?? null]) ?></div>
                <?php endforeach; ?>
            </div>
        </div></div>

        <div class="tab-panel" id="design" hidden>
            <div class="card"><div class="card-head"><h2>Appearance</h2></div><div class="card-body">
                <div class="grid grid-3">
                    <?= Form::select('design[preset]', 'Background preset', Design::PRESETS, $d['preset']) ?>
                    <?= Form::select('design[width]', 'Content width', Design::WIDTHS, $d['width']) ?>
                    <?= Form::select('design[text_align]', 'Text alignment', ['' => 'Block default', 'left' => 'Left', 'center' => 'Centre', 'right' => 'Right'], $d['text_align']) ?>
                </div>
            </div></div>
            <div class="card" data-field-scope><div class="card-head"><h2>Custom background</h2></div><div class="card-body">
                <div class="grid grid-3">
                    <?= Form::select('design[bg_type]', 'Type', ['none' => 'Use the preset', 'color' => 'Solid colour', 'gradient' => 'Gradient', 'image' => 'Image'], $d['bg_type'], ['data' => ['field-name' => 'bg_type']]) ?>
                    <?= Form::color('design[bg_color]', 'Colour', $d['bg_color'], ['showWhen' => ['bg_type' => ['color']]]) ?>
                    <?= Form::color('design[bg_from]', 'Gradient from', $d['bg_from'], ['showWhen' => ['bg_type' => ['gradient']]]) ?>
                    <?= Form::color('design[bg_to]', 'Gradient to', $d['bg_to'], ['showWhen' => ['bg_type' => ['gradient']]]) ?>
                    <?= Form::input('design[bg_angle]', 'Angle (°)', $d['bg_angle'], ['type' => 'number', 'min' => 0, 'max' => 360, 'showWhen' => ['bg_type' => ['gradient']]]) ?>
                </div>
                <div data-show-when='{"bg_type":["image"]}'>
                    <?= Form::media('design[bg_image]', 'Background image', $d['bg_image'], ['kind' => 'IMAGE']) ?>
                    <div class="grid grid-3">
                        <?= Form::select('design[bg_size]', 'Size', ['cover' => 'Cover', 'contain' => 'Contain', 'auto' => 'Original'], $d['bg_size']) ?>
                        <?= Form::select('design[bg_position]', 'Position', ['center' => 'Centre', 'top' => 'Top', 'bottom' => 'Bottom', 'left' => 'Left', 'right' => 'Right'], $d['bg_position']) ?>
                        <?= Form::toggle('design[bg_fixed]', 'Fixed (parallax)', $d['bg_fixed']) ?>
                    </div>
                </div>
                <div class="grid grid-3">
                    <?= Form::color('design[overlay_color]', 'Overlay colour', $d['overlay_color']) ?>
                    <?= Form::input('design[overlay_opacity]', 'Overlay opacity (%)', $d['overlay_opacity'], ['type' => 'number', 'min' => 0, 'max' => 100]) ?>
                </div>
            </div></div>
            <div class="card"><div class="card-head"><h2>Colours</h2></div><div class="card-body">
                <div class="grid grid-4">
                    <?= Form::color('design[text_color]', 'Text', $d['text_color']) ?>
                    <?= Form::color('design[heading_color]', 'Headings', $d['heading_color']) ?>
                    <?= Form::color('design[accent_color]', 'Buttons & accents', $d['accent_color']) ?>
                    <?= Form::color('design[card_bg]', 'Card background', $d['card_bg']) ?>
                </div>
                <p class="help">Leave blank to use the brand colours from Settings.</p>
            </div></div>
            <div class="card"><div class="card-head"><h2>Spacing & size</h2></div><div class="card-body">
                <p class="help mb-2">Any CSS length: 40px, 2.5rem, 5vh, 85%. Tablet and mobile inherit from the larger screen when left blank.</p>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th></th><?php foreach ($bpLabels as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                        <?php foreach ($spacingLabels as $prop => $label): ?>
                            <tr><th style="text-transform:none;letter-spacing:0;font-size:.8125rem"><?= e($label) ?></th>
                                <?php foreach (array_keys($bpLabels) as $bp): ?>
                                    <td><input class="input input-sm" type="text" name="design[<?= $bp ?>][<?= $prop ?>]" value="<?= e($d[$bp][$prop]) ?>" placeholder="auto" aria-label="<?= e($label . ' ' . $bp) ?>"></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div></div>
            <div class="card"><div class="card-head"><h2>Visibility</h2></div><div class="card-body">
                <div class="grid grid-4">
                    <?= Form::toggle('design[hide_desktop]', 'Hide on desktop', $d['hide_desktop']) ?>
                    <?= Form::toggle('design[hide_tablet]', 'Hide on tablet', $d['hide_tablet']) ?>
                    <?= Form::toggle('design[hide_mobile]', 'Hide on mobile', $d['hide_mobile']) ?>
                    <?= Form::select('design[mobile_columns]', 'Grid columns on mobile', ['1' => '1', '2' => '2'], $d['mobile_columns']) ?>
                </div>
            </div></div>
        </div>

        <div class="tab-panel card" id="advanced" hidden><div class="card-body">
            <div class="grid grid-2">
                <?= Form::input('name', 'Admin label', $section['name'], ['maxlength' => 120, 'help' => 'Only shown in the builder, to tell sections apart.']) ?>
                <?= Form::input('design[anchor]', 'Anchor ID', $d['anchor'], ['help' => 'Lets links jump here: /page#pricing. Lowercase letters, numbers and hyphens; unique on the page.']) ?>
                <?= Form::input('design[css_class]', 'Extra CSS class', $d['css_class']) ?>
                <?= Form::toggle('is_visible', 'Visible on the published page', (int) $section['is_visible']) ?>
            </div>
        </div></div>
    </div>
    <div class="sticky-actions">
        <a class="btn btn-ghost" href="/admin/pages/<?= (int) $p['id'] ?>">Back to page</a>
        <button class="btn btn-secondary" type="submit" name="after" value="stay">Save</button>
        <button class="btn btn-primary" type="submit" name="after" value="back">Save & close</button>
    </div>
</form>
<script nonce="<?= e(nonce()) ?>">
// Ordered product picker: keeps the hidden value in tick order.
document.querySelectorAll('[data-products-picker]').forEach(function (picker) {
  var hidden = picker.parentNode.querySelector('[data-products-value]');
  var order = hidden.value ? hidden.value.split(',') : [];
  picker.addEventListener('change', function (e) {
    var v = e.target.value;
    order = order.filter(function (x) { return x !== v; });
    if (e.target.checked) order.push(v);
    hidden.value = order.join(',');
  });
});
</script>
