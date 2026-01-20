<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AbandonedCart;
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
        // Send reminders for carts abandoned 1 hour ago (first reminder)
        $oneHourAgo = now()->subHour();
        $firstReminders = AbandonedCart::query()
            ->whereNull('recovered_at')
            ->whereNull('reminder_sent_at')
            ->where('abandoned_at', '<=', $oneHourAgo)
            ->where('abandoned_at', '>=', now()->subHours(2)) // Within last 2 hours
            ->whereNotNull('email')
            ->get();

        foreach ($firstReminders as $cart) {
            try {
                if ($cart->customer) {
                    Notification::send($cart->customer, new AbandonedCartNotification($cart));
                } else {
                    Notification::route('mail', $cart->email)
                        ->notify(new AbandonedCartNotification($cart));
                }
                
                $cart->update(['reminder_sent_at' => now()]);
                
                Log::info('Sent abandoned cart reminder', [
                    'cart_id' => $cart->id,
                    'email' => $cart->email,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send abandoned cart reminder', [
                    'cart_id' => $cart->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Send second reminder for carts abandoned 24 hours ago (if not recovered)
        $twentyFourHoursAgo = now()->subDay();
        $secondReminders = AbandonedCart::query()
            ->whereNull('recovered_at')
            ->whereNotNull('reminder_sent_at')
            ->where('reminder_sent_at', '<=', $twentyFourHoursAgo)
            ->where('abandoned_at', '<=', $twentyFourHoursAgo)
            ->where('abandoned_at', '>=', now()->subDays(2)) // Within last 2 days
            ->whereNotNull('email')
            ->get();

        foreach ($secondReminders as $cart) {
            try {
                if ($cart->customer) {
                    Notification::send($cart->customer, new AbandonedCartNotification($cart));
                } else {
                    Notification::route('mail', $cart->email)
                        ->notify(new AbandonedCartNotification($cart));
                }
                
                Log::info('Sent second abandoned cart reminder', [
                    'cart_id' => $cart->id,
                    'email' => $cart->email,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send second abandoned cart reminder', [
                    'cart_id' => $cart->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
