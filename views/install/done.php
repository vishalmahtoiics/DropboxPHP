<?php /** @var string $key @var bool $existing @var string $login_path @var string $url */ ?>
<h1 class="auth-title">✓ <?= $existing ? 'Site reconnected' : 'Website installed' ?></h1>

<div class="alert alert-warning"><span><strong>Save your encryption key</strong> somewhere safe (a password manager). You need it only if you move the site to another server. It is also in Admin → Website settings → System.</span></div>
<label class="field"><span class="label">Encryption key</span>
    <input class="input code" type="text" value="<?= e($key) ?>" readonly>
</label>

<dl class="dl small">
    <dt>Website</dt><dd><a href="<?= e($url) ?>/"><?= e($url) ?></a></dd>
    <dt>Admin sign-in</dt><dd><?= e($url . $login_path) ?></dd>
</dl>
<p class="muted small">Next: sign in, scan the QR code with an authenticator app (Google Authenticator, Microsoft Authenticator…), and save your recovery codes. Then set up the cron job shown in Admin → Website settings → System.</p>
<a class="btn btn-primary btn-block" href="<?= e($login_path) ?>">Go to sign-in</a>
