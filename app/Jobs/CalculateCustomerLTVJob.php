<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateCustomerLTVJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public function handle(): void
    {
        Log::info('Starting customer LTV calculation');

        $customers = Customer::query()
            ->whereHas('orders', function ($query) {
                $query->where('payment_status', 'paid');
            })
            ->get();

        $updated = 0;

        foreach ($customers as $customer) {
            $ltv = Order::query()
                ->where('customer_id', $customer->id)
                ->where('payment_status', 'paid')
                ->sum('grand_total');

            // Store LTV in metadata since we don't have a dedicated column
            $metadata = is_array($customer->metadata) ? $customer->metadata : [];
            $metadata['lifetime_value'] = (float) $ltv;
            $metadata['ltv_calculated_at'] = now()->toIso8601String();

            $customer->update(['metadata' => $metadata]);
            $updated++;
        }

        Log::info('Customer LTV calculation complete', [
            'customers_updated' => $updated,
        ]);
    }
}
