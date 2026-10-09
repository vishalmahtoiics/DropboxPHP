<?php
use App\Cms\Icons;
use App\Http\Form;

$qs = static fn (array $extra) => '?' . http_build_query(array_filter(array_merge(['folder' => $folder ?: null, 'kind' => $kind ?: null, 'q' => $q ?: null], $extra)));
$size = static fn (int $b) => $b > 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';
?>
<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Media library', 'sub' => number_format($total) . ' files · ' . $size($storageBytes) . ' · stored with the "' . App\Services\Storage::driver() . '" driver']) ?>
<?php if (can('media.upload')): ?>
<form class="card mb-2" method="post" action="/admin/media/upload" enctype="multipart/form-data">
    <div class="card-body row">
        <?= csrf_field() ?>
        <input type="hidden" name="folder_id" value="<?= (int) $folder ?>">
        <input class="input grow" type="file" name="file[]" multiple required accept="image/*,video/mp4,video/webm,application/pdf,.docx,.xlsx,.pptx,.csv,.txt">
        <button class="btn btn-primary" type="submit"><?= Icons::svg('upload', 'icon', 16) ?> Upload</button>
        <span class="help">Max <?= round(config('app.max_upload_bytes') / 1048576, 1) ?> MB per file. Contents are checked, not just the extension.</span>
    </div>
</form>
<?php endif; ?>
<div class="builder builder-media">
    <aside class="card"><div class="card-body">
        <a class="nav-link<?= !$folder ? ' active' : '' ?>" href="/admin/media"><?= Icons::svg('folder', 'icon', 16) ?> All files</a>
        <?php foreach ($folders as $f): ?>
            <div class="row-between"><a class="nav-link grow<?= $folder === (int) $f['id'] ? ' active' : '' ?>" href="?folder=<?= (int) $f['id'] ?>"><?= Icons::svg('folder', 'icon', 16) ?> <?= e($f['name']) ?> <span class="badge"><?= (int) $f['n'] ?></span></a>
            <?php if (can('media.delete')): ?><?= Form::action('/admin/media/folders/' . $f['id'] . '/delete', '×', 'btn btn-ghost btn-xs', 'Delete this folder? Files stay in the library.') ?><?php endif; ?></div>
        <?php endforeach; ?>
        <?php if (can('media.upload')): ?>
            <form method="post" action="/admin/media/folders" class="mt-2"><?= csrf_field() ?><div class="input-group"><input class="input input-sm" name="name" placeholder="New folder" required maxlength="120"><button class="btn btn-secondary btn-sm" type="submit">Add</button></div></form>
        <?php endif; ?>
    </div></aside>
    <div class="card">
        <form class="toolbar" method="get">
            <?php if ($folder): ?><input type="hidden" name="folder" value="<?= (int) $folder ?>"><?php endif; ?>
            <input class="input search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or alt text">
            <select class="select" name="kind" data-autosubmit><option value="">All types</option><?php foreach (['IMAGE' => 'Images', 'VIDEO' => 'Video', 'DOCUMENT' => 'Documents'] as $k => $v): ?><option value="<?= $k ?>"<?= $kind === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
        </form>
        <?php
        $bulk = [];
        if (can('media.edit')) {
            $bulk['Move to folder'] = ['folder:none' => 'No folder'];
            foreach ($folders as $f) {
                $bulk['Move to folder']['folder:' . (int) $f['id']] = $f['name'];
            }
        }
        if (can('media.delete')) {
            $bulk['delete'] = 'Delete (keep files that pages use)';
            $bulk['delete-all'] = 'Delete, even files that pages use';
        }
        ?>
        <?php if ($items): ?><?= Form::bulkBar('bulk-media', '/admin/media/bulk', $bulk, 'files') ?><?php if ($bulk): ?><div class="toolbar small" style="padding-block:.4rem"><label class="checkbox"><?= Form::bulkAll('bulk-media') ?> <span>Select all on this page</span></label></div><?php endif; ?><?php endif; ?>
        <div class="card-body">
            <?php if (!$items): ?><div class="empty"><?= Icons::svg('image', 'icon', 32) ?><p>No files here yet.</p></div><?php endif; ?>
            <div class="media-grid">
                <?php foreach ($items as $m): ?>
                    <div class="media-tile-wrap"><?php if ($bulk): ?><span class="media-check"><?= Form::bulkCheck('bulk-media', (int) $m['id'], $m['filename']) ?></span><?php endif; ?>
                    <a class="media-tile<?= $selected && (int) $selected['id'] === (int) $m['id'] ? ' selected' : '' ?>" href="<?= e($qs(['item' => $m['id'], 'page' => $pageNo > 1 ? $pageNo : null])) ?>">
                        <?php if ($m['kind'] === 'IMAGE'): ?><img src="<?= e($m['url']) ?>" alt="" loading="lazy"><?php else: ?><div class="file-ph"><?= e(strtoupper(pathinfo($m['filename'], PATHINFO_EXTENSION))) ?></div><?php endif; ?>
                        <div class="cap truncate"><?= e($m['filename']) ?></div>
                    </a></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?= Form::pagination($pageNo, $pages, $total, 'files') ?>
    </div>
    <aside class="card">
        <?php if (!$selected): ?>
            <div class="card-body muted small">Select a file to see its details, copy its address or edit its alt text.</div>
        <?php else: $m = $selected; ?>
            <div class="card-body">
                <?php if ($m['kind'] === 'IMAGE'): ?><img src="<?= e($m['url']) ?>" alt="" style="border-radius:8px;margin-bottom:1rem;max-height:220px;object-fit:contain;width:100%;background:var(--surface-2)"><?php endif; ?>
                <dl class="dl small" style="grid-template-columns:80px 1fr">
                    <dt>File</dt><dd><?= e($m['filename']) ?></dd>
                    <dt>Type</dt><dd><?= e($m['mime_type']) ?></dd>
                    <dt>Size</dt><dd><?= e($size((int) $m['size'])) ?><?= $m['width'] ? ' · ' . (int) $m['width'] . '×' . (int) $m['height'] : '' ?></dd>
                    <dt>Added</dt><dd><?= e(format_datetime($m['created_at'])) ?></dd>
                </dl>
                <div class="input-group mt-1"><input class="input input-sm" readonly value="<?= e($m['url']) ?>" aria-label="File address"><button class="btn btn-secondary btn-sm" type="button" data-copy="<?= e($m['url']) ?>">Copy</button></div>
                <?php if ($usage): ?><div class="alert alert-info mt-1 small"><span>Used in: <?= e(implode('; ', $usage)) ?></span></div><?php endif; ?>
                <?php if (can('media.edit')): ?>
                    <?php $stem = pathinfo((string) $m['storage_key'], PATHINFO_FILENAME); $ext = pathinfo((string) $m['storage_key'], PATHINFO_EXTENSION); $dir = dirname((string) $m['url']); ?>
                    <form method="post" action="/admin/media/<?= (int) $m['id'] ?>/rename" class="stack mt-2" data-confirm="Change this file's address? Every page that uses it is updated automatically<?= $m['provider'] === 'local' ? ', and the old address keeps working' : '' ?>.">
                        <?= csrf_field() ?>
                        <label class="field"><span class="label">File address</span>
                            <span class="prefix-group"><span class="truncate" style="max-width:45%"><?= e($dir) ?>/</span><input class="input" name="name" value="<?= e($stem) ?>" required maxlength="100" pattern="[A-Za-z0-9\- _]+" spellcheck="false"><span>.<?= e($ext) ?></span></span>
                            <span class="help">Use words that describe the file, e.g. business-plan-brochure. Good for SEO.</span>
                        </label>
                        <button class="btn btn-secondary btn-block" type="submit"><?= Icons::svg('link', 'icon', 16) ?> Change address</button>
                    </form>
                <?php endif; ?>
                <?php if (can('media.edit')): ?>
                    <form method="post" action="/admin/media/<?= (int) $m['id'] ?>" class="stack mt-2">
                        <?= csrf_field() ?>
                        <?= Form::input('alt_text', 'Alt text', $m['alt_text'], ['help' => 'Describes the image for screen readers and search engines.']) ?>
                        <?= Form::input('title', 'Title', $m['title']) ?>
                        <?= Form::textarea('caption', 'Caption', $m['caption'], ['rows' => 2]) ?>
                        <?= Form::select('folder_id', 'Folder', array_column($folders, 'name', 'id'), (string) $m['folder_id'], ['empty' => 'No folder']) ?>
                        <button class="btn btn-primary btn-block" type="submit">Save details</button>
                    </form>
                <?php endif; ?>
                <?php if (can('media.delete')): ?>
                    <form method="post" action="/admin/media/<?= (int) $m['id'] ?>/delete" class="mt-1" data-confirm="Delete this file permanently?<?= $usage ? ' It is still in use and those places will show a broken image.' : '' ?>">
                        <?= csrf_field() ?><?php if ($usage): ?><input type="hidden" name="force" value="1"><?php endif; ?>
                        <button class="btn btn-danger-ghost btn-block" type="submit"><?= Icons::svg('trash', 'icon', 16) ?> Delete file</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </aside>
</div>
