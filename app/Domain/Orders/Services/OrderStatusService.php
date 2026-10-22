<?php

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderAuditLog;

class OrderStatusService
{
    public function transition(Order $order, string $newStatus, ?string $actor = null): void
    {
        $previousStatus = $order->status;
        $order->update(['status' => $newStatus]);
        OrderAuditLog::create([
            'order_id' => $order->id,
            'from_status' => $previousStatus,
            'to_status' => $newStatus,
            'actor' => $actor,
            'changed_at' => now(),
        ]);
    }
}
