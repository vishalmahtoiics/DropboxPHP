<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\MediaReferences;
use App\Services\Redirects;
use App\Services\Slugs;
use App\Services\Storage;
use App\Services\Uploads;

final class MediaController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('media.view');
        [$where, $params] = $this->filters();
        $page = max(1, $this->request->int('page', 1));
        $per = 48;
        $total = (int) DB::value("SELECT COUNT(*) FROM media m WHERE $where", $params);
        $items = DB::all("SELECT m.*, u.name AS uploader FROM media m LEFT JOIN users u ON u.id = m.uploaded_by_id WHERE $where ORDER BY m.created_at DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
        $folders = DB::all('SELECT f.*, (SELECT COUNT(*) FROM media WHERE folder_id = f.id AND deleted_at IS NULL) AS n FROM media_folders f ORDER BY f.name');
        $selected = $this->request->int('item') ? DB::first('SELECT * FROM media WHERE id = ? AND deleted_at IS NULL', [$this->request->int('item')]) : null;
        $usage = $selected ? self::usage($selected) : [];
        return $this->screen('media/index', [
            'title' => 'Media library', 'items' => $items, 'folders' => $folders, 'total' => $total, 'pageNo' => $page,
            'pages' => (int) ceil($total / $per), 'selected' => $selected, 'usage' => $usage,
            'folder' => $this->request->int('folder'), 'kind' => $this->request->queryStr('kind'), 'q' => $this->request->queryStr('q'),
            'storageBytes' => (int) DB::value('SELECT COALESCE(SUM(size),0) FROM media WHERE deleted_at IS NULL'),
        ]);
    }

    /** JSON for the picker dialog. */
    public function picker(): Response
    {
        $this->authorize('media.view');
        [$where, $params] = $this->filters();
        $rows = DB::all("SELECT id, url, filename, alt_text, kind, mime_type, width, height FROM media m WHERE $where ORDER BY created_at DESC LIMIT 120", $params);
        return $this->json(['items' => array_map(static fn ($r) => [
            'id' => (int) $r['id'], 'url' => $r['url'], 'filename' => $r['filename'], 'alt' => (string) $r['alt_text'],
            'kind' => $r['kind'], 'mime' => $r['mime_type'], 'width' => $r['width'], 'height' => $r['height'],
        ], $rows)]);
    }

    public function upload(): Response
    {
        $this->authorize('media.upload');
        $files = $this->request->files['file'] ?? null;
        if (!is_array($files)) {
            throw ValidationException::with('file', 'Choose a file to upload.');
        }
        // Normalise single and multiple uploads.
        $list = is_array($files['name']) ? array_map(static fn ($i) => [
            'name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i], 'size' => $files['size'][$i],
        ], array_keys($files['name'])) : [$files];
        $folder = $this->request->int('folder_id') ?: null;
        if ($folder && !DB::value('SELECT id FROM media_folders WHERE id = ?', [$folder])) {
            $folder = null;
        }
        $saved = [];
        foreach (array_slice($list, 0, 20) as $file) {
            $media = Uploads::store($file, (int) $this->me['id'], $folder);
            Audit::record('uploaded', 'Media', $media['id'], $media['filename']);
            $saved[] = ['id' => (int) $media['id'], 'url' => $media['url'], 'filename' => $media['filename'], 'alt' => (string) $media['alt_text'], 'kind' => $media['kind'], 'mime' => $media['mime_type']];
        }
        if ($this->request->wantsJson()) {
            return $this->json(['ok' => true, 'items' => $saved]);
        }
        $this->flash(count($saved) === 1 ? 'File uploaded.' : count($saved) . ' files uploaded.');
        return $this->back('/admin/media');
    }

    public function update(string $id): Response
    {
        $this->authorize('media.edit');
        $media = $this->find($this->id($id));
        $data = $this->validate(['alt_text' => 'nullable|max:255', 'title' => 'nullable|max:255', 'caption' => 'nullable|max:2000', 'description' => 'nullable|max:5000', 'folder_id' => 'nullable|int']);
        DB::update('media', [
            'alt_text' => Sanitizer::line((string) $data['alt_text']) ?: null,
            'title' => Sanitizer::line((string) $data['title']) ?: null,
            'caption' => Sanitizer::text((string) $data['caption']) ?: null,
            'description' => Sanitizer::text((string) $data['description']) ?: null,
            'folder_id' => $data['folder_id'] && DB::value('SELECT id FROM media_folders WHERE id = ?', [$data['folder_id']]) ? (int) $data['folder_id'] : null,
        ], ['id' => $media['id']]);
        Audit::record('updated', 'Media', $media['id'], $media['filename']);
        $this->flash('Details saved.');
        return $this->redirect('/admin/media?item=' . $media['id']);
    }

    /**
     * Give a file a new, readable address. The file is moved, every page,
     * layout and setting that uses the old address is updated, and for files
     * on this server the old address keeps working (permanent redirect).
     */
    public function rename(string $id): Response
    {
        $this->authorize('media.edit');
        $media = $this->find($this->id($id));
        $stem = trim((string) preg_replace('/-+/', '-', Slugs::make($this->request->str('name'))), '-');
        $stem = substr($stem, 0, 100);
        if ($stem === '' || !preg_match('/^[a-z0-9]/', $stem)) {
            throw ValidationException::with('name', 'Use letters and numbers, e.g. business-plan-brochure.');
        }
        $oldKey = (string) $media['storage_key'];
        $ext = strtolower(pathinfo($oldKey, PATHINFO_EXTENSION));
        $dir = dirname($oldKey);
        $newKey = $dir . '/' . $stem . '.' . $ext;
        if ($newKey === $oldKey) {
            $this->flash('That is already the file address.', 'info');
            return $this->redirect('/admin/media?item=' . $media['id']);
        }
        for ($n = 2; DB::value('SELECT id FROM media WHERE storage_key = ?', [$newKey]) || ($media['provider'] === 'local' && Storage::localPath($newKey) !== null); $n++) {
            $newKey = $dir . '/' . $stem . '-' . $n . '.' . $ext;
        }
        try {
            $newUrl = Storage::rename((string) $media['provider'], $oldKey, $newKey, (string) $media['mime_type']);
        } catch (\RuntimeException $e) {
            throw ValidationException::with('name', $e->getMessage());
        }
        $oldUrl = (string) $media['url'];
        DB::update('media', ['storage_key' => $newKey, 'url' => $newUrl, 'filename' => basename($newKey)], ['id' => $media['id']]);
        $updated = MediaReferences::replace($oldUrl, $newUrl);
        if ($media['provider'] === 'local') {
            Redirects::onSlugChange($oldUrl, $newUrl);
        }
        Audit::record('updated', 'Media', $media['id'], 'Renamed ' . $oldUrl . ' → ' . $newUrl);
        $this->flash('Address changed to ' . $newUrl . ($updated ? '. Updated ' . $updated . ' place(s) that used it.' : '.'));
        return $this->redirect('/admin/media?item=' . $media['id']);
    }

    public function destroy(string $id): Response
    {
        $this->authorize('media.delete');
        $media = $this->find($this->id($id));
        $usage = self::usage($media);
        if ($usage && !$this->request->bool('force')) {
            $this->flash('This file is used in ' . count($usage) . ' place(s). Remove it there first, or confirm deletion from its details panel.', 'warning');
            return $this->redirect('/admin/media?item=' . $media['id']);
        }
        DB::delete('media', ['id' => $media['id']]);
        Storage::delete((string) $media['provider'], (string) $media['storage_key']);
        Audit::record('deleted', 'Media', $media['id'], $media['filename']);
        $this->flash('File deleted.');
        return $this->redirect('/admin/media');
    }

    public function createFolder(): Response
    {
        $this->authorize('media.upload');
        $data = $this->validate(['name' => 'required|max:120']);
        $id = DB::insert('media_folders', ['name' => Sanitizer::line((string) $data['name'], 120)]);
        $this->flash('Folder created.');
        return $this->redirect('/admin/media?folder=' . $id);
    }

    public function deleteFolder(string $id): Response
    {
        $this->authorize('media.delete');
        DB::update('media', ['folder_id' => null], ['folder_id' => $this->id($id)]);
        DB::delete('media_folders', ['id' => $this->id($id)]);
        $this->flash('Folder deleted. Its files moved to "All files".');
        return $this->redirect('/admin/media');
    }

    /** Where a file is referenced (sections, products, posts, settings). @return list<string> */
    public static function usage(array $media): array
    {
        $url = (string) $media['url'];
        $id = (int) $media['id'];
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $url) . '%';
        $out = [];
        foreach (DB::all('SELECT p.title FROM page_sections s JOIN pages p ON p.id = s.page_id WHERE (s.content LIKE ? OR s.settings LIKE ?) AND p.deleted_at IS NULL', [$like, $like]) as $r) {
            $out[] = 'Page: ' . $r['title'];
        }
        foreach (DB::all('SELECT title FROM pages WHERE (builder_layout LIKE ? OR builder_draft LIKE ?) AND deleted_at IS NULL', [$like, $like]) as $r) {
            $out[] = 'Page (builder): ' . $r['title'];
        }
        foreach (DB::all('SELECT name FROM products WHERE (builder_layout LIKE ? OR builder_draft LIKE ?) AND deleted_at IS NULL', [$like, $like]) as $r) {
            $out[] = 'Product (builder): ' . $r['name'];
        }
        foreach (DB::all('SELECT name FROM builder_templates WHERE layout LIKE ? OR draft LIKE ?', [$like, $like]) as $r) {
            $out[] = 'Builder template: ' . $r['name'];
        }
        foreach (DB::all('SELECT name FROM products WHERE (image_id = ? OR og_image_id = ? OR description LIKE ?) AND deleted_at IS NULL', [$id, $id, $like]) as $r) {
            $out[] = 'Product: ' . $r['name'];
        }
        foreach (DB::all('SELECT title FROM blog_posts WHERE (featured_image_id = ? OR og_image_id = ? OR content LIKE ?) AND deleted_at IS NULL', [$id, $id, $like]) as $r) {
            $out[] = 'Article: ' . $r['title'];
        }
        if (DB::value('SELECT 1 FROM settings WHERE data LIKE ?', [$like])) {
            $out[] = 'Website settings (logo, favicon or share image)';
        }
        return array_values(array_unique($out));
    }

    /** @return array{0:string,1:list<mixed>} */
    private function filters(): array
    {
        $where = 'm.deleted_at IS NULL';
        $params = [];
        $q = $this->request->queryStr('q');
        if ($q !== '') {
            $where .= ' AND (m.filename LIKE ? OR m.alt_text LIKE ? OR m.title LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        $kind = $this->request->queryStr('kind');
        if (in_array($kind, ['IMAGE', 'VIDEO', 'DOCUMENT', 'OTHER'], true)) {
            $where .= ' AND m.kind = ?';
            $params[] = $kind;
        }
        $folder = $this->request->int('folder');
        if ($folder) {
            $where .= ' AND m.folder_id = ?';
            $params[] = $folder;
        }
        return [$where, $params];
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $m = DB::first('SELECT * FROM media WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$m) {
            throw HttpException::notFound();
        }
        return $m;
    }
}
