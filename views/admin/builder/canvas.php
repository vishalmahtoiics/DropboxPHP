<?php /** @var bool $productContext @var string|null $chromeKind */ ?>
<?php if (($chromeKind ?? null) === 'footer'): ?><div class="bx-chrome-sample">Page content appears here — you are editing the site footer.</div><?php endif; ?>
<div class="bx-root" id="bx-root" data-bx-root></div>
<div class="bx-add-zone" data-bx-add-zone>
    <button type="button" data-bx-add-root title="Add a container"><?= App\Cms\Icons::svg('plus', 'icon', 22) ?></button>
    <button type="button" data-bx-add-template title="Insert a template"><?= App\Cms\Icons::svg('folder', 'icon', 20) ?></button>
    <span>Drag a widget here, or add a container</span>
</div>
<?php if (($chromeKind ?? null) === 'header'): ?><div class="bx-chrome-sample">Page content appears here — you are editing the site header.</div><?php endif; ?>
