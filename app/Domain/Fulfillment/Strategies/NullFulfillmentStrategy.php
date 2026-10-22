<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Strategies;

use App\Domain\Fulfillment\Contracts\FulfillmentStrategy;
use App\Domain\Fulfillment\DTOs\FulfillmentRequestData;
use App\Domain\Fulfillment\DTOs\FulfillmentResult;

class NullFulfillmentStrategy implements FulfillmentStrategy
{
    public function dispatch(FulfillmentRequestData $request): FulfillmentResult
    {
        // No-op strategy for testing or deferring fulfillment.
        return new FulfillmentResult(
            status: 'pending',
            externalReference: null,
            trackingNumber: null,
            trackingUrl: null,
            rawResponse: ['message' => 'Null strategy invoked'],
        );
    }
}
