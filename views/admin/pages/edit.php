<?php
use App\Cms\Blocks;
use App\Cms\Icons;
use App\Core\DB;
use App\Http\Form;

$og = $p['og_image_id'] ? (string) DB::value('SELECT url FROM media WHERE id = ?', [$p['og_image_id']]) : '';
$canEdit = can('pages.edit');
$actions = '<a class="btn btn-secondary" href="/preview/page/' . (int) $p['id'] . '" target="_blank">' . Icons::svg('eye', 'icon', 16) . ' Preview</a>';
if ($p['status'] === 'PUBLISHED') {
    $actions .= ' <a class="btn btn-ghost" href="' . e($publicPath) . '" target="_blank">' . Icons::svg('external', 'icon', 16) . ' View live</a>';
}
if ($canEdit) {
    $actions .= ' ' . ($p['editor'] === 'builder'
        ? '<a class="btn btn-primary" href="/admin/builder/page/' . (int) $p['id'] . '">' . Icons::svg('layout', 'icon', 16) . ' Edit with Builder</a>'
        : Form::action('/admin/pages/' . $p['id'] . '/builder', 'Edit with Builder', 'btn btn-secondary', '', 'layout'));
}
if (can('pages.publish') && $p['status'] !== 'PUBLISHED') {
    $actions .= ' ' . Form::action('/admin/pages/' . $p['id'] . '/status', 'Publish', 'btn btn-primary', '', 'check');
}
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => $p['title'], 'crumbs' => [['Pages', '/admin/pages'], [$p['title'], null]], 'sub' => $publicPath, 'actions' => $actions]) ?>
<div class="row mb-2"><?= Form::badge($p['status']) ?><?php if ($p['status'] === 'SCHEDULED'): ?><span class="small muted">Goes live <?= e(format_datetime($p['published_at'])) ?></span><?php endif; ?><span class="small muted">Last saved <?= e(time_ago($p['updated_at'])) ?></span></div>

<div data-tabs="page">
    <div class="tabs" role="tablist">
        <button class="tab" role="tab" data-tab="builder">Page builder</button>
        <button class="tab" role="tab" data-tab="settings">Settings</button>
        <button class="tab" role="tab" data-tab="seo">SEO</button>
        <button class="tab" role="tab" data-tab="preview">Preview</button>
    </div>

    <div class="tab-panel" id="builder">
        <?php if ($p['editor'] === 'builder'): ?>
            <div class="card builder-switch">
                <div class="card-body row" style="justify-content:space-between;gap:1rem;flex-wrap:wrap">
                    <div><h2 style="margin:0 0 .25rem"><?= Icons::svg('layout', 'icon', 20) ?> This page uses the visual builder</h2>
                        <p class="muted small" style="margin:0">Design it with drag and drop: containers, widgets, spacing and sizes for desktop, tablet and mobile.<?= $p['builder_draft'] !== null ? ' <strong>There is an unpublished draft.</strong>' : '' ?></p></div>
                    <div class="row">
                        <?php if ($canEdit): ?><a class="btn btn-primary" href="/admin/builder/page/<?= (int) $p['id'] ?>"><?= Icons::svg('layout', 'icon', 16) ?> Open the builder</a>
                        <?= Form::action('/admin/pages/' . $p['id'] . '/sections-editor', 'Switch back to sections', 'btn btn-ghost', 'Show the classic sections on this page instead? The builder layout is kept, so you can switch back later.', 'layers') ?><?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php if ($canEdit): ?>
            <div class="card builder-switch mb-2">
                <div class="card-body row" style="justify-content:space-between;gap:1rem;flex-wrap:wrap">
                    <div><strong>Prefer drag and drop?</strong> <span class="muted small">Open this page in the visual builder. Your sections are copied across — nothing is lost.</span></div>
                    <?= Form::action('/admin/pages/' . $p['id'] . '/builder', 'Edit with Builder', 'btn btn-primary btn-sm', '', 'layout') ?>
                </div>
            </div>
            <?php endif; ?>
        <div class="builder">
            <div>
                <?php if (!$sections): ?>
                    <div class="card"><div class="empty"><?= Icons::svg('layers', 'icon', 32) ?><p>This page has no sections yet.</p><?php if ($canEdit): ?><button class="btn btn-primary" type="button" data-open-dialog="add-section"><?= Icons::svg('plus', 'icon', 16) ?> Add the first section</button><?php endif; ?></div></div>
                <?php else: ?>
                    <ul class="section-list" <?= $canEdit ? 'data-sortable="/admin/pages/' . (int) $p['id'] . '/sections/reorder"' : '' ?>>
                        <?php foreach ($sections as $i => $s): $def = Blocks::get($s['block_type']); ?>
                            <li class="section-item<?= (int) $s['is_visible'] ? '' : ' hidden-section' ?>" data-sort-id="<?= (int) $s['id'] ?>" <?= $canEdit ? 'draggable="true"' : '' ?>>
                                <?php if ($canEdit): ?><span class="drag-handle" tabindex="0" title="Drag to reorder (or Alt+↑/↓)" aria-label="Reorder"><?= Icons::svg('drag', 'icon', 18) ?></span><?php endif; ?>
                                <span class="section-type-icon"><?= Icons::svg($def['icon'] ?? 'layout', 'icon', 18) ?></span>
                                <div class="section-meta">
                                    <strong><?= e($s['name'] ?: ($s['content']['heading'] ?? '') ?: ($def['label'] ?? $s['block_type'])) ?></strong>
                                    <span class="small muted"><?= e($def['label'] ?? 'Unknown block') ?><?= (int) $s['is_visible'] ? '' : ' · hidden' ?></span>
                                </div>
                                <?php if ($canEdit): ?>
                                    <a class="btn btn-secondary btn-sm" href="/admin/sections/<?= (int) $s['id'] ?>"><?= Icons::svg('edit', 'icon', 14) ?> Edit</a>
                                    <div class="dropdown" data-dropdown>
                                        <button class="btn btn-ghost btn-icon" type="button" data-dropdown-toggle aria-label="More"><?= Icons::svg('more', 'icon', 18) ?></button>
                                        <div class="dropdown-menu">
                                            <?php if ($i > 0): ?><?= Form::action('/admin/sections/' . $s['id'] . '/move?dir=up', 'Move up', '', '', 'chevron-up') ?><?php endif; ?>
                                            <?php if ($i < count($sections) - 1): ?><?= Form::action('/admin/sections/' . $s['id'] . '/move?dir=down', 'Move down', '', '', 'chevron-down') ?><?php endif; ?>
                                            <?= Form::action('/admin/sections/' . $s['id'] . '/toggle', (int) $s['is_visible'] ? 'Hide' : 'Show', '', '', (int) $s['is_visible'] ? 'eye-off' : 'eye') ?>
                                            <?= Form::action('/admin/sections/' . $s['id'] . '/duplicate', 'Duplicate', '', '', 'copy') ?>
                                            <?= Form::action('/admin/sections/' . $s['id'] . '/delete', 'Delete', '', 'Delete this section? This cannot be undone.', 'trash') ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($canEdit): ?><button class="btn btn-secondary btn-block" type="button" data-open-dialog="add-section"><?= Icons::svg('plus', 'icon', 16) ?> Add section</button><?php endif; ?>
                <?php endif; ?>
            </div>
            <aside>
                <div class="card"><div class="card-body small">
                    <h3>How the builder works</h3>
                    <p class="muted">Each section is a block — hero, features, pricing, FAQ… Drag to reorder. Hidden sections stay on the page without being published.</p>
                    <p class="muted mb-0">Every section has the same Design tab: spacing per screen size, width, background, colours and an anchor ID for links like <code>#pricing</code>.</p>
                </div></div>
            </aside>
        </div>
        <?php endif; ?>
    </div>

    <div class="tab-panel" id="settings" hidden>
        <form class="card" method="post" action="/admin/pages/<?= (int) $p['id'] ?>" data-guard>
            <?= csrf_field() ?>
            <input type="hidden" name="seo_title" value="<?= e($p['seo_title']) ?>"><input type="hidden" name="seo_description" value="<?= e($p['seo_description']) ?>">
            <div class="card-body">
                <div class="grid grid-2">
                    <?= Form::input('title', 'Title', $p['title'], ['required' => true, 'maxlength' => 255]) ?>
                    <?php if ($p['slug'] === ''): ?>
                        <?= Form::input('slug', 'Address', '/', ['readonly' => true, 'help' => 'This is the homepage.']) ?>
                    <?php else: ?>
                        <?= Form::input('slug', 'Address', $p['slug'], ['required' => true, 'help' => 'Changing the address of a published page adds a permanent redirect from the old one.']) ?>
                    <?php endif; ?>
                    <?= Form::select('status', 'Status', $statuses, $p['status'], ['help' => can('pages.publish') ? '' : 'Your role can save drafts; an editor publishes.', 'data' => ['field-name' => 'status']]) ?>
                    <?= Form::input('published_at', 'Publish date', $p['published_at'] ? date('Y-m-d\TH:i', strtotime((string) $p['published_at'])) : '', ['type' => 'datetime-local', 'help' => 'For scheduled pages, the moment they go live.']) ?>
                    <?= Form::select('category_id', 'Category', $categories, (string) $p['category_id'], ['empty' => 'None']) ?>
                </div>
                <hr>
                <div class="grid grid-2">
                    <?= Form::toggle('show_header', 'Show the site header', (int) $p['show_header']) ?>
                    <?= Form::toggle('show_footer', 'Show the site footer', (int) $p['show_footer']) ?>
                </div>
                <?php foreach (['canonical_url', 'og_title', 'og_description', 'primary_keywords'] as $k): ?><input type="hidden" name="<?= $k ?>" value="<?= e($p[$k]) ?>"><?php endforeach; ?>
                <input type="hidden" name="og_image" value="<?= e($og) ?>">
                <?php if ((int) $p['no_index']): ?><input type="hidden" name="no_index" value="1"><?php endif; ?>
                <?php if ((int) $p['no_follow']): ?><input type="hidden" name="no_follow" value="1"><?php endif; ?>
            </div>
            <?php if ($canEdit): ?><div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save settings</button></div><?php endif; ?>
        </form>
        <?php $others = array_filter(App\Services\Countries::accessible(), static fn ($c) => (int) $c['id'] !== (int) $p['country_id']); ?>
        <?php if ($others && can('pages.create')): ?>
        <form class="card" method="post" action="/admin/pages/<?= (int) $p['id'] ?>/copy-to-market"><div class="card-head"><h2>Copy to another market</h2></div>
            <div class="card-body row"><?= csrf_field() ?>
                <select class="select" name="country_id" style="width:auto"><?php foreach ($others as $o): ?><option value="<?= (int) $o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select>
                <button class="btn btn-secondary" type="submit">Copy as draft</button>
                <span class="help">The copy is independent: later edits here do not change it.</span></div></form>
        <?php endif; ?>
    </div>

    <div class="tab-panel" id="seo" hidden>
        <form class="card" method="post" action="/admin/pages/<?= (int) $p['id'] ?>" data-guard>
            <?= csrf_field() ?>
            <?php foreach (['title', 'slug', 'status', 'category_id'] as $k): ?><input type="hidden" name="<?= $k ?>" value="<?= e($p[$k]) ?>"><?php endforeach; ?>
            <input type="hidden" name="published_at" value="<?= e($p['published_at'] ? date('Y-m-d\TH:i', strtotime((string) $p['published_at'])) : '') ?>">
            <?php if ((int) $p['show_header']): ?><input type="hidden" name="show_header" value="1"><?php endif; ?>
            <?php if ((int) $p['show_footer']): ?><input type="hidden" name="show_footer" value="1"><?php endif; ?>
            <div class="card-body">
                <div class="seo-preview mb-2" data-seo-preview data-title="seo-title" data-desc="seo-desc" data-fallback-title="<?= e($p['title']) ?>" data-fallback-desc="<?= e(App\Services\Settings::get('seo', 'default_description')) ?>">
                    <div class="u"><?= e(base_url() . $publicPath) ?></div><div class="t"></div><div class="d"></div>
                </div>
                <?= Form::input('seo_title', 'SEO title', $p['seo_title'], ['id' => 'seo-title', 'maxlength' => 255, 'data' => ['count' => 60], 'help' => 'Blank uses the page title. The site name is added by the title template.']) ?>
                <?= Form::textarea('seo_description', 'Meta description', $p['seo_description'], ['id' => 'seo-desc', 'rows' => 3, 'maxlength' => 500, 'data' => ['count' => 160]]) ?>
                <?= Form::input('primary_keywords', 'Primary keywords', $p['primary_keywords'], ['help' => 'Up to three, comma-separated. Used by the SEO checks.']) ?>
                <?= Form::input('canonical_url', 'Canonical URL', $p['canonical_url'], ['help' => 'Leave blank unless this page duplicates another address.']) ?>
                <div class="grid grid-2"><?= Form::toggle('no_index', 'Hide from search engines (noindex)', (int) $p['no_index']) ?><?= Form::toggle('no_follow', 'Do not follow links (nofollow)', (int) $p['no_follow']) ?></div>
                <hr><h3>Social sharing</h3>
                <?= Form::input('og_title', 'Share title', $p['og_title'], ['maxlength' => 255]) ?>
                <?= Form::textarea('og_description', 'Share description', $p['og_description'], ['rows' => 2, 'maxlength' => 500]) ?>
                <?= Form::media('og_image', 'Share image (1200×630)', $og, ['kind' => 'IMAGE']) ?>
                <?= App\Core\View::partial('admin/partials/seo-checks', ['report' => App\Services\SeoAnalysis::forPage($p)]) ?>
            </div>
            <?php if ($canEdit): ?><div class="card-foot form-actions"><button class="btn btn-primary" type="submit">Save SEO</button></div><?php endif; ?>
        </form>
    </div>

    <div class="tab-panel" id="preview" hidden>
        <div class="row-between mb-2">
            <div class="device-switch" data-device-switch="page-preview"><button type="button" class="active" data-width="100%">Desktop</button><button type="button" data-width="820px">Tablet</button><button type="button" data-width="390px">Mobile</button></div>
            <a class="btn btn-ghost btn-sm" href="/preview/page/<?= (int) $p['id'] ?>" target="_blank">Open in a new tab</a>
        </div>
        <iframe class="preview-frame" id="page-preview" src="/preview/page/<?= (int) $p['id'] ?>" title="Page preview" loading="lazy"></iframe>
    </div>
</div>

<?php if ($canEdit): ?>
<dialog id="add-section" class="wide" aria-labelledby="add-section-title">
    <form method="post" action="/admin/pages/<?= (int) $p['id'] ?>/sections">
        <?= csrf_field() ?>
        <div class="dialog-head"><h2 id="add-section-title">Add a section</h2><button class="btn btn-ghost btn-icon" type="button" data-close-dialog aria-label="Close"><?= Icons::svg('x') ?></button></div>
        <div class="dialog-body">
            <?php foreach ($blocks as $group => $items): ?>
                <h3 class="small muted mt-2"><?= e($group) ?></h3>
                <div class="block-picker">
                    <?php foreach ($items as $b): ?>
                        <button class="block-option" type="submit" name="block_type" value="<?= e($b['type']) ?>">
                            <span class="section-type-icon"><?= Icons::svg($b['icon'], 'icon', 18) ?></span>
                            <span><strong><?= e($b['label']) ?></strong><small><?= e($b['description']) ?></small></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </form>
</dialog>
<?php endif; ?>
