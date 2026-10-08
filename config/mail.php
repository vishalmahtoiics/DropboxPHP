<?php

declare(strict_types=1);

// Starting SMTP settings. The values saved in Admin -> Settings -> Email take
// precedence. Override these in config.php (see config.sample.php).
return [
    'host' => '',
    'port' => 587,
    'username' => '',
    'password' => '',
    'encryption' => 'tls',
    'from' => '',
    'from_name' => 'Dropbox Reseller',
];
