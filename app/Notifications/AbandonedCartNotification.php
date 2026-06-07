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

    public function __construct(
        private readonly AbandonedCart $cart,
        private readonly int $reminderNumber = 1,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toArray(object $notifiable): array
    {
        $messages = [
            1 => [
                'title' => 'You left items in your cart',
                'body' => 'Complete your purchase before items sell out.',
            ],
            2 => [
                'title' => 'Still thinking about it?',
                'body' => 'Your cart is still here. Use code SAVE10 for 10% off.',
            ],
            3 => [
                'title' => 'Last chance! Your cart is expiring',
                'body' => 'Don\'t miss out — complete your order now with 10% off. Code: SAVE10',
            ],
        ];

        $msg = $messages[$this->reminderNumber] ?? $messages[1];

        return [
            'title' => $msg['title'],
            'body' => $msg['body'],
            'action_url' => url('/cart'),
            'action_label' => 'Resume checkout',
        ];
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

        $subjects = [
            1 => 'You left items in your cart',
            2 => 'Still thinking about it? Get 10% off',
            3 => 'Last chance! Your cart is expiring',
        ];

        $intros = [
            1 => 'You have items waiting in your cart. We saved them for you:',
            2 => 'Your cart is still here! Complete your purchase today and use code <strong>SAVE10</strong> for 10% off.',
            3 => 'This is your last reminder — your cart will expire soon. Use code <strong>SAVE10</strong> to get 10% off before it\'s too late.',
        ];

        $subject = $subjects[$this->reminderNumber] ?? $subjects[1];
        $intro = $intros[$this->reminderNumber] ?? $intros[1];

        $mail = (new MailMessage())
            ->subject($subject)
            ->greeting('Complete your purchase')
            ->line($intro)
            ->line($lines !== '' ? $lines : 'Items saved for you.')
            ->action('Resume Checkout', url('/cart'))
            ->line('If you already completed your order, you can ignore this email.');

        if ($this->reminderNumber > 1) {
            $mail->line('—');
            $mail->line('Use code <strong>SAVE10</strong> at checkout for 10% off your order.');
        }

        return $mail;
    }
}
