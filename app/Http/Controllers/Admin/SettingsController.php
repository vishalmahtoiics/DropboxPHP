<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Installation;
use App\Core\Migrator;
use App\Core\Permissions;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\EmailTemplates;
use App\Services\Installer;
use App\Services\Mailer;
use App\Services\Settings;
use Throwable;

final class SettingsController extends AdminController
{
    private const GROUPS = ['website', 'footer', 'email', 'storage'];

    public function index(): Response
    {
        $this->authorize('settings.manage');
        return $this->screen('settings/index', [
            'title' => 'Website settings',
            's' => Settings::group('website'),
            'footer' => Settings::group('footer'),
            'email' => Settings::group('email'),
            'hasPassword' => Settings::secret('email', 'password') !== '',
            'mailConfigured' => Mailer::isConfigured(),
            'forms' => \App\Services\Options::forms(),
            'storage' => Settings::group('storage'),
            'hasStorageSecret' => Settings::secret('storage', 'secret_key') !== '',
            'pendingMigrations' => Migrator::pending(),
            'isSuperAdmin' => ($this->user()['role_slug'] ?? '') === 'super-admin',
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function save(string $group): Response
    {
        $this->authorize('settings.manage');
        if (!in_array($group, self::GROUPS, true)) {
            throw HttpException::notFound();
        }
        $input = $this->request->body;
        unset($input['_token']);
        if ($group === 'email') {
            if (($input['encryption'] ?? '') !== '' && !in_array($input['encryption'], ['tls', 'ssl', 'none'], true)) {
                throw ValidationException::with('encryption', 'Choose TLS, SSL or none.');
            }
            foreach (['from_email', 'reply_to'] as $k) {
                if (($input[$k] ?? '') !== '' && !filter_var($input[$k], FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::with($k, 'Enter a valid email address.');
                }
            }
            $emails = array_filter(array_map('trim', explode(',', (string) ($input['sales_emails'] ?? ''))));
            foreach ($emails as $e) {
                if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::with('sales_emails', "\"$e\" is not a valid email address.");
                }
            }
            $input['sales_emails'] = implode(', ', $emails);
            if (!empty($input['clear_password'])) {
                DB::query("UPDATE settings SET data = JSON_SET(data, '$.password', '') WHERE `group` = 'email'");
                unset($input['password']);
            }
        }
        if ($group === 'storage') {
            $this->validateStorage($input);
        }
        if ($group === 'website' && array_key_exists('trusted_proxies', $input)) {
            $list = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $input['trusted_proxies']) ?: []));
            foreach ($list as $ip) {
                if ($ip !== '*' && !filter_var($ip, FILTER_VALIDATE_IP)) {
                    throw ValidationException::with('trusted_proxies', "\"$ip\" is not an IP address.");
                }
            }
            $input['trusted_proxies'] = implode(', ', $list);
        }
        // Each settings form posts only its own section and lists its keys in
        // _fields: listed keys that are absent are unticked switches; every
        // other key keeps its stored value.
        $listed = array_filter(explode(',', (string) ($input['_fields'] ?? '')));
        foreach ($listed as $key) {
            if (!array_key_exists($key, $input)) {
                $input[$key] = '';
            }
        }
        $before = Settings::group($group);
        $saved = Settings::save($group, $input, true);
        Audit::record('updated', 'Settings', $group, 'Changed ' . implode(', ', array_keys(array_diff_assoc(array_map('json_encode', $saved), array_map('json_encode', $before + array_fill_keys(array_keys($saved), null))))), $before, $saved);
        $this->flash('Settings saved.');
        return $this->redirect('/admin/settings#' . ($this->request->str('_tab') ?: $group));
    }

    public function testEmail(): Response
    {
        $this->authorize('settings.manage');
        $mode = $this->request->str('mode');
        try {
            if ($mode === 'connect') {
                Mailer::testConnection();
                $this->flash('Connected and signed in to the SMTP server successfully.');
            } else {
                $to = $this->request->str('to') ?: $this->me['email'];
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::with('to', 'Enter a valid address.');
                }
                Mailer::sendOrFail($to, 'Test email from ' . Settings::get('website', 'site_name'), '<p>This is a test. Your email settings work.</p>');
                $this->flash("Test email sent to $to.");
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->flash('Email test failed: ' . $e->getMessage(), 'error');
        }
        return $this->redirect('/admin/settings#email');
    }

    public function templates(): Response
    {
        $this->authorize('settings.manage');
        EmailTemplates::seed();
        return $this->screen('settings/templates', ['title' => 'Email templates', 'rows' => DB::all('SELECT * FROM email_templates ORDER BY id'), 'defaults' => EmailTemplates::DEFAULTS]);
    }

    public function saveTemplate(string $id): Response
    {
        $this->authorize('settings.manage');
        $tpl = DB::first('SELECT * FROM email_templates WHERE id = ?', [$this->id($id)]);
        if (!$tpl) {
            throw HttpException::notFound();
        }
        if ($this->request->str('reset') === '1' && isset(EmailTemplates::DEFAULTS[$tpl['key']])) {
            [, $subject, $body] = EmailTemplates::DEFAULTS[$tpl['key']];
            DB::update('email_templates', ['subject' => $subject, 'body' => $body], ['id' => $tpl['id']]);
            $this->flash('Template reset to the default.');
            return $this->redirect('/admin/email-templates#t' . $tpl['id']);
        }
        $d = $this->validate(['subject' => 'required|max:250', 'body' => 'required|max:50000', 'is_active' => 'bool']);
        DB::update('email_templates', ['subject' => Sanitizer::line((string) $d['subject'], 250), 'body' => Sanitizer::html((string) $d['body']), 'is_active' => $d['is_active'] ? 1 : 0], ['id' => $tpl['id']]);
        Audit::record('updated', 'EmailTemplate', $tpl['id'], $tpl['key']);
        $this->flash('Template saved.');
        return $this->redirect('/admin/email-templates#t' . $tpl['id']);
    }

    /** @param array<string, mixed> $input */
    private function validateStorage(array &$input): void
    {
        $driver = (string) ($input['driver'] ?? 'local');
        if (!in_array($driver, ['local', 's3', 'r2'], true)) {
            throw ValidationException::with('driver', 'Choose local, Amazon S3 or Cloudflare R2.');
        }
        if (!in_array((string) ($input['path_style'] ?? 'auto'), ['auto', 'on', 'off'], true)) {
            $input['path_style'] = 'auto';
        }
        if ($driver === 'local') {
            return;
        }
        $errors = [];
        if (!preg_match('/^[a-z0-9][a-z0-9.\-]{1,62}$/', (string) ($input['bucket'] ?? ''))) {
            $errors['bucket'] = 'Enter the bucket name.';
        }
        if (trim((string) ($input['access_key'] ?? '')) === '') {
            $errors['access_key'] = 'Enter the access key ID.';
        }
        if (trim((string) ($input['secret_key'] ?? '')) === '' && Settings::secret('storage', 'secret_key') === '') {
            $errors['secret_key'] = 'Enter the secret access key.';
        }
        if ($driver === 'r2' && !preg_match('#^https://[a-z0-9.-]+$#i', (string) ($input['endpoint'] ?? ''))) {
            $errors['endpoint'] = 'R2 needs its endpoint, e.g. https://<account-id>.r2.cloudflarestorage.com';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
    }

    /** Site address, sign-in address and timezone — recorded by the installer. */
    public function saveSystem(): Response
    {
        $this->authorize('settings.manage');
        $url = rtrim(trim($this->request->str('url')), '/');
        $login = '/' . trim(strtolower(trim($this->request->str('login_path'))), '/');
        $tz = $this->request->str('timezone');
        $errors = array_filter([
            'url' => Installer::urlError($url),
            'login_path' => Installer::loginPathError($login),
            'timezone' => in_array($tz, timezone_identifiers_list(), true) ? null : 'Choose a timezone.',
        ]);
        if ($errors) {
            throw new ValidationException($errors);
        }
        $before = array_intersect_key(Installation::read(), ['url' => 1, 'login_path' => 1, 'timezone' => 1]);
        $after = ['url' => $url, 'login_path' => $login, 'timezone' => $tz];
        Installation::write($after);
        Audit::record('updated', 'Settings', 'system', 'Changed site address, sign-in address or timezone', $before, $after);
        $this->flash(($before['login_path'] ?? '') !== $login ? "Saved. The sign-in address is now $login — bookmark it." : 'Saved.');
        return $this->redirect('/admin/settings#system');
    }

    /** Apply database updates shipped with new code (same as the console "migrate"). */
    public function migrate(): Response
    {
        $this->authorize('settings.manage');
        $applied = Migrator::migrate();
        Permissions::sync();
        Audit::record('updated', 'Settings', 'system', 'Applied database updates: ' . ($applied ? implode(', ', $applied) : 'none'));
        $this->flash($applied ? count($applied) . ' database update(s) applied.' : 'The database is already up to date.');
        return $this->redirect('/admin/settings#system');
    }

    /** Show the encryption key to a Super Admin who re-enters their password. */
    public function revealKey(): Response
    {
        $this->authorize('settings.manage');
        $user = $this->user();
        if (($user['role_slug'] ?? '') !== 'super-admin') {
            throw HttpException::forbidden();
        }
        $hash = (string) DB::value('SELECT password_hash FROM users WHERE id = ?', [(int) $user['id']]);
        $password = $this->request->input('password', '');
        if (!is_string($password) || !password_verify($password, $hash)) {
            Audit::record('denied', 'Settings', 'system', 'Wrong password when viewing the encryption key');
            throw ValidationException::with('password', 'That password is not correct.');
        }
        Audit::record('viewed', 'Settings', 'system', 'Viewed the encryption key');
        return $this->screen('settings/key', ['title' => 'Encryption key', 'key' => (string) config('app.key')])
            ->header('Cache-Control', 'no-store');
    }
}
