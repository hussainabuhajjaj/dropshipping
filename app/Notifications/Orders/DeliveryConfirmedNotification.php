<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Models\Order;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliveryConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ?string $deliveredAt = null,
        public ?string $trackingUrl = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail', WhatsAppChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_number' => $this->order->number,
            'status' => $this->order->status,
            'delivered_at' => $this->deliveredAt,
            'tracking_url' => $this->trackingLink(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? ($this->order->customer_name ?? $this->order->email ?? 'Customer');

        return (new MailMessage)
            ->subject("Delivered: order #{$this->order->number}")
            ->greeting("Hi {$name},")
            ->line("Your order #{$this->order->number} was delivered" . ($this->deliveredAt ? " on {$this->deliveredAt}" : '') . '.')
            ->action('View order', $this->trackingLink())
            ->line('If anything is off, reply and we will help immediately.');
    }

    public function toWhatsApp(object $notifiable): string
    {
        $date = $this->deliveredAt ? " on {$this->deliveredAt}" : '';
        return "Order #{$this->order->number} shows delivered{$date}. If anything is off, reply and we’ll fix it. Track: {$this->trackingLink()}";
    }

    private function trackingLink(): string
    {
        return $this->trackingUrl ?? url("/orders/track?number={$this->order->number}&email={$this->order->email}");
    }
}
