<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AbandonedCart;
use App\Models\SiteSetting;
use App\Notifications\AbandonedCartNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendAbandonedCartReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $settings = SiteSetting::query()->first();
        $config = $settings?->abandoned_cart_config ?? [];
        $couponCode = $config['coupon_code'] ?? 'SAVE10';
        $enablePush = $config['enable_push'] ?? true;
        $enableWhatsApp = $config['enable_whatsapp'] ?? true;
        $enableEmail = $config['enable_email'] ?? true;

        // Send reminders for carts abandoned 1 hour ago (first reminder)
        $oneHourAgo = now()->subHour();
        $firstReminders = AbandonedCart::query()
            ->whereNull('recovered_at')
            ->whereNull('reminder_sent_at')
            ->where('abandoned_at', '<=', $oneHourAgo)
            ->where('abandoned_at', '>=', now()->subHours(2))
            ->whereNotNull('email')
            ->get();

        foreach ($firstReminders as $cart) {
            try {
                $notification = new AbandonedCartNotification(
                    cart: $cart,
                    reminderNumber: 1,
                    couponCode: $couponCode,
                    enablePush: $enablePush,
                    enableWhatsApp: $enableWhatsApp,
                    enableEmail: $enableEmail,
                );

                if ($cart->customer) {
                    Notification::send($cart->customer, $notification);
                } else {
                    Notification::route('mail', $cart->email)
                        ->notify($notification);
                }

                $cart->update(['reminder_sent_at' => now()]);

                Log::info('Sent abandoned cart reminder', [
                    'cart_id' => $cart->id,
                    'email' => $cart->email,
                    'reminder' => 1,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send abandoned cart reminder', [
                    'cart_id' => $cart->id,
                    'reminder' => 1,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Send second reminder for carts abandoned 24 hours ago
        $twentyFourHoursAgo = now()->subDay();
        $secondReminders = AbandonedCart::query()
            ->whereNull('recovered_at')
            ->whereNotNull('reminder_sent_at')
            ->where('reminder_sent_at', '<=', $twentyFourHoursAgo)
            ->where('abandoned_at', '<=', $twentyFourHoursAgo)
            ->where('abandoned_at', '>=', now()->subDays(2))
            ->whereNotNull('email')
            ->get();

        foreach ($secondReminders as $cart) {
            try {
                $notification = new AbandonedCartNotification(
                    cart: $cart,
                    reminderNumber: 2,
                    couponCode: $couponCode,
                    enablePush: $enablePush,
                    enableWhatsApp: $enableWhatsApp,
                    enableEmail: $enableEmail,
                );

                if ($cart->customer) {
                    Notification::send($cart->customer, $notification);
                } else {
                    Notification::route('mail', $cart->email)
                        ->notify($notification);
                }

                Log::info('Sent second abandoned cart reminder', [
                    'cart_id' => $cart->id,
                    'email' => $cart->email,
                    'reminder' => 2,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send second abandoned cart reminder', [
                    'cart_id' => $cart->id,
                    'reminder' => 2,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Send third reminder for carts abandoned 72 hours ago (last chance)
        $seventyTwoHoursAgo = now()->subHours(72);
        $thirdReminders = AbandonedCart::query()
            ->whereNull('recovered_at')
            ->where('reminder_sent_at', '<=', now()->subDay())
            ->where('abandoned_at', '<=', $seventyTwoHoursAgo)
            ->where('abandoned_at', '>=', now()->subDays(4))
            ->whereNotNull('email')
            ->get();

        foreach ($thirdReminders as $cart) {
            try {
                $notification = new AbandonedCartNotification(
                    cart: $cart,
                    reminderNumber: 3,
                    couponCode: $couponCode,
                    enablePush: $enablePush,
                    enableWhatsApp: $enableWhatsApp,
                    enableEmail: $enableEmail,
                );

                if ($cart->customer) {
                    Notification::send($cart->customer, $notification);
                } else {
                    Notification::route('mail', $cart->email)
                        ->notify($notification);
                }

                Log::info('Sent third (last chance) abandoned cart reminder', [
                    'cart_id' => $cart->id,
                    'email' => $cart->email,
                    'reminder' => 3,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send third abandoned cart reminder', [
                    'cart_id' => $cart->id,
                    'reminder' => 3,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
