<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\Countries;
use App\Services\Mfa;

/**
 * Staff accounts. Role hierarchy is enforced by rank (lower = more
 * privileged): you can only create or edit accounts with a strictly higher
 * rank than your own, so nobody can promote themselves or a peer. Nobody can
 * change their own role or suspend themselves, and the last active Super
 * Admin cannot be removed.
 */
final class StaffController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('staff.manage');
        $rows = DB::all(
            "SELECT u.*, r.name AS role_name, r.`rank`,
                    (SELECT COUNT(*) FROM auth_sessions s WHERE s.user_id = u.id AND s.revoked_at IS NULL AND s.expires_at > NOW()) AS sessions,
                    (SELECT COUNT(*) FROM leads l WHERE l.assigned_to_id = u.id AND l.deleted_at IS NULL AND l.status NOT IN ('WON','LOST','SPAM')) AS open_leads
               FROM users u JOIN roles r ON r.id = u.role_id WHERE u.deleted_at IS NULL ORDER BY r.`rank`, u.name"
        );
        return $this->screen('staff/index', ['title' => 'Staff', 'rows' => $rows]);
    }

    public function create(): Response
    {
        $this->authorize('staff.manage');
        return $this->editor(null);
    }

    public function edit(string $id): Response
    {
        $this->authorize('staff.manage');
        return $this->editor($this->find($this->id($id)));
    }

    private function editor(?array $u): Response
    {
        return $this->screen('staff/edit', [
            'title' => $u ? $u['name'] : 'New staff member',
            'u' => $u,
            'roles' => array_column($this->assignableRoles(), 'name', 'id'),
            'markets' => Countries::all(),
            'userMarkets' => $u ? array_map('intval', DB::column('SELECT country_id FROM user_countries WHERE user_id = ?', [$u['id']])) : [],
            'sessions' => $u ? DB::all('SELECT * FROM auth_sessions WHERE user_id = ? AND revoked_at IS NULL AND expires_at > NOW() ORDER BY last_seen_at DESC', [$u['id']]) : [],
            'isSelf' => $u && (int) $u['id'] === (int) $this->me['id'],
            'canManage' => !$u || $this->outranks($u),
            'narrow' => true,
        ]);
    }

    public function store(): Response
    {
        $this->authorize('staff.manage');
        $d = $this->validate(['name' => 'required|max:160', 'email' => 'required|email', 'role_id' => 'required|int', 'job_title' => 'nullable|max:120',
            'phone' => 'nullable|max:40', 'password' => 'required|max:200', 'all_countries' => 'bool', 'countries' => 'array']);
        $this->assertAssignable((int) $d['role_id']);
        if (DB::value('SELECT id FROM users WHERE email = ?', [$d['email']])) {
            throw ValidationException::with('email', 'An account with this email already exists.');
        }
        if ($err = Auth::passwordError((string) $d['password'], (string) $d['email'])) {
            throw ValidationException::with('password', $err);
        }
        $id = DB::transaction(function () use ($d): int {
            $id = DB::insert('users', [
                'name' => Sanitizer::line((string) $d['name'], 160), 'email' => $d['email'], 'role_id' => $d['role_id'],
                'job_title' => $d['job_title'] ?: null, 'phone' => $d['phone'] ?: null, 'password_hash' => Auth::hash((string) $d['password']),
                'password_changed_at' => now(), 'status' => 'ACTIVE', 'two_factor_required' => 1, 'all_countries' => $d['all_countries'] ? 1 : 0,
            ]);
            $this->saveMarkets($id, $d);
            return $id;
        });
        Audit::record('created', 'User', $id, $d['email'] . ' (' . DB::value('SELECT name FROM roles WHERE id = ?', [$d['role_id']]) . ')');
        $this->flash('Account created. Share the password securely; they will set up two-factor authentication at first sign-in.');
        return $this->redirect('/admin/staff/' . $id);
    }

    public function update(string $id): Response
    {
        $this->authorize('staff.manage');
        $u = $this->find($this->id($id));
        $self = (int) $u['id'] === (int) $this->me['id'];
        if (!$self && !$this->outranks($u)) {
            throw HttpException::forbidden();
        }
        $d = $this->validate(['name' => 'required|max:160', 'email' => 'required|email', 'role_id' => 'nullable|int', 'job_title' => 'nullable|max:120',
            'phone' => 'nullable|max:40', 'status' => 'nullable|in:ACTIVE,SUSPENDED', 'password' => 'nullable|max:200', 'all_countries' => 'bool', 'countries' => 'array']);
        if (DB::value('SELECT id FROM users WHERE email = ? AND id <> ?', [$d['email'], $u['id']])) {
            throw ValidationException::with('email', 'Another account uses this email.');
        }
        $row = ['name' => Sanitizer::line((string) $d['name'], 160), 'email' => $d['email'], 'job_title' => $d['job_title'] ?: null, 'phone' => $d['phone'] ?: null];
        if (!$self) {
            if ($d['role_id'] && (int) $d['role_id'] !== (int) $u['role_id']) {
                $this->assertAssignable((int) $d['role_id']);
                $this->assertNotLastSuperAdmin($u);
                $row['role_id'] = $d['role_id'];
            }
            if ($d['status'] && $d['status'] !== $u['status']) {
                if ($d['status'] === 'SUSPENDED') {
                    $this->assertNotLastSuperAdmin($u);
                }
                $row['status'] = $d['status'];
            }
            $row['all_countries'] = $d['all_countries'] ? 1 : 0;
        }
        if ($d['password']) {
            if ($err = Auth::passwordError((string) $d['password'], (string) $d['email'])) {
                throw ValidationException::with('password', $err);
            }
            $row['password_hash'] = Auth::hash((string) $d['password']);
            $row['password_changed_at'] = now();
        }
        DB::transaction(function () use ($u, $row, $d, $self): void {
            DB::update('users', $row, ['id' => $u['id']]);
            if (!$self) {
                $this->saveMarkets((int) $u['id'], $d);
            }
            // A new password, role or suspension ends every other session at once.
            if (isset($row['password_hash']) || isset($row['role_id']) || ($row['status'] ?? '') === 'SUSPENDED') {
                Auth::revokeAll((int) $u['id'], 'account-changed', $self ? (string) $this->me['session_id'] : null);
            }
        });
        unset($row['password_hash']);
        Audit::record('updated', 'User', $u['id'], $u['email'], array_intersect_key($u, $row), $row);
        $this->flash('Account saved.');
        return $this->redirect('/admin/staff/' . $u['id']);
    }

    public function resetMfa(string $id): Response
    {
        $this->authorize('user.mfa.reset');
        $u = $this->find($this->id($id));
        if (!$this->outranks($u) && (int) $u['id'] !== (int) $this->me['id']) {
            throw HttpException::forbidden();
        }
        Mfa::reset((int) $u['id']);
        Auth::revokeAll((int) $u['id'], 'mfa-reset');
        Audit::record('mfa-reset', 'User', $u['id'], $u['email']);
        $this->flash('Authenticator removed. They will set up a new one at their next sign-in.');
        return $this->redirect('/admin/staff/' . $u['id']);
    }

    public function revokeSessions(string $id): Response
    {
        $this->authorize('staff.manage');
        $u = $this->find($this->id($id));
        if (!$this->outranks($u)) {
            throw HttpException::forbidden();
        }
        Auth::revokeAll((int) $u['id'], 'revoked-by-admin');
        Audit::record('sessions-revoked', 'User', $u['id'], $u['email']);
        $this->flash('Signed out everywhere.');
        return $this->redirect('/admin/staff/' . $u['id']);
    }

    public function destroy(string $id): Response
    {
        $this->authorize('staff.manage');
        $u = $this->find($this->id($id));
        if ((int) $u['id'] === (int) $this->me['id'] || !$this->outranks($u)) {
            throw HttpException::forbidden();
        }
        $this->assertNotLastSuperAdmin($u);
        DB::transaction(static function () use ($u): void {
            DB::update('users', ['deleted_at' => now(), 'status' => 'SUSPENDED', 'email' => 'deleted-' . $u['id'] . '-' . $u['email'], 'password_hash' => null], ['id' => $u['id']]);
            DB::update('leads', ['assigned_to_id' => null], ['assigned_to_id' => $u['id']]);
            Auth::revokeAll((int) $u['id'], 'deleted');
        });
        Audit::record('deleted', 'User', $u['id'], $u['email']);
        $this->flash('Account removed. Its leads are now unassigned; its history stays in the audit log.');
        return $this->redirect('/admin/staff');
    }

    private function saveMarkets(int $userId, array $d): void
    {
        DB::delete('user_countries', ['user_id' => $userId]);
        if (!$d['all_countries']) {
            $mine = Countries::accessibleIds();
            foreach ((array) $d['countries'] as $cid) {
                if (in_array((int) $cid, $mine, true)) {
                    DB::insert('user_countries', ['user_id' => $userId, 'country_id' => (int) $cid]);
                }
            }
        }
    }

    /** @return list<array<string,mixed>> roles strictly below the signed-in user */
    private function assignableRoles(): array
    {
        return DB::all('SELECT * FROM roles WHERE `rank` > ? ORDER BY `rank`, name', [(int) $this->me['role_rank']]);
    }

    private function assertAssignable(int $roleId): void
    {
        if (!in_array($roleId, array_map(static fn ($r) => (int) $r['id'], $this->assignableRoles()), true)) {
            throw ValidationException::with('role_id', 'You can only give roles below your own.');
        }
    }

    private function outranks(array $u): bool
    {
        return (int) $u['rank'] > (int) $this->me['role_rank'];
    }

    private function assertNotLastSuperAdmin(array $u): void
    {
        if ($u['role_slug'] === 'super-admin') {
            $count = (int) DB::value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'super-admin' AND u.status = 'ACTIVE' AND u.deleted_at IS NULL");
            if ($count <= 1) {
                throw ValidationException::with('role_id', 'This is the last active Super Admin. Create another one first.');
            }
        }
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $u = DB::first('SELECT u.*, r.name AS role_name, r.slug AS role_slug, r.`rank` FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.deleted_at IS NULL', [$id]);
        if (!$u) {
            throw HttpException::notFound();
        }
        return $u;
    }
}
