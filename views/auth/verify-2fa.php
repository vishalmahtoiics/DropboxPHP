<h1 class="auth-title">Confirm it's you</h1>
<p class="muted">Enter the 6-digit code from your authenticator app for <strong><?= e($user['email']) ?></strong>. Lost your phone? Use one of your recovery codes.</p>
<form method="post" action="/auth/verify-2fa" class="stack" autocomplete="off">
    <?= csrf_field() ?>
    <label class="field">
        <span class="label">Authentication code</span>
        <input class="input otp" type="text" name="code" maxlength="12" required autofocus autocomplete="one-time-code" inputmode="text">
    </label>
    <button class="btn btn-primary btn-block" type="submit">Verify</button>
</form>
<form method="post" action="/auth/logout" class="center mt-2">
    <?= csrf_field() ?>
    <button class="link-btn" type="submit">Sign out</button>
</form>
