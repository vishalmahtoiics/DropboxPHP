<?php
/** @var list<array> $files @var ?int $folder @var list<array> $folders @var int $bytes */
use App\Cms\Icons;

$size = static fn (int $b) => $b > 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';
$empty = count(array_filter($folders, static fn ($f) => (int) $f['n'] === 0));
?>
<?= App\Core\View::partial('admin/partials/page-head', [
    'crumbs' => [['Media library', '/admin/media'], ['Unused files', null]],
    'heading' => 'Unused files',
    'sub' => 'Files that no page, product, article, template, category, popup, lead magnet or website setting uses.',
]) ?>
<div class="grid grid-3">
    <div class="card span-2">
        <form class="toolbar" method="get">
            <select class="select" name="folder" data-autosubmit aria-label="Folder">
                <option value="">Every folder</option>
                <?php foreach ($folders as $f): ?>
                    <option value="<?= (int) $f['id'] ?>" <?= $folder === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?> (<?= (int) $f['n'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if (!$files): ?>
            <div class="empty"><?= Icons::svg('check-circle', 'icon', 32) ?><p>Nothing to clean up: every file here is in use.</p></div>
        <?php else: ?>
            <div class="card-body">
                <div class="media-grid">
                    <?php foreach (array_slice($files, 0, 300) as $m): ?>
                        <a class="media-tile" href="/admin/media?item=<?= (int) $m['id'] ?>" title="<?= e($m['filename']) ?>">
                            <?php if ($m['kind'] === 'IMAGE'): ?><img src="<?= e($m['url']) ?>" alt="" loading="lazy"><?php else: ?><span class="file-ph"><?= Icons::svg('file', 'icon', 28) ?></span><?php endif; ?>
                            <div class="cap truncate"><?= e($m['filename']) ?> · <?= $size((int) $m['size']) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($files) > 300): ?><p class="help mt-2">Showing 300 of <?= count($files) ?>. All of them are removed.</p><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-head"><h2>Clean up</h2></div>
        <form method="post" action="/admin/media/unused" data-confirm="Delete these unused files for good? This cannot be undone.">
            <?= csrf_field() ?>
            <input type="hidden" name="folder" value="<?= (int) $folder ?>">
            <div class="card-body stack">
                <p><strong><?= count($files) ?></strong> unused <?= count($files) === 1 ? 'file' : 'files' ?> · <strong><?= $size($bytes) ?></strong></p>
                <label class="switch"><input type="checkbox" name="empty_folders" value="1" checked><span>Also delete folders left empty<?= $empty ? ' (' . $empty . ' already empty)' : '' ?></span></label>
                <p class="help">A file counts as used when its address appears in a page (live, draft or section), product, article, builder template, category, brand, popup, lead magnet or website setting. Older builder versions in a page's history are not counted: restoring one may show a missing image.</p>
                <p class="help">Take a backup first if you are unsure.</p>
            </div>
            <div class="card-foot form-actions">
                <button class="btn btn-danger" type="submit" <?= !$files && !$empty ? 'disabled' : '' ?>><?= Icons::svg('trash', 'icon', 16) ?> Delete <?= count($files) ?> unused <?= count($files) === 1 ? 'file' : 'files' ?></button>
            </div>
        </form>
    </div>
</div>
