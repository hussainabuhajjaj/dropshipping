<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\DTOs;

class WooCommerceSyncResult
{
    public function __construct(
        public readonly bool $success = true,
        public readonly string $status = 'synced',
        public readonly ?string $error = null,
        public readonly ?int $entityId = null,
        public readonly ?int $woocommerceId = null,
        public readonly array $meta = [],
        public readonly array $rawResponse = [],
    ) {
    }

    public static function success(?int $entityId = null, ?int $woocommerceId = null, array $meta = []): self
    {
        return new self(
            success: true,
            status: 'synced',
            entityId: $entityId,
            woocommerceId: $woocommerceId,
            meta: $meta,
        );
    }

    public static function skipped(string $reason, ?int $entityId = null): self
    {
        return new self(
            success: true,
            status: 'skipped',
            error: $reason,
            entityId: $entityId,
        );
    }

    public static function failed(string $error, ?int $entityId = null): self
    {
        return new self(
            success: false,
            status: 'failed',
            error: $error,
            entityId: $entityId,
        );
    }
}
