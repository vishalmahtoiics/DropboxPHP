<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/** Thrown from a constructor or helper to end the request with a redirect. */
final class RedirectException extends RuntimeException
{
    public function __construct(public readonly string $to)
    {
        parent::__construct($to);
    }
}
