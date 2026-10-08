<?php

declare(strict_types=1);

$path = static function (string $value): string {
    if ($value === '') {
        return $value;
    }
    return str_starts_with($value, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $value)
        ? rtrim($value, '/\\')
        : BASE_PATH . '/' . trim($value, '/\\');
};

return [
    'name' => 'Dropbox Reseller',
    'version' => '1.0.0',
    'env' => (string) env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => rtrim((string) env('APP_URL', 'http://localhost:8000'), '/'),
    'key' => (string) env('APP_KEY', ''),
    'timezone' => (string) env('APP_TIMEZONE', 'UTC'),
    'login_path' => '/' . trim((string) env('LOGIN_PATH', '/auth-control-panel/admin'), '/'),
    'canonical_host_redirect' => (bool) env('CANONICAL_HOST_REDIRECT', true),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

    'upload_dir' => $path((string) env('UPLOAD_DIR', 'storage/uploads')),
    'media_path' => '/' . trim((string) env('MEDIA_PUBLIC_PATH', '/media'), '/'),
    'max_upload_bytes' => (int) round(((float) env('MAX_UPLOAD_SIZE_MB', 10)) * 1024 * 1024),

    'backup_dir' => $path((string) env('BACKUP_DIR', 'storage/backups')),
    'cron_secret' => (string) env('CRON_SECRET', ''),

    'session' => [
        'name' => 'dbx_session',
        'lifetime' => 60 * 60 * 12,     // absolute lifetime of a signed-in session
        'idle' => 60 * 60 * 2,          // signed out after this long without a request
    ],
];
