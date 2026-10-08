<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\HttpException;
use App\Core\Response;
use App\Http\Controller;
use App\Services\Storage;

/**
 * Serves uploaded files from outside the web root. Only keys that match the
 * storage key format are accepted, the resolved path must stay inside the
 * upload directory, and every file goes out with nosniff and a sandboxing
 * CSP (set by the kernel for the media surface), so even an SVG cannot run
 * script on this origin.
 */
final class MediaController extends Controller
{
    public function serve(string $path): Response
    {
        $file = Storage::localPath($path);
        if ($file === null) {
            throw HttpException::notFound();
        }
        $mime = Storage::mimeFor($file);
        $size = (int) filesize($file);
        $etag = '"' . substr(sha1($path . '|' . filemtime($file) . '|' . $size), 0, 20) . '"';
        if (($this->request->header('If-None-Match') ?? '') === $etag) {
            return new Response('', 304, ['ETag' => $etag, 'Cache-Control' => 'public, max-age=31536000, immutable']);
        }
        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => (string) $size,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ];
        if (!str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/')) {
            $headers['Content-Disposition'] = 'inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file)) . '"';
        }
        if ($this->request->method === 'HEAD') {
            return new Response('', 200, $headers);
        }
        return Response::file($file, $headers);
    }
}
