<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $refundAmount = $this->order->refund_amount ? number_format($this->order->refund_amount / 100, 2) : '0.00';
        $reasonLabel = $this->order->refund_reason?->label() ?? 'Refund';

        return (new MailMessage())
            ->subject("Refund Approved for Order {$this->order->order_number}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("We're sorry for the inconvenience. Your refund has been approved.")
            ->line('')
            ->line("**Order Number:** #{$this->order->order_number}")
            ->line("**Refund Amount:** \$" . $refundAmount)
            ->line("**Reason:** $reasonLabel")
            ->line('')
            ->line('Your refund will be processed and appear in your account within 3-5 business days.')
            ->line('')
            ->when($this->order->refund_notes, function ($message) {
                return $message->line("**Note:** {$this->order->refund_notes}");
            })
            ->line('')
            ->action('View Order Details', route('customer.orders.show', $this->order->id))
            ->line('If you have any questions, feel free to reach out to our support team.')
            ->salutation('Thank you for your understanding');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'refund_amount' => $this->order->refund_amount,
            'refund_reason' => $this->order->refund_reason?->value,
        ];
    }
}
