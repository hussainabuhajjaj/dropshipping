<?php

declare(strict_types=1);

namespace App\Domain\Observability;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderEvent;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Domain\Fulfillment\Models\FulfillmentEvent;
use App\Domain\Orders\Models\OrderItem;

class EventLogger
{
    public function order(Order $order, string $type, ?string $status = null, ?string $message = null, array $payload = []): OrderEvent
    {
        return $order->events()->create([
            'type' => $type,
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
        ]);
    }

    public function payment(Payment $payment, string $type, ?string $status = null, ?string $message = null, array $payload = []): PaymentEvent
    {
        return $payment->events()->create([
            'order_id' => $payment->order_id,
            'type' => $type,
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
        ]);
    }

    public function fulfillment(OrderItem $orderItem, string $type, ?string $status = null, ?string $message = null, array $payload = []): FulfillmentEvent
    {
        return $orderItem->fulfillmentEvents()->create([
            'fulfillment_provider_id' => $orderItem->fulfillment_provider_id,
            'fulfillment_job_id' => $orderItem->fulfillmentJob?->id,
            'type' => $type,
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
        ]);
    }
}
