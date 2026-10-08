<?php
use App\Cms\Icons;
use App\Http\Form;

$v = $p ?? [];
$action = $p ? '/admin/products/' . $p['id'] : '/admin/products';
$features = implode("\n", json_decode((string) ($v['features'] ?? '[]'), true) ?: []);
$benefits = implode("\n", json_decode((string) ($v['benefits'] ?? '[]'), true) ?: []);
$specs = json_decode((string) ($v['specs'] ?? '[]'), true) ?: [];
$specs[] = ['label' => '', 'value' => ''];
$canEdit = $p ? can('products.edit') : can('products.create');
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => $p ? $p['name'] : 'New product', 'crumbs' => [['Products', '/admin/products'], [$p ? $p['name'] : 'New', null]], 'actions' => $p ? '<a class="btn btn-secondary" href="/preview/product/' . (int) $p['id'] . '" target="_blank">' . Icons::svg('eye', 'icon', 16) . ' Preview</a>' : '']) ?>
<?php if ($p && $canEdit): ?>
    <?php
    $templatesList = App\Core\DB::all("SELECT id, name, is_default FROM builder_templates WHERE kind = 'product' ORDER BY name");
    $defaultTpl = array_values(array_filter($templatesList, static fn ($t) => (int) $t['is_default'] === 1))[0] ?? null;
    $mode = $p['page_layout'] ?? 'template';
    ?>
    <form method="post" action="/admin/products/<?= (int) $p['id'] ?>/design" class="card mb-2">
        <?= csrf_field() ?>
        <div class="card-body row" style="gap:1rem;flex-wrap:wrap;align-items:flex-end">
            <div style="flex:1;min-width:260px">
                <strong><?= Icons::svg('layout', 'icon', 18) ?> Product page design</strong>
                <p class="muted small" style="margin:.25rem 0 0">
                    <?php if ($mode === 'builder'): ?>This product has its own drag-and-drop layout.
                    <?php elseif ($mode === 'template'): ?><?= $defaultTpl || $p['template_id'] ? 'Uses a builder template — change the template and every product using it follows.' : 'No default product template yet, so the classic page is shown. Create one under Builder templates.' ?>
                    <?php else: ?>Uses the classic product page.<?php endif; ?>
                </p>
            </div>
            <label class="field" style="min-width:200px"><span class="label">Design</span>
                <select class="select" name="page_layout">
                    <option value="template"<?= $mode === 'template' ? ' selected' : '' ?>>Template<?= $defaultTpl ? ' (default: ' . e($defaultTpl['name']) . ')' : '' ?></option>
                    <option value="builder"<?= $mode === 'builder' ? ' selected' : '' ?>>Own builder layout</option>
                    <option value="classic"<?= $mode === 'classic' ? ' selected' : '' ?>>Classic product page</option>
                </select>
            </label>
            <?php if ($templatesList): ?>
            <label class="field" style="min-width:200px"><span class="label">Template</span>
                <select class="select" name="template_id">
                    <option value="">Default template</option>
                    <?php foreach ($templatesList as $t): ?><option value="<?= (int) $t['id'] ?>"<?= (int) ($p['template_id'] ?? 0) === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <button class="btn btn-secondary" type="submit">Save design</button>
            <a class="btn btn-primary" href="/admin/builder/product/<?= (int) $p['id'] ?>"><?= Icons::svg('layout', 'icon', 16) ?> Edit with Builder</a>
        </div>
    </form>
<?php endif; ?>
<form method="post" action="<?= e($action) ?>" data-guard>
    <?= csrf_field() ?>
    <div data-tabs="product">
        <div class="tabs" role="tablist">
            <button class="tab" type="button" role="tab" data-tab="details">Details</button>
            <button class="tab" type="button" role="tab" data-tab="pricing">Pricing & markets</button>
            <button class="tab" type="button" role="tab" data-tab="content">Features & content</button>
            <button class="tab" type="button" role="tab" data-tab="media">Images</button>
            <button class="tab" type="button" role="tab" data-tab="seo">SEO</button>
        </div>
        <div class="tab-panel" id="details">
            <div class="card"><div class="card-body">
                <div class="grid grid-2">
                    <?= Form::input('name', 'Name', $v['name'] ?? '', ['required' => true, 'maxlength' => 200, 'data' => ['slug-source' => 'product-slug']]) ?>
                    <?= Form::input('slug', 'Address', $v['slug'] ?? '', ['id' => 'product-slug', 'prefix' => '/' . App\Services\Settings::get('product', 'base_path') . '/']) ?>
                    <?= Form::input('sku', 'SKU', $v['sku'] ?? '', ['maxlength' => 80]) ?>
                    <?= Form::select('status', 'Status', $statuses, $v['status'] ?? 'DRAFT', ['help' => 'Global status. Each market also has its own availability under Pricing & markets.']) ?>
                    <?= Form::select('category_id', 'Category', $categories, (string) ($v['category_id'] ?? ''), ['empty' => 'None']) ?>
                    <?= Form::select('brand_id', 'Brand', $brands, (string) ($v['brand_id'] ?? ''), ['empty' => 'None']) ?>
                </div>
                <?= Form::textarea('short_description', 'Short description', $v['short_description'] ?? '', ['rows' => 2, 'maxlength' => 1000, 'help' => 'Shown on product cards.']) ?>
                <div class="grid grid-3">
                    <?= Form::input('storage', 'Storage', $v['storage'] ?? '', ['placeholder' => '3 TB, Unlimited']) ?>
                    <?= Form::input('min_users', 'Minimum users', $v['min_users'] ?? '', ['type' => 'number', 'min' => 0]) ?>
                    <?= Form::input('max_users', 'Maximum users', $v['max_users'] ?? '', ['type' => 'number', 'min' => 0]) ?>
                </div>
                <?= Form::toggle('is_featured', 'Featured product (global default)', $v['is_featured'] ?? 0) ?>
            </div></div>
            <div class="card"><div class="card-head"><h2>Call to action</h2></div><div class="card-body">
                <div class="grid grid-3">
                    <?= Form::input('cta_label', 'Button label', $v['cta_label'] ?? 'Get started', ['maxlength' => 80]) ?>
                    <?= Form::select('cta_form_id', 'Opens this form', $forms, (string) ($v['cta_form_id'] ?? ''), ['empty' => 'The default product form', 'help' => 'The lead records which product the visitor asked about.']) ?>
                    <?= Form::input('cta_url', 'Or link to', $v['cta_url'] ?? '', ['help' => 'Overrides the form. Path, URL or #popup-ID.']) ?>
                </div>
            </div></div>
        </div>

        <div class="tab-panel" id="pricing" hidden>
            <div class="card"><div class="card-head"><h2>Default price</h2></div><div class="card-body">
                <div class="grid grid-4">
                    <?= Form::input('currency', 'Currency', $v['currency'] ?? 'INR', ['maxlength' => 3]) ?>
                    <?= Form::input('annual_price', 'Annual price (per month)', $v['annual_price'] ?? '', ['inputmode' => 'decimal']) ?>
                    <?= Form::input('monthly_price', 'Monthly price', $v['monthly_price'] ?? '', ['inputmode' => 'decimal']) ?>
                    <?= Form::input('compare_at_price', 'Compare-at price', $v['compare_at_price'] ?? '', ['inputmode' => 'decimal']) ?>
                    <?= Form::select('billing_period', 'Billing', ['BOTH' => 'Monthly & annual', 'MONTHLY' => 'Monthly', 'ANNUAL' => 'Annual', 'ONE_TIME' => 'One-time'], $v['billing_period'] ?? 'BOTH') ?>
                    <?= Form::input('discount_percent', 'Discount %', $v['discount_percent'] ?? '', ['type' => 'number', 'min' => 0, 'max' => 100]) ?>
                    <?= Form::input('price_suffix', 'Price suffix', $v['price_suffix'] ?? '', ['placeholder' => 'per user / month']) ?>
                    <?= Form::input('price_note', 'Price note', $v['price_note'] ?? '', ['placeholder' => 'Excl. GST']) ?>
                </div>
                <p class="help">Prices are stored as exact decimals. A market's own price below takes precedence.</p>
            </div></div>
            <?php foreach ($markets as ['country' => $c, 'row' => $r]): $cid = (int) $c['id']; $n = "markets[$cid]"; ?>
                <div class="card" data-field-scope>
                    <div class="card-head"><h2><?= e($c['name']) ?> <span class="small muted"><?= e(App\Services\Countries::href($c, '/')) ?></span></h2>
                        <label class="switch"><input type="checkbox" name="<?= $n ?>[enabled]" value="1" data-field-name="enabled"<?= $r || !$p ? ' checked' : '' ?>><span>Sold here</span></label></div>
                    <div class="card-body" data-show-when='{"enabled":["1"]}'>
                        <div class="grid grid-4">
                            <?= Form::select($n . '[status]', 'Availability', ['PUBLISHED' => 'On sale', 'DRAFT' => 'Draft', 'ARCHIVED' => 'Archived'], $r['status'] ?? 'PUBLISHED') ?>
                            <?= Form::input($n . '[currency]', 'Currency', $r['currency'] ?? $c['currency'], ['maxlength' => 3]) ?>
                            <?= Form::input($n . '[annual_price]', 'Annual (per month)', $r['annual_price'] ?? '', ['inputmode' => 'decimal']) ?>
                            <?= Form::input($n . '[monthly_price]', 'Monthly', $r['monthly_price'] ?? '', ['inputmode' => 'decimal']) ?>
                            <?= Form::input($n . '[compare_at_price]', 'Compare-at', $r['compare_at_price'] ?? '', ['inputmode' => 'decimal']) ?>
                            <?= Form::input($n . '[price_suffix]', 'Suffix', $r['price_suffix'] ?? '') ?>
                            <?= Form::input($n . '[price_note]', 'Note', $r['price_note'] ?? '') ?>
                            <?= Form::input($n . '[cta_label]', 'Button label', $r['cta_label'] ?? '') ?>
                        </div>
                        <?= Form::toggle($n . '[is_featured]', 'Featured in ' . $c['name'], $r['is_featured'] ?? ($v['is_featured'] ?? 0)) ?>
                        <details><summary>SEO for this market</summary>
                            <?= Form::input($n . '[seo_title]', 'SEO title', $r['seo_title'] ?? '') ?>
                            <?= Form::textarea($n . '[seo_description]', 'Meta description', $r['seo_description'] ?? '', ['rows' => 2]) ?>
                        </details>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="card"><div class="card-head"><h2>Variants</h2><span class="small muted">Optional: storage or seat tiers</span></div><div class="card-body">
                <div data-repeater="variants">
                    <div data-repeater-items>
                        <?php foreach ($variants as $i => $var): ?>
                            <div class="repeater-item" data-repeater-item><div class="repeater-item-head"><strong data-item-index>#<?= $i + 1 ?></strong><button class="btn btn-danger-ghost btn-xs" type="button" data-repeater-action="remove">Remove</button></div>
                                <div class="grid grid-4"><?php foreach (['name' => 'Name', 'sku' => 'SKU', 'storage' => 'Storage', 'users' => 'Users', 'annual_price' => 'Annual', 'monthly_price' => 'Monthly'] as $k => $l): ?><label class="field"><span class="label"><?= $l ?></span><input class="input" data-name="<?= $k ?>" value="<?= e($var[$k]) ?>"></label><?php endforeach; ?></div></div>
                        <?php endforeach; ?>
                    </div>
                    <template><div class="repeater-item" data-repeater-item><div class="repeater-item-head"><strong data-item-index>#</strong><button class="btn btn-danger-ghost btn-xs" type="button" data-repeater-action="remove">Remove</button></div>
                        <div class="grid grid-4"><?php foreach (['name' => 'Name', 'sku' => 'SKU', 'storage' => 'Storage', 'users' => 'Users', 'annual_price' => 'Annual', 'monthly_price' => 'Monthly'] as $k => $l): ?><label class="field"><span class="label"><?= $l ?></span><input class="input" data-name="<?= $k ?>"></label><?php endforeach; ?></div></div></template>
                    <button class="btn btn-secondary btn-sm" type="button" data-repeater-add><?= Icons::svg('plus', 'icon', 14) ?> Add variant</button>
                </div>
            </div></div>
        </div>

        <div class="tab-panel" id="content" hidden>
            <div class="card"><div class="card-body">
                <div class="grid grid-2">
                    <?= Form::textarea('features', 'Features', $features, ['rows' => 8, 'help' => 'One per line. Shown on cards and in comparisons.']) ?>
                    <?= Form::textarea('benefits', 'Benefits', $benefits, ['rows' => 8, 'help' => 'One per line. Shown under "Top benefits of …".']) ?>
                </div>
                <span class="label">Specifications</span>
                <div data-repeater="specs"><div data-repeater-items>
                    <?php foreach ($specs as $i => $s): ?>
                        <div class="row mb-2" data-repeater-item><input class="input grow" data-name="label" value="<?= e($s['label'] ?? '') ?>" placeholder="Label, e.g. Storage"><input class="input grow" data-name="value" value="<?= e($s['value'] ?? '') ?>" placeholder="Value, e.g. 3 TB"><button class="btn btn-ghost btn-sm" type="button" data-repeater-action="up">↑</button><button class="btn btn-danger-ghost btn-sm" type="button" data-repeater-action="remove">×</button></div>
                    <?php endforeach; ?>
                </div>
                <template><div class="row mb-2" data-repeater-item><input class="input grow" data-name="label" placeholder="Label"><input class="input grow" data-name="value" placeholder="Value"><button class="btn btn-ghost btn-sm" type="button" data-repeater-action="up">↑</button><button class="btn btn-danger-ghost btn-sm" type="button" data-repeater-action="remove">×</button></div></template>
                <button class="btn btn-secondary btn-sm" type="button" data-repeater-add>Add specification</button></div>
                <hr>
                <?= Form::richtext('description', 'Full description', $v['description'] ?? '', ['minHeight' => '240px']) ?>
            </div></div>
        </div>

        <div class="tab-panel" id="media" hidden>
            <div class="card"><div class="card-body">
                <?= Form::media('image', 'Main image', $imageUrl, ['kind' => 'IMAGE']) ?>
                <?= Form::textarea('gallery', 'Gallery images', implode("\n", $gallery), ['rows' => 4, 'help' => 'One media-library address per line (copy it from the library).']) ?>
            </div></div>
        </div>

        <div class="tab-panel" id="seo" hidden>
            <div class="card"><div class="card-body">
                <?= Form::input('seo_title', 'SEO title', $v['seo_title'] ?? '', ['maxlength' => 255, 'data' => ['count' => 60]]) ?>
                <?= Form::textarea('seo_description', 'Meta description', $v['seo_description'] ?? '', ['rows' => 3, 'maxlength' => 500, 'data' => ['count' => 160]]) ?>
                <?= Form::input('primary_keywords', 'Primary keywords', $v['primary_keywords'] ?? '') ?>
                <?= Form::input('canonical_url', 'Canonical URL', $v['canonical_url'] ?? '') ?>
                <?= Form::media('og_image', 'Share image', $ogUrl, ['kind' => 'IMAGE']) ?>
                <?= Form::toggle('no_index', 'Hide from search engines', $v['no_index'] ?? 0) ?>
            </div></div>
        </div>
    </div>
    <?php if ($canEdit): ?><div class="sticky-actions"><a class="btn btn-ghost" href="/admin/products">Cancel</a><button class="btn btn-primary" type="submit"><?= $p ? 'Save product' : 'Create product' ?></button></div><?php endif; ?>
</form>
