<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Notifications\Marketing\CrossSellNotification;
use App\Services\ProductRecommendationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPostPurchaseCrossSells implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $settings = SiteSetting::query()->first();
        $config = $settings?->cross_sell_config ?? [];
        $delayDays = (int) ($config['delay_days'] ?? 7);
        $maxRecommendations = (int) ($config['max_recommendations'] ?? 3);
        $couponCode = $config['coupon_code'] ?? 'WELCOME10';
        $enablePush = $config['enable_push'] ?? true;
        $enableWhatsApp = $config['enable_whatsapp'] ?? true;
        $enableEmail = $config['enable_email'] ?? true;

        $cutoff = now()->subDays($delayDays);

        $orders = Order::query()
            ->where('customer_status', 'delivered')
            ->whereNull('cross_sell_sent_at')
            ->whereHas('customer', fn (Builder $q) => $q->where('marketing_opt_in', true))
            ->whereHas('orderItems.shipments', function (Builder $q) use ($cutoff) {
                $q->whereNotNull('delivered_at')->where('delivered_at', '<=', $cutoff);
            })
            ->with(['customer', 'orderItems.productVariant.product'])
            ->limit(50)
            ->get();

        $service = app(ProductRecommendationService::class);
        $sent = 0;

        foreach ($orders as $order) {
            try {
                $customer = $order->customer;
                if (! $customer || ! $customer->email) {
                    continue;
                }

                $products = $order->orderItems
                    ->pluck('productVariant.product')
                    ->filter()
                    ->unique('id');

                $recommendations = collect();
                foreach ($products as $product) {
                    $related = $service->relatedProducts($product, $maxRecommendations);
                    $recommendations = $recommendations->merge($related);
                }

                $recommendations = $recommendations
                    ->unique('id')
                    ->take($maxRecommendations);

                if ($recommendations->isEmpty()) {
                    $recommendations = collect($service->personalized($customer, $maxRecommendations));
                }

                if ($recommendations->isEmpty()) {
                    continue;
                }

                $notification = new CrossSellNotification(
                    order: $order,
                    recommendations: $recommendations,
                    couponCode: $couponCode,
                    enablePush: $enablePush,
                    enableWhatsApp: $enableWhatsApp,
                    enableEmail: $enableEmail,
                );

                $customer->notify($notification);
                $order->update(['cross_sell_sent_at' => now()]);
                $sent++;

                Log::info('Cross-sell sent', [
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'recommendations' => $recommendations->pluck('id')->all(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send cross-sell', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('SendPostPurchaseCrossSells completed', ['sent' => $sent, 'total' => $orders->count()]);
    }
}
