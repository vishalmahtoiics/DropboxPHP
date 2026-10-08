<h1 class="auth-title">Sign in</h1>
<p class="muted">Use your staff account. You will confirm with your authenticator app next.</p>
<form method="post" action="<?= e(config('app.login_path')) ?>" class="stack" autocomplete="on">
    <?= csrf_field() ?>
    <label class="field">
        <span class="label">Email</span>
        <input class="input" type="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="username" maxlength="254">
    </label>
    <label class="field">
        <span class="label">Password</span>
        <input class="input" type="password" name="password" required autocomplete="current-password" maxlength="200">
    </label>
    <button class="btn btn-primary btn-block" type="submit">Continue</button>
</form>
