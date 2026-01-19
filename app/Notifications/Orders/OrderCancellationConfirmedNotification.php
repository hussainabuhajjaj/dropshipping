<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCancellationConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ?string $refundAmount = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_number' => $this->order->number,
            'cancelled_at' => now(),
            'refund_amount' => $this->refundAmount,
            'order_url' => url("/orders/track?number={$this->order->number}&email={$this->order->email}"),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? ($this->order->guest_name ?? $this->order->email ?? 'Customer');

        return (new MailMessage)
            ->subject("Order #{$this->order->number} has been cancelled")
            ->greeting("Hi {$name},")
            ->line("Your order #{$this->order->number} has been successfully cancelled.")
            ->when($this->refundAmount, function ($mail) {
                return $mail->line("A refund of {$this->order->currency} {$this->refundAmount} will be processed to your original payment method.")
                    ->line('Depending on your bank, the refund may take 3-5 business days to appear.');
            })
            ->action('View order', url("/orders/track?number={$this->order->number}&email={$this->order->email}"))
            ->line('If you have any questions, please contact our support team.');
    }
}
