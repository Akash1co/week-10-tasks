<?php

namespace App\Services;

use RuntimeException;

class CiaServiceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
