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
use Illuminate\Support\Facades\Notification;

class ProcessAbandonedCartsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function handle(): void
    {
        $cutoff = now()->subMinutes(60);

        AbandonedCart::query()
            ->whereNull('recovered_at')
            ->whereNull('reminder_sent_at')
            ->whereNotNull('email')
            ->where('last_activity_at', '<=', $cutoff)
            ->limit(50)
            ->get()
            ->each(function (AbandonedCart $cart) {
                if ($cart->customer) {
                    Notification::send($cart->customer, new AbandonedCartNotification($cart));
                } else {
                    $notifiable = Notification::route('mail', $cart->email);
                    $notifiable->notify(new AbandonedCartNotification($cart));
                }

                $cart->update(['reminder_sent_at' => now()]);
            });
    }
}
