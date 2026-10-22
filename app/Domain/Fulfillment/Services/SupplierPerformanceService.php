<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Fulfillment\Models\SupplierMetric;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;
use Illuminate\Support\Facades\DB;

class SupplierPerformanceService
{
    public function refreshForProvider(FulfillmentProvider $provider): SupplierMetric
    {
        return DB::transaction(function () use ($provider) {
            $orderItems = OrderItem::where('fulfillment_provider_id', $provider->id);

            $fulfilledCount = (clone $orderItems)->where('fulfillment_status', 'fulfilled')->count();
            $failedCount = (clone $orderItems)->where('fulfillment_status', 'failed')->count();
            $refundedCount = (clone $orderItems)
                ->whereHas('order', fn ($q) => $q->where('payment_status', 'refunded'))
                ->count();

            $avgLeadTime = $this->averageLeadTimeDays($provider->id);

            return SupplierMetric::updateOrCreate(
                ['fulfillment_provider_id' => $provider->id],
                [
                    'fulfilled_count' => $fulfilledCount,
                    'failed_count' => $failedCount,
                    'refunded_count' => $refundedCount,
                    'average_lead_time_days' => $avgLeadTime,
                    'calculated_at' => now(),
                ]
            );
        });
    }

    private function averageLeadTimeDays(int $providerId): ?float
    {
        $shipmentTimes = Shipment::query()
            ->whereHas('orderItem', fn ($q) => $q->where('fulfillment_provider_id', $providerId))
            ->whereNotNull('shipped_at')
            ->whereNotNull('delivered_at')
            ->get(['shipped_at', 'delivered_at'])
            ->map(fn ($s) => $s->delivered_at->diffInHours($s->shipped_at) / 24);

        if ($shipmentTimes->isEmpty()) {
            return null;
        }

        return round($shipmentTimes->avg(), 2);
    }
}
