<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Session;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\Mfa;

/** The signed-in user's profile, password, two-factor and sessions. */
final class ProfileController extends AdminController
{
    public function index(): Response
    {
        $u = DB::first('SELECT * FROM users WHERE id = ?', [$this->me['id']]);
        return $this->screen('staff/profile', [
            'title' => 'Profile & security',
            'u' => $u,
            'sessions' => DB::all('SELECT * FROM auth_sessions WHERE user_id = ? AND revoked_at IS NULL AND expires_at > NOW() ORDER BY last_seen_at DESC', [$this->me['id']]),
            'codesLeft' => Mfa::remainingRecoveryCodes((int) $this->me['id']),
            'activity' => DB::all('SELECT * FROM audit_logs WHERE actor_id = ? ORDER BY created_at DESC LIMIT 15', [$this->me['id']]),
            'narrow' => true,
        ]);
    }

    public function update(): Response
    {
        $d = $this->validate(['name' => 'required|max:160', 'job_title' => 'nullable|max:120', 'department' => 'nullable|max:120', 'phone' => 'nullable|max:40',
            'timezone' => 'nullable|max:64', 'bio' => 'nullable|max:2000', 'linkedin_url' => 'nullable|absurl|max:500', 'avatar_url' => 'nullable|max:1000']);
        if ($d['timezone'] && !in_array($d['timezone'], timezone_identifiers_list(), true)) {
            throw ValidationException::with('timezone', 'Choose a valid timezone.');
        }
        DB::update('users', [
            'name' => Sanitizer::line((string) $d['name'], 160), 'job_title' => $d['job_title'] ?: null, 'department' => $d['department'] ?: null,
            'phone' => $d['phone'] ?: null, 'timezone' => $d['timezone'] ?: null, 'bio' => Sanitizer::text((string) $d['bio']) ?: null,
            'linkedin_url' => $d['linkedin_url'] ?: null, 'avatar_url' => Sanitizer::url((string) $d['avatar_url'], true) ?: null,
        ], ['id' => $this->me['id']]);
        Audit::record('updated', 'Profile', $this->me['id']);
        $this->flash('Profile saved.');
        return $this->redirect('/admin/profile');
    }

    public function password(): Response
    {
        $current = (string) ($this->request->body['current_password'] ?? '');
        $new = (string) ($this->request->body['password'] ?? '');
        $confirm = (string) ($this->request->body['password_confirmation'] ?? '');
        $hash = (string) DB::value('SELECT password_hash FROM users WHERE id = ?', [$this->me['id']]);
        if (!\App\Core\RateLimiter::hit('pwchange:' . $this->me['id'], 5, 900)) {
            throw ValidationException::with('current_password', 'Too many attempts. Try again in 15 minutes.');
        }
        if (!password_verify($current, $hash)) {
            throw ValidationException::with('current_password', 'Your current password is not correct.');
        }
        if ($err = Auth::passwordError($new, (string) $this->me['email'])) {
            throw ValidationException::with('password', $err);
        }
        if ($new !== $confirm) {
            throw ValidationException::with('password_confirmation', 'The passwords do not match.');
        }
        if (password_verify($new, $hash)) {
            throw ValidationException::with('password', 'Choose a password you have not used here before.');
        }
        DB::update('users', ['password_hash' => Auth::hash($new), 'password_changed_at' => now()], ['id' => $this->me['id']]);
        Auth::revokeAll((int) $this->me['id'], 'password-changed', (string) $this->me['session_id']);
        Audit::record('password-changed', 'User', $this->me['id']);
        $this->flash('Password changed. Your other sessions were signed out.');
        return $this->redirect('/admin/profile');
    }

    public function revokeSessions(): Response
    {
        $sid = $this->request->str('session');
        if ($sid !== '') {
            DB::query("UPDATE auth_sessions SET revoked_at = NOW(), revoked_reason = 'revoked-by-user' WHERE id = ? AND user_id = ? AND id <> ?", [$sid, $this->me['id'], $this->me['session_id']]);
        } else {
            Auth::revokeAll((int) $this->me['id'], 'revoked-by-user', (string) $this->me['session_id']);
        }
        Audit::record('sessions-revoked', 'User', $this->me['id']);
        $this->flash('Signed out of the selected session(s).');
        return $this->redirect('/admin/profile#sessions');
    }

    public function regenerateCodes(): Response
    {
        $codes = Mfa::regenerateRecoveryCodes((int) $this->me['id']);
        Audit::record('recovery-codes-regenerated', 'User', $this->me['id']);
        Session::flash('recovery_codes', $codes);
        return $this->redirect('/auth/recovery-codes');
    }
}
