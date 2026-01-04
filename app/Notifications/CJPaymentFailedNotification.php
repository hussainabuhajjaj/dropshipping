<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CJPaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
        private readonly string $error,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("⚠️ CJ Payment Failed - Order {$this->order->number}")
            ->greeting("CJ Dropshipping Payment Failed")
            ->line("Order: {$this->order->number}")
            ->line("Customer: {$this->order->shippingAddress?->name}")
            ->line("Amount: {$this->order->cj_amount_due} {$this->order->currency}")
            ->line("Error: {$this->error}")
            ->line("Attempts: {$this->order->cj_payment_attempts}/3")
            ->action('View Order', route('filament.admin.resources.orders.view', $this->order))
            ->line("The system will retry up to 3 times. You can also manually retry in the admin panel.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->number,
            'error' => $this->error,
            'attempts' => $this->order->cj_payment_attempts,
        ];
    }
}
