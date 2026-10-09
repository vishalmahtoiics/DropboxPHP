<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\ValidationException;

/**
 * Upload validation and the media library write path.
 *
 * A file is accepted only if its extension is allowed AND its contents (magic
 * bytes, via finfo) match that extension's type — the browser's declared MIME
 * type is ignored. SVGs are parsed and refused if they contain script, event
 * handlers, foreign objects or external references. Names are rebuilt from
 * scratch, so nothing from the original filename reaches the filesystem
 * except a sanitised stem.
 */
final class Uploads
{
    /** extension => [canonical mime, kind, accepted detected mimes] */
    public const ALLOWED = [
        'jpg' => ['image/jpeg', 'IMAGE', ['image/jpeg']],
        'jpeg' => ['image/jpeg', 'IMAGE', ['image/jpeg']],
        'png' => ['image/png', 'IMAGE', ['image/png']],
        'gif' => ['image/gif', 'IMAGE', ['image/gif']],
        'webp' => ['image/webp', 'IMAGE', ['image/webp']],
        'avif' => ['image/avif', 'IMAGE', ['image/avif', 'application/octet-stream']],
        'svg' => ['image/svg+xml', 'IMAGE', ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain', 'text/html']],
        'ico' => ['image/x-icon', 'IMAGE', ['image/x-icon', 'image/vnd.microsoft.icon']],
        'mp4' => ['video/mp4', 'VIDEO', ['video/mp4']],
        'webm' => ['video/webm', 'VIDEO', ['video/webm']],
        'pdf' => ['application/pdf', 'DOCUMENT', ['application/pdf']],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'DOCUMENT', ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream']],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'DOCUMENT', ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream']],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'DOCUMENT', ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream']],
        'csv' => ['text/csv', 'DOCUMENT', ['text/csv', 'text/plain', 'application/csv']],
        'txt' => ['text/plain', 'DOCUMENT', ['text/plain']],
        'woff2' => ['font/woff2', 'OTHER', ['font/woff2', 'application/octet-stream']],
    ];

    /**
     * Validate and store an uploaded file, creating its media row.
     * @param array<string,mixed> $file one entry of $_FILES
     * @param string|null $key a storage key to use when it is free (imports); otherwise a new one
     * @return array<string,mixed> the media row
     */
    public static function store(array $file, ?int $userId, ?int $folderId = null, ?string $key = null): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw ValidationException::with('file', match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server allows.',
                UPLOAD_ERR_NO_FILE => 'Choose a file to upload.',
                default => 'The upload failed. Please try again.',
            });
        }
        $tmp = (string) $file['tmp_name'];
        $size = (int) $file['size'];
        $name = (string) $file['name'];
        $max = (int) config('app.max_upload_bytes');
        if ($size <= 0 || $size > $max) {
            throw ValidationException::with('file', 'Files must be smaller than ' . round($max / 1048576, 1) . ' MB.');
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            throw ValidationException::with('file', 'That file type is not allowed. Use images (JPG, PNG, WebP, GIF, SVG, AVIF), MP4/WebM video, PDF or Office documents.');
        }
        [$mime, $kind, $accepted] = self::ALLOWED[$ext];
        $detected = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($detected, $accepted, true)) {
            throw ValidationException::with('file', 'The file contents do not match its type (' . $ext . ').');
        }
        if ($ext === 'svg') {
            self::assertSafeSvg((string) file_get_contents($tmp));
        }
        $width = $height = null;
        if ($kind === 'IMAGE' && $ext !== 'svg' && $ext !== 'ico') {
            $info = @getimagesize($tmp);
            if ($info === false) {
                throw ValidationException::with('file', 'That image could not be read.');
            }
            [$width, $height] = $info;
            if ($width * $height > 60_000_000) {
                throw ValidationException::with('file', 'That image is too large in pixels.');
            }
        } elseif ($ext === 'svg') {
            [$width, $height] = self::svgSize((string) file_get_contents($tmp));
        }
        $free = $key !== null && preg_match(Storage::KEY_PATTERN, $key) && strtolower(pathinfo($key, PATHINFO_EXTENSION)) === $ext
            && !DB::value('SELECT id FROM media WHERE storage_key = ?', [$key]) && Storage::localPath($key) === null;
        if (!$free) {
            $key = Storage::newKey($name, $ext);
        }
        [$provider, $url] = Storage::put($tmp, $key, $mime);
        $id = DB::insert('media', [
            'folder_id' => $folderId,
            'filename' => mb_substr(preg_replace('/[^\pL\pN ._()\-]/u', '', $name) ?: 'file.' . $ext, 0, 255),
            'storage_key' => $key,
            'url' => $url,
            'provider' => $provider,
            'mime_type' => $mime,
            'kind' => $kind,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'alt_text' => $kind === 'IMAGE' ? mb_substr(ucfirst(trim(preg_replace('/[-_]+/', ' ', pathinfo($name, PATHINFO_FILENAME)) ?? '')), 0, 200) : null,
            'uploaded_by_id' => $userId,
        ]);
        return (array) DB::first('SELECT * FROM media WHERE id = ?', [$id]);
    }

    public static function assertSafeSvg(string $svg): void
    {
        $lower = strtolower($svg);
        $bad = ['<script', 'javascript:', 'onload', 'onerror', 'onclick', 'onmouse', 'onfocus', 'onbegin', '<foreignobject', '<iframe', '<embed', '<object', '<!entity', 'data:text/html', 'xlink:href="http', "xlink:href='http", 'href="http', "href='http", '@import'];
        foreach ($bad as $needle) {
            if (str_contains($lower, $needle)) {
                throw ValidationException::with('file', 'That SVG contains scripts or external references and was refused. Export it again as a plain SVG.');
            }
        }
        if (preg_match('/\son[a-z]+\s*=/i', $svg)) {
            throw ValidationException::with('file', 'That SVG contains event handlers and was refused.');
        }
        $prev = libxml_use_internal_errors(true);
        $ok = simplexml_load_string($svg, 'SimpleXMLElement', LIBXML_NONET) !== false;
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            throw ValidationException::with('file', 'That SVG is not valid XML.');
        }
    }

    /** @return array{0:?int,1:?int} */
    private static function svgSize(string $svg): array
    {
        if (preg_match('/viewBox\s*=\s*["\'][\d.\-]+[\s,]+[\d.\-]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $svg, $m)) {
            return [(int) round((float) $m[1]), (int) round((float) $m[2])];
        }
        return [null, null];
    }
}
