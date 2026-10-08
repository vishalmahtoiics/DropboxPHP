<?= App\Core\View::partial('admin/partials/page-head', ['heading' => 'Encryption key', 'sub' => 'Viewing it has been recorded in the audit log.']) ?>
<div class="card"><div class="card-body stack">
    <div class="alert alert-warning"><span>Anyone with this key and a copy of the database can read stored secrets. Keep it in a password manager, never in email or chat.</span></div>
    <label class="field"><span class="label">Key</span><input class="input code" readonly value="<?= e($key) ?>"></label>
    <p><a class="btn btn-secondary" href="/admin/settings#system">Back to settings</a></p>
</div></div>
