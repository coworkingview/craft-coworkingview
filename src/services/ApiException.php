<?php

declare(strict_types=1);

namespace coworkingview\coworkingview\services;

use RuntimeException;
use Throwable;

/**
 * Failure talking to the public CoworkingView API.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public int $status,
        public string $errorCode,
        public string $title,
        public string $detail,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }
}
