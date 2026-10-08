<?php

declare(strict_types=1);

// Bootstrap SMTP. The values saved in Admin -> Settings -> Email take precedence.
return [
    'host' => (string) env('SMTP_HOST', ''),
    'port' => (int) env('SMTP_PORT', 587),
    'username' => (string) env('SMTP_USERNAME', ''),
    'password' => (string) env('SMTP_PASSWORD', ''),
    'encryption' => (string) env('SMTP_ENCRYPTION', 'tls'),
    'from' => (string) env('MAIL_FROM', ''),
    'from_name' => (string) env('MAIL_FROM_NAME', 'Dropbox Reseller'),
];
