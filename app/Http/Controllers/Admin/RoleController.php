<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Permissions;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\Slugs;

/**
 * Roles and permissions. A role can only be created or edited below your own
 * rank, and it can only hold permissions you hold yourself — so this screen
 * can never be used to escalate.
 */
final class RoleController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('staff.manage');
        $roles = DB::all('SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.deleted_at IS NULL) AS users FROM roles r ORDER BY r.`rank`, r.name');
        $edit = $this->request->int('edit') ? DB::first('SELECT * FROM roles WHERE id = ?', [$this->request->int('edit')]) : null;
        $held = $edit ? DB::column('SELECT p.`key` FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?', [$edit['id']]) : [];
        return $this->screen('staff/roles', ['title' => 'Roles & permissions', 'roles' => $roles, 'edit' => $edit, 'held' => $held, 'new' => $this->request->queryStr('new') === '1']);
    }

    public function save(): Response
    {
        $this->authorize('staff.manage');
        $d = $this->validate(['id' => 'nullable|int', 'name' => 'required|max:80', 'description' => 'nullable|max:255', 'rank' => 'required|int', 'permissions' => 'array']);
        $myRank = (int) $this->me['role_rank'];
        if ((int) $d['rank'] <= $myRank) {
            throw ValidationException::with('rank', 'Rank must be higher (less privileged) than your own (' . $myRank . ').');
        }
        $existing = $d['id'] ? DB::first('SELECT * FROM roles WHERE id = ?', [$d['id']]) : null;
        if ($existing && ((int) $existing['rank'] <= $myRank || $existing['slug'] === 'super-admin')) {
            throw HttpException::forbidden();
        }
        $grantable = array_intersect(array_keys(Permissions::ALL), $this->me['permissions']);
        $perms = array_values(array_intersect((array) $d['permissions'], $grantable));
        $name = Sanitizer::line((string) $d['name'], 80);
        if (DB::value('SELECT id FROM roles WHERE name = ? AND id <> ?', [$name, $existing['id'] ?? 0])) {
            throw ValidationException::with('name', 'A role with this name exists.');
        }
        $id = DB::transaction(static function () use ($existing, $name, $d, $perms): int {
            $row = ['name' => $name, 'description' => $d['description'] ?: null, 'rank' => (int) $d['rank']];
            if ($existing) {
                DB::update('roles', $row, ['id' => $existing['id']]);
                $id = (int) $existing['id'];
            } else {
                $row['slug'] = Slugs::unique('roles', Slugs::make($name));
                $id = DB::insert('roles', $row);
            }
            DB::delete('role_permissions', ['role_id' => $id]);
            foreach ($perms as $key) {
                DB::query('INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE `key` = ?', [$id, $key]);
            }
            return $id;
        });
        Audit::record($existing ? 'updated' : 'created', 'Role', $id, $name . ': ' . implode(', ', $perms));
        $this->flash('Role saved. Changes apply to its members on their next request.');
        return $this->redirect('/admin/staff/roles?edit=' . $id);
    }

    public function destroy(string $id): Response
    {
        $this->authorize('staff.manage');
        $role = DB::first('SELECT * FROM roles WHERE id = ?', [$this->id($id)]);
        if (!$role || (int) $role['is_system'] === 1 || (int) $role['rank'] <= (int) $this->me['role_rank']) {
            throw HttpException::forbidden();
        }
        if (DB::value('SELECT COUNT(*) FROM users WHERE role_id = ? AND deleted_at IS NULL', [$role['id']])) {
            $this->flash('Move this role\'s members to another role first.', 'error');
            return $this->redirect('/admin/staff/roles?edit=' . $role['id']);
        }
        DB::delete('roles', ['id' => $role['id']]);
        Audit::record('deleted', 'Role', $role['id'], $role['name']);
        $this->flash('Role deleted.');
        return $this->redirect('/admin/staff/roles');
    }
}
