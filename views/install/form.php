<?php
use App\Http\Form;

/** @var list<array{0:string,1:bool,2:string}> $checks */
$failed = array_filter($checks, static fn ($c) => !$c[1]);
$errors = errors();
?>
<h1 class="auth-title">Install the website</h1>
<p class="muted">Fill this in once. Everything is saved for you — there are no files to edit.</p>

<details class="install-checks"<?= $failed ? ' open' : '' ?>>
    <summary><?= $failed ? '⚠ ' . count($failed) . ' server check(s) failed' : '✓ Server checks passed' ?></summary>
    <ul class="check-list">
        <?php foreach ($checks as [$label, $ok, $detail]): ?>
            <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '✗' ?> <?= e($label) ?> <span class="small muted">— <?= e($detail) ?></span></li>
        <?php endforeach; ?>
    </ul>
</details>

<?php if ($ready): ?>
<form method="post" action="/install" class="stack" autocomplete="off">
    <?= csrf_field() ?>

    <h2 class="install-step">1. Website</h2>
    <?= Form::input('url', 'Website address', $defaults['url'], ['required' => true, 'help' => 'The address visitors use, e.g. https://www.example.com']) ?>

    <h2 class="install-step">2. Database</h2>
    <p class="help">Create these in your hosting panel (cPanel → MySQL Databases), and add the user to the database with ALL PRIVILEGES.</p>
    <div class="grid grid-2">
        <?= Form::input('db_name', 'Database name', '', ['required' => true]) ?>
        <?= Form::input('db_user', 'Database user', '', ['required' => true]) ?>
        <?= Form::input('db_pass', 'Database password', '', ['type' => 'password', 'autocomplete' => 'new-password']) ?>
        <?= Form::input('db_host', 'Database host', $defaults['db_host'], ['help' => 'Usually localhost']) ?>
    </div>
    <details><summary class="small">Advanced: port</summary><?= Form::input('db_port', 'Port', $defaults['db_port'], ['type' => 'number']) ?></details>

    <?php if (isset($errors['existing_key'])): ?>
        <h2 class="install-step">Existing site found</h2>
        <?= Form::input('existing_key', 'Encryption key of the existing site', '', ['required' => true, 'placeholder' => 'base64:…', 'help' => 'Shown when the site was first installed, and in Admin → Website settings → System.']) ?>
    <?php else: ?>
        <h2 class="install-step">3. Your admin account</h2>
        <div class="grid grid-2">
            <?= Form::input('admin_name', 'Your name', '', ['autocomplete' => 'name']) ?>
            <?= Form::input('admin_email', 'Email', '', ['type' => 'email', 'autocomplete' => 'username']) ?>
            <?= Form::input('admin_password', 'Password', '', ['type' => 'password', 'autocomplete' => 'new-password', 'help' => '10+ characters with upper- and lower-case letters and a number.']) ?>
            <?= Form::input('admin_password_confirmation', 'Repeat password', '', ['type' => 'password', 'autocomplete' => 'new-password']) ?>
        </div>
    <?php endif; ?>

    <h2 class="install-step"><?= isset($errors['existing_key']) ? '' : '4. ' ?>Options</h2>
    <?= Form::input('login_path', 'Admin sign-in address', ltrim($defaults['login_path'], '/'), ['prefix' => '/', 'help' => 'Keep it secret: /admin is hidden and shows "not found".']) ?>
    <?= Form::select('timezone', 'Timezone', array_combine($timezones, $timezones), $defaults['timezone']) ?>
    <?php if (!isset($errors['existing_key'])): ?><?= Form::toggle('demo', 'Add demo pages, products and blog posts', true) ?><?php endif; ?>

    <button class="btn btn-primary btn-block" type="submit">Install</button>
</form>
<?php else: ?>
    <div class="alert alert-error"><span>Fix the failed checks above, then reload this page.</span></div>
<?php endif; ?>
