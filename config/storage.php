<?php

declare(strict_types=1);

// Media storage: local | s3 | r2. Override in config.php (see config.sample.php).
return [
    'driver' => 'local',
    'bucket' => '',
    'region' => '',
    'endpoint' => '',
    'access_key' => '',
    'secret_key' => '',
    'public_url' => '',
    'path_style' => null,
];
