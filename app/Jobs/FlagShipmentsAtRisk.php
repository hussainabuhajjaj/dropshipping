<?php

namespace App\Jobs;

use App\Domain\Orders\Models\Shipment;
use App\Enums\ShipmentExceptionCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FlagShipmentsAtRisk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of days without tracking updates to mark as at-risk.
     * Configurable via environment: SHIPMENT_AT_RISK_DAYS
     */
    protected int $daysThreshold;

    public function __construct(int $daysThreshold = null)
    {
        $this->daysThreshold = $daysThreshold ?? (int) config('shipments.at_risk_days', 14);
    }

    public function handle(): void
    {
        // Find shipments that:
        // 1. Are shipped (shipped_at is not null)
        // 2. Are not yet delivered (delivered_at is null)
        // 3. Are not already flagged as at-risk (is_at_risk = false)
        // 4. Last tracking event was more than X days ago (or no events at all)
        // 5. Last update was more than X days ago

        $threshold = now()->subDays($this->daysThreshold);

        $shipmentsAtRisk = Shipment::where('shipped_at', '!=', null)
            ->whereNull('delivered_at')
            ->where('is_at_risk', false)
            ->where(function ($query) use ($threshold) {
                // Either no tracking events, or last tracking event before threshold
                $query->whereDoesntHave('trackingEvents')
                    ->orWhereHas('trackingEvents', function ($q) use ($threshold) {
                        $q->where('occurred_at', '<', $threshold);
                    }, '<', 1);
            })
            ->where(function ($query) use ($threshold) {
                // Or updated_at before threshold
                $query->where('updated_at', '<', $threshold);
            })
            ->get();

        foreach ($shipmentsAtRisk as $shipment) {
            $this->flagAsAtRisk($shipment);
        }

        $this->info(sprintf('Flagged %d shipments as at-risk (no updates for %d+ days)', count($shipmentsAtRisk), $this->daysThreshold));
    }

    private function flagAsAtRisk(Shipment $shipment): void
    {
        $daysAgo = $shipment->updated_at->diffInDays(now());

        $reason = sprintf(
            'No tracking updates for %d days (last update: %s)',
            $daysAgo,
            $shipment->updated_at->format('M d, Y H:i')
        );

        $shipment->markAsAtRisk($reason);
    }

    private function info(string $message): void
    {
        \Illuminate\Support\Facades\Log::info("FlagShipmentsAtRisk: {$message}");
    }
}
