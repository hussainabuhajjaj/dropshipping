<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Contracts;

use App\Domain\Fulfillment\DTOs\FulfillmentRequestData;
use App\Domain\Fulfillment\DTOs\FulfillmentResult;

interface FulfillmentStrategy
{
    public function dispatch(FulfillmentRequestData $request): FulfillmentResult;
}
