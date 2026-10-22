<?php

declare(strict_types=1);

namespace App\Infrastructure\WooCommerce;

use RuntimeException;

class WooCommerceApiException extends RuntimeException
{
    public function __construct(
        string $message = '',
        private readonly int $statusCode = 0,
        private readonly array $responseBody = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): array
    {
        return $this->responseBody;
    }

    public function isRateLimit(): bool
    {
        return $this->statusCode === 429;
    }

    public function isServerError(): bool
    {
        return in_array($this->statusCode, [500, 502, 503, 504], true);
    }

    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    public function isDuplicate(): bool
    {
        return $this->statusCode === 409
            || str_contains(strtolower($this->message), 'duplicate')
            || str_contains(strtolower($this->message), 'already exists');
    }
}
