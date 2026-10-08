<?php

declare(strict_types=1);

// Defaults. Override them in config.php at the project root (see config.sample.php).
return [
    'name' => 'Dropbox Reseller',
    'version' => '1.0.0',
    'env' => 'production',
    'debug' => false,
    'url' => 'http://localhost:8000',
    'key' => '',
    'timezone' => 'UTC',
    'login_path' => '/auth-control-panel/admin',
    'canonical_host_redirect' => true,
    'trusted_proxies' => [],

    'upload_dir' => 'storage/uploads',
    'media_path' => '/media',
    'max_upload_mb' => 10,

    'backup_dir' => 'storage/backups',
    'cron_secret' => '',

    'session' => [
        'name' => 'dbx_session',
        'lifetime' => 60 * 60 * 12,     // absolute lifetime of a signed-in session
        'idle' => 60 * 60 * 2,          // signed out after this long without a request
    ],
];
