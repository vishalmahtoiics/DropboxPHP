<?php
/** @var array $config */
use App\Cms\Icons;
use App\Core\View;

$i = static fn (string $n, int $s = 18) => Icons::svg($n, 'icon', $s);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/builder.css')) ?>">
</head>
<body class="bx-editor">
<div class="bx-app" data-device="desktop">
    <header class="bx-topbar">
        <div class="bx-topbar-left">
            <a class="bx-tb-btn" href="<?= e($config['urls']['back']) ?>" data-bx-exit title="Exit the builder"><?= $i('arrow-left') ?></a>
            <div class="bx-doc-title"><strong><?= e($config['title']) ?></strong><span class="bx-doc-status" data-bx-status></span></div>
        </div>
        <div class="bx-devices" role="group" aria-label="Preview size">
            <button type="button" class="bx-tb-btn" data-bx-device="desktop" aria-pressed="true" title="Desktop"><?= $i('monitor') ?></button>
            <button type="button" class="bx-tb-btn" data-bx-device="tablet" aria-pressed="false" title="Tablet (1024px and below)"><?= $i('tablet') ?></button>
            <button type="button" class="bx-tb-btn" data-bx-device="mobile" aria-pressed="false" title="Mobile (767px and below)"><?= $i('smartphone') ?></button>
        </div>
        <div class="bx-topbar-right">
            <button type="button" class="bx-tb-btn" data-bx-page-settings title="<?= $config['kind'] === 'template' ? 'Template settings' : 'Title, address and SEO' ?>"><?= $i('settings') ?></button>
            <button type="button" class="bx-tb-btn" data-bx-undo title="Undo (Ctrl+Z)" disabled><?= $i('undo') ?></button>
            <button type="button" class="bx-tb-btn" data-bx-redo title="Redo (Ctrl+Shift+Z)" disabled><?= $i('redo') ?></button>
            <button type="button" class="bx-tb-btn" data-bx-navigator title="Navigator (structure)"><?= $i('layers') ?></button>
            <button type="button" class="bx-tb-btn" data-bx-history title="Revisions"><?= $i('history') ?></button>
            <button type="button" class="bx-tb-btn" data-bx-transfer title="Import / export (.json file)"><?= $i('folder-sync') ?></button>
            <?php if ($config['urls']['preview']): ?><a class="bx-tb-btn" href="<?= e($config['urls']['preview']) ?>" target="_blank" rel="noopener" data-bx-preview title="Preview the saved draft in a new tab"><?= $i('eye') ?></a><?php endif; ?>
            <button type="button" class="btn btn-secondary btn-sm" data-bx-save title="Save draft (Ctrl+S)"><?= $i('save', 16) ?> Save draft</button>
            <button type="button" class="btn btn-primary btn-sm" data-bx-publish<?= $config['canPublish'] ? '' : ' disabled title="You do not have permission to publish this page"' ?>><?= $i('upload', 16) ?> Publish</button>
        </div>
    </header>

    <aside class="bx-panel" aria-label="Builder panel">
        <section class="bx-view" data-bx-view="widgets">
            <div class="bx-panel-tabs" role="tablist">
                <button type="button" role="tab" aria-selected="true" data-bx-wtab="elements">Elements</button>
                <button type="button" role="tab" aria-selected="false" data-bx-wtab="templates">Templates</button>
            </div>
            <div data-bx-wpane="elements">
                <div class="bx-search"><input class="input" type="search" placeholder="Search widgets…" data-bx-search aria-label="Search widgets"></div>
                <div class="bx-widget-groups" data-bx-widgets></div>
            </div>
            <div data-bx-wpane="templates" hidden>
                <p class="bx-hint">Insert a saved layout. Save your own from the right-click menu of any container.</p>
                <div class="bx-template-list" data-bx-templates><p class="bx-hint">Loading…</p></div>
                <p class="bx-hint"><a href="/admin/builder/templates" target="_blank" rel="noopener">Manage templates</a></p>
            </div>
        </section>
        <section class="bx-view" data-bx-view="settings" hidden>
            <div class="bx-settings-head">
                <button type="button" class="bx-tb-btn" data-bx-back title="All widgets"><?= $i('grid') ?></button>
                <strong data-bx-settings-title>Settings</strong>
                <button type="button" class="bx-tb-btn" data-bx-parent title="Select the parent container"><?= $i('arrow-up-right') ?></button>
            </div>
            <div class="bx-panel-tabs" role="tablist" data-bx-tabs></div>
            <div class="bx-device-note" data-bx-device-note hidden></div>
            <div class="bx-settings-body" data-bx-settings></div>
        </section>
    </aside>

    <main class="bx-stage">
        <div class="bx-frame-wrap">
            <iframe class="bx-frame" title="Page canvas" src="<?= e($config['urls']['canvas']) ?>" data-bx-frame></iframe>
            <div class="bx-loading" data-bx-loading>Loading the page…</div>
        </div>
    </main>

    <div class="bx-navigator" data-bx-nav-panel hidden>
        <div class="bx-navigator-head"><strong>Navigator</strong><button type="button" class="bx-tb-btn" data-bx-nav-close title="Close"><?= $i('x') ?></button></div>
        <div class="bx-navigator-body" data-bx-nav-tree></div>
    </div>
</div>

<div class="bx-menu" data-bx-menu hidden role="menu"></div>

<dialog class="bx-dialog" data-bx-structure aria-labelledby="bx-structure-title">
    <div class="dialog-head"><h2 id="bx-structure-title">Select your structure</h2><button class="btn btn-ghost btn-icon" type="button" data-close-dialog aria-label="Close"><?= $i('x') ?></button></div>
    <div class="dialog-body"><div class="bx-structures" data-bx-structures></div></div>
</dialog>

<dialog class="bx-dialog" data-bx-settings-dialog aria-labelledby="bx-set-title">
    <form method="dialog" data-bx-settings-form>
        <div class="dialog-head"><h2 id="bx-set-title"><?= $config['kind'] === 'template' ? 'Template settings' : ($config['kind'] === 'product' ? 'Product settings' : 'Page settings') ?></h2><button class="btn btn-ghost btn-icon" type="button" data-close-dialog aria-label="Close"><?= $i('x') ?></button></div>
        <div class="dialog-body stack" data-bx-settings-fields></div>
        <div class="dialog-foot"><button class="btn btn-secondary" type="button" data-close-dialog>Cancel</button><button class="btn btn-primary" type="submit">Save settings</button></div>
    </form>
</dialog>

<dialog class="bx-dialog" data-bx-revisions aria-labelledby="bx-rev-title">
    <div class="dialog-head"><h2 id="bx-rev-title">Revisions</h2><button class="btn btn-ghost btn-icon" type="button" data-close-dialog aria-label="Close"><?= $i('x') ?></button></div>
    <div class="dialog-body">
        <p class="muted small">Every publish is kept (the last 30). Loading one puts it in the editor; publish to make it live.</p>
        <div data-bx-revision-list></div>
        <div class="bx-draft-row" data-bx-draft-row hidden><span class="small">You have an unpublished draft.</span><button class="btn btn-danger-ghost btn-sm" type="button" data-bx-discard>Discard the draft</button></div>
    </div>
</dialog>

<dialog class="bx-dialog" data-bx-save-template aria-labelledby="bx-st-title">
    <form method="dialog" class="stack" data-bx-save-template-form>
        <div class="dialog-head"><h2 id="bx-st-title">Save as template</h2></div>
        <div class="dialog-body stack">
            <label class="field"><span class="label">Template name</span><input class="input" name="name" maxlength="160" required></label>
            <p class="muted small" data-bx-st-note></p>
        </div>
        <div class="dialog-foot"><button class="btn btn-secondary" type="button" data-close-dialog>Cancel</button><button class="btn btn-primary" type="submit">Save template</button></div>
    </form>
</dialog>

<dialog class="bx-dialog" data-bx-transfer-dialog aria-labelledby="bx-tr-title">
    <div class="dialog-head"><h2 id="bx-tr-title">Import / export</h2><button class="btn btn-ghost btn-icon" type="button" data-close-dialog aria-label="Close"><?= $i('x') ?></button></div>
    <div class="dialog-body stack">
        <section class="stack">
            <h3 class="bx-dialog-sub">Export</h3>
            <p class="muted small">Download this layout as a .json file, including changes you have not saved yet. Import it into another page, product or site.</p>
            <div><button class="btn btn-secondary" type="button" data-bx-export><?= $i('download', 16) ?> Download .json</button></div>
        </section>
        <hr class="bx-dialog-rule">
        <form class="stack" data-bx-import-form>
            <h3 class="bx-dialog-sub">Import</h3>
            <label class="field"><span class="label">Layout file (.json)</span><input class="input" type="file" name="file" accept=".json,application/json" required></label>
            <fieldset class="bx-choices">
                <legend class="label">Put it</legend>
                <label><input type="radio" name="mode" value="append" checked> <span>At the end of this layout</span></label>
                <label><input type="radio" name="mode" value="replace"> <span>Instead of everything on it now</span></label>
            </fieldset>
            <p class="muted small">Nothing changes on the live site until you publish. Undo (Ctrl+Z) takes the import back.<?= $config['canCode'] ? '' : ' Custom HTML and CSS in the file are left out: your role cannot add code.' ?></p>
            <div><button class="btn btn-primary" type="submit"><?= $i('upload', 16) ?> Import</button></div>
        </form>
    </div>
</dialog>

<?= View::partial('admin/partials/media-dialog') ?>
<script type="application/json" id="bx-config"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script nonce="<?= e(nonce()) ?>" src="<?= e(asset('js/admin.js')) ?>"></script>
<script nonce="<?= e(nonce()) ?>" src="<?= e(asset('js/builder.js')) ?>"></script>
</body>
</html>
