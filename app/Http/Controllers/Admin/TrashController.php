<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Http\AdminController;
use App\Services\Countries;
use App\Services\Slugs;

/**
 * Recycle bin. Anything a lead can reference is soft-deleted, so attribution
 * and history survive. Restoring gives the item its address back (if still
 * free); purging deletes it for good where nothing depends on it.
 */
final class TrashController extends AdminController
{
    /** type => [table, label column, permission, has slug, country scoped] */
    private const TYPES = [
        'page' => ['pages', 'title', 'pages.delete', true, true],
        'product' => ['products', 'name', 'products.delete', true, false],
        'post' => ['blog_posts', 'title', 'blog.delete', true, true],
        'form' => ['forms', 'name', 'forms.delete', true, false],
        'lead' => ['leads', 'name', 'leads.delete', false, true],
        'customer' => ['customers', 'name', 'customers.delete', false, false],
        'popup' => ['popups', 'name', 'marketing.manage', false, false],
        'magnet' => ['lead_magnets', 'title', 'marketing.manage', true, false],
        'category' => ['product_categories', 'name', 'products.delete', true, false],
        'brand' => ['brands', 'name', 'products.delete', true, false],
    ];

    public function index(): Response
    {
        $groups = [];
        $countryIds = Countries::accessibleIds();
        foreach (self::TYPES as $type => [$table, $label, $perm, , $scoped]) {
            if (!Auth::can($perm)) {
                continue;
            }
            $sql = "SELECT id, `$label` AS label, deleted_at" . ($scoped ? ', country_id' : '') . " FROM `$table` WHERE deleted_at IS NOT NULL";
            $params = [];
            if ($scoped) {
                [$in, $params] = DB::in($countryIds);
                $sql .= " AND country_id IN ($in)";
            }
            $rows = DB::all($sql . ' ORDER BY deleted_at DESC LIMIT 200', $params);
            if ($rows) {
                $groups[$type] = $rows;
            }
        }
        if (!Auth::canAny(array_column(self::TYPES, 2))) {
            throw HttpException::forbidden();
        }
        return $this->screen('trash', ['title' => 'Recycle bin', 'groups' => $groups]);
    }

    public function restore(string $type, string $id): Response
    {
        [$table, $label, $perm, , $scoped] = $this->type($type);
        $this->authorize($perm);
        $row = $this->row($table, $this->id($id), $scoped);
        $slug = $this->restoreRow($type, $row);
        $this->flash('"' . $row[$label] . '" restored' . ($slug !== null && $slug !== Slugs::untrashed((string) $row['slug']) ? ' as /' . $slug . ' (its old address was taken)' : '') . '. It is a draft until you publish it again.');
        return $this->redirect('/admin/trash');
    }

    public function purge(string $type, string $id): Response
    {
        [$table, $label, $perm, , $scoped] = $this->type($type);
        $this->authorize($perm);
        $row = $this->row($table, $this->id($id), $scoped);
        if (!$this->purgeRow($type, $row)) {
            $this->flash('"' . $row[$label] . '" is still referenced (for example by leads or subscriptions) and stays in the recycle bin.', 'error');
            return $this->redirect('/admin/trash');
        }
        $this->flash('Deleted permanently.');
        return $this->redirect('/admin/trash');
    }

    /** Restore or permanently delete the ticked items of one type ("restore:page", "purge:page"). */
    public function bulk(): Response
    {
        [$verb, $type] = array_pad(explode(':', $this->request->str('action'), 2), 2, '');
        $ids = $this->bulkIds();
        if (!$ids || !in_array($verb, ['restore', 'purge'], true)) {
            $this->flash('Select items and an action.', 'warning');
            return $this->redirect('/admin/trash');
        }
        [$table, $label, $perm, , $scoped] = $this->type($type);
        $this->authorize($perm);
        [$in, $params] = DB::in($ids);
        $done = 0;
        $kept = [];
        foreach (DB::all("SELECT * FROM `$table` WHERE id IN ($in) AND deleted_at IS NOT NULL", $params) as $row) {
            if ($scoped && !in_array((int) $row['country_id'], Countries::accessibleIds(), true)) {
                continue;
            }
            if ($verb === 'restore') {
                $this->restoreRow($type, $row);
            } elseif (!$this->purgeRow($type, $row)) {
                $kept[] = '"' . $row[$label] . '" is still referenced';
                continue;
            }
            $done++;
        }
        $this->flash($done . ' item(s) ' . ($verb === 'restore' ? 'restored as drafts' : 'deleted permanently') . '.' . ($kept ? ' Kept: ' . implode('; ', array_slice($kept, 0, 5)) . '.' : ''), $kept && !$done ? 'warning' : 'success');
        return $this->redirect('/admin/trash');
    }

    /** @return ?string the restored address, for types that have one */
    private function restoreRow(string $type, array $row): ?string
    {
        [$table, $label, , $hasSlug, $scoped] = self::TYPES[$type];
        $update = ['deleted_at' => null];
        if ($hasSlug) {
            $scope = $scoped ? ['country_id' => (int) $row['country_id']] : [];
            $update['slug'] = Slugs::unique($table, Slugs::untrashed((string) $row['slug']), (int) $row['id'], $scope);
        }
        DB::update($table, $update, ['id' => $row['id']]);
        Audit::record('restored', ucfirst($type), $row['id'], (string) $row[$label]);
        return $update['slug'] ?? null;
    }

    private function purgeRow(string $type, array $row): bool
    {
        [$table, $label] = self::TYPES[$type];
        try {
            DB::delete($table, ['id' => $row['id']]);
        } catch (\PDOException) {
            return false;
        }
        Audit::record('purged', ucfirst($type), $row['id'], (string) $row[$label]);
        return true;
    }

    /** @return array{0:string,1:string,2:string,3:bool,4:bool} */
    private function type(string $type): array
    {
        if (!isset(self::TYPES[$type])) {
            throw HttpException::notFound();
        }
        return self::TYPES[$type];
    }

    /** @return array<string,mixed> */
    private function row(string $table, int $id, bool $scoped): array
    {
        $row = DB::first("SELECT * FROM `$table` WHERE id = ? AND deleted_at IS NOT NULL", [$id]);
        if (!$row) {
            throw HttpException::notFound();
        }
        if ($scoped) {
            Countries::assertAccess((int) $row['country_id']);
        }
        return $row;
    }
}
