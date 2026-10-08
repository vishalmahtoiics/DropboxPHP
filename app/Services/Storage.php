<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * File storage behind one interface: local disk (default), Amazon S3 or
 * Cloudflare R2. The CMS only calls put(), delete() and url(); nothing else
 * branches on the provider. Each media row records the provider that stored
 * it, so files uploaded before a switch keep resolving.
 *
 * S3/R2 requests are signed with AWS Signature V4 directly — no SDK, so it
 * runs on any PHP host.
 */
final class Storage
{
    public const KEY_PATTERN = '#^\d{4}/\d{2}/[a-z0-9][a-z0-9\-]{0,120}\.[a-z0-9]{2,5}$#';

    public static function driver(): string
    {
        $d = strtolower((string) env('STORAGE_DRIVER', 'local'));
        return in_array($d, ['local', 's3', 'r2'], true) ? $d : 'local';
    }

    /** A new, unguessable storage key that keeps a readable stem of the original name. */
    public static function newKey(string $originalName, string $extension): string
    {
        $stem = strtolower(pathinfo($originalName, PATHINFO_FILENAME));
        $stem = trim(preg_replace('/[^a-z0-9]+/', '-', $stem) ?? '', '-');
        $stem = mb_substr($stem !== '' ? $stem : 'file', 0, 60);
        return date('Y/m') . '/' . $stem . '-' . bin2hex(random_bytes(6)) . '.' . strtolower($extension);
    }

    /** Store a file; returns [provider, public URL]. @return array{0:string,1:string} */
    public static function put(string $sourcePath, string $key, string $mime): array
    {
        if (!preg_match(self::KEY_PATTERN, $key)) {
            throw new RuntimeException('Invalid storage key.');
        }
        $driver = self::driver();
        if ($driver === 'local') {
            $target = rtrim((string) config('app.upload_dir'), '/') . '/' . $key;
            $dir = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Upload directory is not writable.');
            }
            if (is_uploaded_file($sourcePath) ? !move_uploaded_file($sourcePath, $target) : !copy($sourcePath, $target)) {
                throw new RuntimeException('Could not save the file.');
            }
            @chmod($target, 0644);
            return ['local', rtrim((string) config('app.media_path'), '/') . '/' . $key];
        }
        $body = (string) file_get_contents($sourcePath);
        self::s3('PUT', $key, $body, $mime);
        return [$driver, self::publicS3Url($key)];
    }

    public static function delete(string $provider, string $key): void
    {
        if (!preg_match(self::KEY_PATTERN, $key)) {
            return;
        }
        if ($provider === 'local') {
            $file = self::localPath($key);
            if ($file !== null) {
                @unlink($file);
            }
            return;
        }
        try {
            self::s3('DELETE', $key, '', '');
        } catch (RuntimeException) {
            // An orphaned object is harmless; a failed delete must not block the admin.
        }
    }

    /** The absolute path of a local key, or null if it is not a valid stored file. */
    public static function localPath(string $key): ?string
    {
        if (!preg_match(self::KEY_PATTERN, $key)) {
            return null;
        }
        foreach ([(string) config('app.upload_dir'), BASE_PATH . '/public/uploads'] as $base) {
            $root = realpath($base);
            if ($root === false) {
                continue;
            }
            $file = realpath($root . '/' . $key);
            if ($file !== false && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) {
                return $file;
            }
        }
        return null;
    }

    public static function mimeFor(string $file): string
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return Uploads::ALLOWED[$ext][0] ?? 'application/octet-stream';
    }

    // --- S3 / R2 -------------------------------------------------------------

    private static function publicS3Url(string $key): string
    {
        $public = rtrim((string) env('S3_PUBLIC_URL', ''), '/');
        if ($public !== '') {
            return $public . '/' . $key;
        }
        [$host, $pathPrefix] = self::endpoint();
        return 'https://' . $host . $pathPrefix . '/' . $key;
    }

    /** @return array{0:string,1:string} host, path prefix */
    private static function endpoint(): array
    {
        $bucket = (string) env('S3_BUCKET', '');
        $region = (string) env('S3_REGION', 'us-east-1');
        $endpoint = (string) env('S3_ENDPOINT', '');
        $pathStyle = (bool) env('S3_FORCE_PATH_STYLE', self::driver() === 'r2');
        if ($endpoint !== '') {
            $host = (string) parse_url($endpoint, PHP_URL_HOST);
            return $pathStyle ? [$host, '/' . $bucket] : [$bucket . '.' . $host, ''];
        }
        $host = "s3.$region.amazonaws.com";
        return $pathStyle ? [$host, '/' . $bucket] : ["$bucket.$host", ''];
    }

    private static function s3(string $method, string $key, string $body, string $mime): void
    {
        $access = (string) env('S3_ACCESS_KEY', '');
        $secret = (string) env('S3_SECRET_KEY', '');
        $region = (string) env('S3_REGION', 'auto');
        if ($access === '' || $secret === '' || env('S3_BUCKET', '') === '') {
            throw new RuntimeException('S3 storage is not configured.');
        }
        [$host, $prefix] = self::endpoint();
        $path = $prefix . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
        $time = gmdate('Ymd\THis\Z');
        $date = substr($time, 0, 8);
        $payloadHash = hash('sha256', $body);
        $headers = ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $time];
        if ($method === 'PUT') {
            $headers['content-type'] = $mime;
            $headers['cache-control'] = 'public, max-age=31536000, immutable';
        }
        ksort($headers);
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= $k . ':' . trim($v) . "\n";
        }
        $signed = implode(';', array_keys($headers));
        $canonical = "$method\n$path\n\n$canonicalHeaders\n$signed\n$payloadHash";
        $scope = "$date/$region/s3/aws4_request";
        $toSign = "AWS4-HMAC-SHA256\n$time\n$scope\n" . hash('sha256', $canonical);
        $k = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
        $k = hash_hmac('sha256', $region, $k, true);
        $k = hash_hmac('sha256', 's3', $k, true);
        $k = hash_hmac('sha256', 'aws4_request', $k, true);
        $signature = hash_hmac('sha256', $toSign, $k);
        $headers['authorization'] = "AWS4-HMAC-SHA256 Credential=$access/$scope, SignedHeaders=$signed, Signature=$signature";

        $ch = curl_init('https://' . $host . $path);
        $httpHeaders = [];
        foreach ($headers as $k2 => $v) {
            if ($k2 !== 'host') {
                $httpHeaders[] = $k2 . ': ' . $v;
            }
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_POSTFIELDS => $method === 'PUT' ? $body : null,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false || $status >= 300) {
            throw new RuntimeException('Storage request failed (' . ($status ?: $error) . ').');
        }
    }
}
