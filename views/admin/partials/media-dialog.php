<dialog id="media-dialog" class="wide" aria-labelledby="media-dialog-title">
    <div class="dialog-head">
        <h2 id="media-dialog-title">Choose media</h2>
        <button class="btn btn-ghost btn-icon" type="button" data-close-dialog aria-label="Close"><?= App\Cms\Icons::svg('x') ?></button>
    </div>
    <div class="dialog-body">
        <div class="row mb-2">
            <input class="input search grow" type="search" placeholder="Search by name or alt text" data-media-search>
            <label class="btn btn-primary">
                <?= App\Cms\Icons::svg('upload', 'icon', 16) ?> Upload
                <input type="file" hidden multiple data-media-upload accept="image/*,video/mp4,video/webm,application/pdf">
            </label>
        </div>
        <div class="dropzone mb-2" data-media-drop>Drop files here to upload</div>
        <div class="media-grid" data-media-grid><p class="muted">Loading…</p></div>
    </div>
    <div class="dialog-foot">
        <span class="muted small grow" data-media-status></span>
        <button class="btn btn-secondary" type="button" data-close-dialog>Cancel</button>
    </div>
</dialog>
