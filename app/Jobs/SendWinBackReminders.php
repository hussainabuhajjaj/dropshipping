<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Customer;
use App\Models\SiteSetting;
use App\Notifications\Marketing\WinBackNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWinBackReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $settings = SiteSetting::query()->first();
        $config = $settings?->win_back_config ?? [];
        $inactivityDays = (int) ($config['inactivity_days'] ?? 60);
        $couponCode = $config['coupon_code'] ?? 'MISSYOU10';
        $enablePush = $config['enable_push'] ?? true;
        $enableWhatsApp = $config['enable_whatsapp'] ?? true;
        $enableEmail = $config['enable_email'] ?? true;

        $cutoff = now()->subDays($inactivityDays);

        $customers = Customer::query()
            ->where('marketing_opt_in', true)
            ->whereNotNull('email')
            ->whereHas('orders', function ($q) {
                $q->where('payment_status', 'paid');
            })
            ->whereDoesntHave('orders', function ($q) use ($cutoff) {
                $q->where('placed_at', '>=', $cutoff)->where('payment_status', 'paid');
            })
            ->whereDoesntHave('notifications', function ($q) {
                $q->where('type', WinBackNotification::class)
                    ->where('created_at', '>=', now()->subDays(90));
            })
            ->limit(100)
            ->get();

        $sent = 0;

        foreach ($customers as $customer) {
            try {
                $lastOrder = $customer->orders()
                    ->where('payment_status', 'paid')
                    ->latest('placed_at')
                    ->first();

                $actualInactiveDays = $lastOrder?->placed_at
                    ? (int) $lastOrder->placed_at->diffInDays(now())
                    : $inactivityDays;

                $notification = new WinBackNotification(
                    customer: $customer,
                    couponCode: $couponCode,
                    inactiveDays: $actualInactiveDays,
                    enablePush: $enablePush,
                    enableWhatsApp: $enableWhatsApp,
                    enableEmail: $enableEmail,
                );

                $customer->notify($notification);
                $sent++;

                Log::info('Win-back sent', [
                    'customer_id' => $customer->id,
                    'email' => $customer->email,
                    'inactive_days' => $actualInactiveDays,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send win-back', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('SendWinBackReminders completed', ['sent' => $sent, 'total' => $customers->count()]);
    }
}
