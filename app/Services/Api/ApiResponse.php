<?php

declare(strict_types=1);

namespace App\Services\Api;

class ApiResponse
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?int $status,
        public readonly ?string $message,
        public readonly mixed $data,
        public readonly mixed $raw,
    ) {
    }

    public static function success(mixed $data, mixed $raw = null, ?string $message = null, ?int $status = null): self
    {
        return new self(true, $status, $message, $data, $raw);
    }

    public static function error(?string $message, ?int $status, mixed $data = null, mixed $raw = null): self
    {
        return new self(false, $status, $message, $data, $raw);
    }
}
