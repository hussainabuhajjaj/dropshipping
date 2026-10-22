<?php

declare(strict_types=1);

namespace App\Services\Api;

use RuntimeException;

class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $codeString = null,
        public readonly mixed $body = null,
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($message, $status ?? 0);
    }
}
