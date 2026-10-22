<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Strategies;

use App\Domain\Fulfillment\Contracts\FulfillmentStrategy;
use App\Domain\Fulfillment\DTOs\FulfillmentRequestData;
use App\Domain\Fulfillment\DTOs\FulfillmentResult;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use Illuminate\Support\Str;

class ManualFulfillmentStrategy implements FulfillmentStrategy
{
    public function __construct(private readonly FulfillmentProvider $provider)
    {
    }

    public function dispatch(FulfillmentRequestData $request): FulfillmentResult
    {
        // Manual flow requires a human to act; mark as needs_action and attach metadata for operators.
        return new FulfillmentResult(
            status: 'needs_action',
            externalReference: 'manual-' . Str::uuid()->toString(),
            trackingNumber: null,
            trackingUrl: null,
            rawResponse: [
                'instruction' => 'Manual fulfillment required',
                'provider' => $this->provider->code,
                'order_item_id' => $request->orderItem->id,
            ],
        );
    }
}
