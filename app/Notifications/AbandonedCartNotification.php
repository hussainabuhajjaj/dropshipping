<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AbandonedCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AbandonedCartNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly AbandonedCart $cart)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cart = $this->cart->cart_data ?? [];
        $lines = collect($cart)->map(function (array $line) {
            $name = $line['name'] ?? 'Item';
            $qty = (int) ($line['quantity'] ?? 1);
            $price = (float) ($line['price'] ?? 0);
            $subtotal = $qty * $price;
            return "{$name} x{$qty} — $" . number_format($subtotal, 2);
        })->implode("\n");

        return (new MailMessage())
            ->subject('You left items in your cart')
            ->greeting('Complete your purchase')
            ->line('You have items waiting in your cart. We saved them for you:')
            ->line($lines !== '' ? $lines : 'Items saved for you.')
            ->action('Resume Checkout', url('/cart'))
            ->line('If you already completed your order, you can ignore this email.');
    }
}
