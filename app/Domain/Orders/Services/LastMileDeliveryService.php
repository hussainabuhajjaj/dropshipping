<?php

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\LastMileDelivery;

class LastMileDeliveryService
{
    public function createLastMileDelivery(Order $order, array $data): LastMileDelivery
    {
        return LastMileDelivery::create(array_merge($data, [
            'order_id' => $order->id,
            'status' => 'pending',
        ]));
    }

    public function markOutForDelivery(LastMileDelivery $delivery, string $driverName, string $driverPhone, string $yangoReference): void
    {
        $delivery->update([
            'status' => 'out_for_delivery',
            'driver_name' => $driverName,
            'driver_phone' => $driverPhone,
            'yango_reference' => $yangoReference,
            'out_for_delivery_at' => now(),
        ]);
    }

    public function markDelivered(LastMileDelivery $delivery): void
    {
        $delivery->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }
}
