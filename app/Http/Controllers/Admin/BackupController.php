<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Http\AdminController;
use App\Services\Backups;
use App\Services\Settings;
use Throwable;

final class BackupController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('backup.view');
        $rows = DB::all('SELECT b.*, u.name AS creator FROM backups b LEFT JOIN users u ON u.id = b.created_by_id ORDER BY b.created_at DESC LIMIT 100');
        $dirOk = true;
        try {
            Backups::dir();
        } catch (Throwable) {
            $dirOk = false;
        }
        return $this->screen('backups', [
            'title' => 'Backup & restore', 'rows' => $rows, 's' => Settings::group('backup'), 'dirOk' => $dirOk,
            'zip' => class_exists(\ZipArchive::class), 'restores' => DB::all('SELECT r.*, u.name AS who FROM backup_restores r LEFT JOIN users u ON u.id = r.restored_by_id ORDER BY r.started_at DESC LIMIT 10'),
            'cronUrl' => config('app.cron_secret') ? absolute_url('/cron/run?token=…') : null,
        ]);
    }

    public function create(): Response
    {
        if ($this->request->str('action') === 'import') {
            $this->authorize('backup.restore');
            try {
                $file = $this->request->file('backup') ?? [];
                $id = Backups::import($file, (int) $this->me['id']);
                Audit::record('imported', 'Backup', $id);
                $this->flash('Backup imported. You can restore from it below.');
            } catch (Throwable $e) {
                $this->flash($e->getMessage(), 'error');
            }
            return $this->redirect('/admin/backups');
        }
        $this->authorize('backup.create');
        $type = in_array($this->request->str('type'), ['FULL', 'DATABASE', 'MEDIA'], true) ? $this->request->str('type') : 'FULL';
        try {
            $id = Backups::create($type, 'MANUAL', (int) $this->me['id'], $this->request->str('note'));
            Audit::record('created', 'Backup', $id, $type);
            $this->flash('Backup completed.');
        } catch (Throwable $e) {
            $this->flash('Backup failed: ' . $e->getMessage(), 'error');
        }
        return $this->redirect('/admin/backups');
    }

    public function settings(): Response
    {
        $this->authorize('backup.settings');
        $in = $this->request->body + ['enabled' => '', 'include_media' => ''];
        Settings::save('backup', $in, true);
        Audit::record('updated', 'Settings', 'backup');
        $this->flash('Schedule saved.' . (config('app.cron_secret') ? '' : ' Remember to set up the cron job (see below).'));
        return $this->redirect('/admin/backups');
    }

    public function download(string $id): Response
    {
        $this->authorize('backup.download');
        $b = $this->find($this->id($id));
        $path = Backups::path($b);
        if (!is_file($path)) {
            throw HttpException::notFound();
        }
        Audit::record('downloaded', 'Backup', $b['id']);
        return Response::file($path, [
            'Content-Type' => 'application/zip',
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => 'attachment; filename="' . basename($path) . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function restore(string $id): Response
    {
        $this->authorize('backup.restore');
        $b = $this->find($this->id($id));
        if ($this->request->str('confirm') !== 'RESTORE') {
            $this->flash('Type RESTORE to confirm. Restoring replaces the whole site with the backup.', 'error');
            return $this->redirect('/admin/backups');
        }
        Audit::record('restore-started', 'Backup', $b['id']);
        try {
            $safety = Backups::restore((int) $b['id'], (int) $this->me['id']);
            // The audit table itself was restored: record the restore in the restored log too.
            Audit::record('restored', 'Backup', $b['id'], 'Safety backup #' . $safety);
            Auth::reset();
            $this->flash('Site restored from backup #' . $b['id'] . '. A safety backup (#' . $safety . ') was taken first. Please sign in again.');
            return $this->redirect((string) config('app.login_path'));
        } catch (Throwable $e) {
            $this->flash($e->getMessage(), 'error');
        }
        return $this->redirect('/admin/backups');
    }

    public function destroy(string $id): Response
    {
        $this->authorize('backup.delete');
        $b = $this->find($this->id($id));
        Backups::delete($b);
        Audit::record('deleted', 'Backup', $b['id'], $b['filename']);
        $this->flash('Backup deleted.');
        return $this->redirect('/admin/backups');
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $b = DB::first('SELECT * FROM backups WHERE id = ?', [$id]);
        if (!$b) {
            throw HttpException::notFound();
        }
        return $b;
    }
}
