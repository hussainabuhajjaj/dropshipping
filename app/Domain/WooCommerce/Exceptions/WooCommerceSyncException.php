<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Exceptions;

use RuntimeException;

class WooCommerceSyncException extends RuntimeException
{
    public function __construct(
        string $message = '',
        private readonly ?int $entityId = null,
        private readonly ?string $entityType = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }
}
