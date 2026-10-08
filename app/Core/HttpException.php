<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : (string) $status);
    }

    public static function notFound(): self
    {
        return new self(404, 'Not Found');
    }

    public static function forbidden(): self
    {
        return new self(403, 'Forbidden');
    }
}
