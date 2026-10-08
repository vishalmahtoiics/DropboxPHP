<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Response;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\Csv;
use App\Services\Redirects;

final class RedirectController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('seo.manage');
        if ($this->request->queryStr('export') === 'csv') {
            $csv = Csv::build(['source', 'destination', 'type', 'active', 'hits'], array_map(static fn ($r) => [$r['source'], $r['destination'], $r['type'], $r['is_active'], $r['hit_count']], DB::all('SELECT * FROM redirects ORDER BY source')));
            return new Response($csv, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="redirects.csv"']);
        }
        $q = $this->request->queryStr('q');
        $rows = $q !== ''
            ? DB::all('SELECT * FROM redirects WHERE source LIKE ? OR destination LIKE ? ORDER BY updated_at DESC LIMIT 500', ["%$q%", "%$q%"])
            : DB::all('SELECT * FROM redirects ORDER BY updated_at DESC LIMIT 500');
        $edit = $this->request->int('edit') ? DB::first('SELECT * FROM redirects WHERE id = ?', [$this->request->int('edit')]) : null;
        return $this->screen('seo/redirects', [
            'title' => 'Redirects', 'rows' => $rows, 'edit' => $edit, 'q' => $q,
            'notFound' => DB::all("SELECT * FROM not_found_log WHERE status = 'OPEN' ORDER BY hits DESC LIMIT 50"),
            'prefill' => $this->request->queryStr('from'),
        ]);
    }

    public function save(): Response
    {
        $this->authorize('seo.manage');
        if ($this->request->str('action') === 'ignore') {
            DB::query("UPDATE not_found_log SET status = 'IGNORED' WHERE path_hash = ?", [hash('sha256', Redirects::normalise($this->request->str('path')))]);
            return $this->redirect('/admin/redirects');
        }
        if ($this->request->str('action') === 'import') {
            return $this->import();
        }
        $d = $this->validate(['id' => 'nullable|int', 'source' => 'required|max:500', 'destination' => 'required|max:1000', 'type' => 'required|in:PERMANENT,TEMPORARY', 'is_active' => 'bool', 'note' => 'nullable|max:255']);
        $id = Redirects::save($d, $d['id'] ?: null);
        DB::query("UPDATE not_found_log SET status = 'RESOLVED' WHERE path_hash = ?", [hash('sha256', Redirects::normalise((string) $d['source']))]);
        Audit::record('saved', 'Redirect', $id, $d['source'] . ' → ' . $d['destination']);
        $this->flash('Redirect saved.');
        return $this->redirect('/admin/redirects');
    }

    /** CSV import: source,destination[,type]. Each row is validated (loops refused). */
    private function import(): Response
    {
        $file = $this->request->file('csv');
        if (!$file || (int) $file['error'] !== UPLOAD_ERR_OK || (int) $file['size'] > 2 * 1024 * 1024) {
            throw ValidationException::with('csv', 'Upload a CSV file under 2 MB.');
        }
        $handle = fopen((string) $file['tmp_name'], 'r');
        $ok = 0;
        $errors = [];
        $line = 0;
        while (($row = fgetcsv($handle)) !== false && $line < 5000) {
            $line++;
            if ($line === 1 && strtolower(trim((string) ($row[0] ?? ''))) === 'source') {
                continue;
            }
            try {
                Redirects::save(['source' => $row[0] ?? '', 'destination' => $row[1] ?? '', 'type' => strtoupper(trim((string) ($row[2] ?? 'PERMANENT'))), 'is_active' => 1]);
                $ok++;
            } catch (ValidationException $e) {
                $errors[] = "Line $line: " . $e->getMessage() . ' ' . implode(' ', $e->errors);
            }
        }
        fclose($handle);
        Audit::record('imported', 'Redirect', null, "$ok redirects imported");
        $this->flash("$ok redirect(s) imported." . ($errors ? ' Skipped: ' . implode(' | ', array_slice($errors, 0, 5)) : ''), $errors ? 'warning' : 'success');
        return $this->redirect('/admin/redirects');
    }

    public function destroy(string $id): Response
    {
        $this->authorize('seo.manage');
        DB::delete('redirects', ['id' => $this->id($id)]);
        Audit::record('deleted', 'Redirect', $id);
        $this->flash('Redirect deleted.');
        return $this->redirect('/admin/redirects');
    }
}
