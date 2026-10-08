<h1 class="auth-title">Set up two-factor authentication</h1>
<p class="muted">Two-factor authentication is required for every staff account. Scan this code with Google Authenticator, Microsoft Authenticator, 1Password or Authy.</p>
<div class="qr-box"><?= $qr ?></div>
<details class="small">
    <summary>Can't scan? Enter this key instead</summary>
    <code class="secret-key"><?= e($secret) ?></code>
    <p class="muted small">Account: <?= e($user['email']) ?> · Type: time-based · 6 digits</p>
</details>
<form method="post" action="/auth/setup-2fa" class="stack" autocomplete="off">
    <?= csrf_field() ?>
    <label class="field">
        <span class="label">Enter the 6-digit code from the app</span>
        <input class="input otp" type="text" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus autocomplete="one-time-code">
    </label>
    <button class="btn btn-primary btn-block" type="submit">Turn on and continue</button>
</form>
<form method="post" action="/auth/logout" class="center mt-2">
    <?= csrf_field() ?>
    <button class="link-btn" type="submit">Sign out</button>
</form>
