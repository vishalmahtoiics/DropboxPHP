<h1 class="auth-title">Save your recovery codes</h1>
<p class="muted">Each code signs you in once if you lose your phone. Store them somewhere safe — they will not be shown again.</p>
<ol class="recovery-codes">
    <?php foreach ($codes as $code): ?>
        <li><code><?= e($code) ?></code></li>
    <?php endforeach; ?>
</ol>
<button class="btn btn-secondary btn-block" type="button" data-copy="<?= e(implode("\n", $codes)) ?>">Copy codes</button>
<a class="btn btn-primary btn-block mt-1" href="/admin">I have saved them — continue</a>
<script nonce="<?= e(nonce()) ?>">
document.querySelector('[data-copy]').addEventListener('click', function () {
    navigator.clipboard.writeText(this.dataset.copy).then(() => { this.textContent = 'Copied'; });
});
</script>
