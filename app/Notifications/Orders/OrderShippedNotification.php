<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Domain\Orders\Models\Order;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderShippedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ?string $trackingNumber = null,
        public ?string $carrier = null,
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
            'tracking_number' => $this->trackingNumber,
            'carrier' => $this->carrier,
            'tracking_url' => $this->trackingLink(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? ($this->order->guest_name ?? $this->order->email ?? 'Customer');

        $message = (new MailMessage)
            ->subject("Your order #{$this->order->number} has shipped!")
            ->greeting("Hi {$name},")
            ->line("Great news! Your order #{$this->order->number} is on its way.");

        if ($this->trackingNumber) {
            $message->line("Tracking Number: {$this->trackingNumber}");
        }

        if ($this->carrier) {
            $message->line("Carrier: {$this->carrier}");
        }

        $message->action('Track Your Package', $this->trackingLink())
            ->line('Delivery usually takes 7-14 business days depending on your location.')
            ->line('Thank you for your order!');

        return $message;
    }

    public function toWhatsApp(object $notifiable): string
    {
        $tracking = $this->trackingNumber ? " Tracking: {$this->trackingNumber}." : '';
        return "Your order #{$this->order->number} has shipped!{$tracking} Track it here: {$this->trackingLink()}";
    }

    private function trackingLink(): string
    {
        if ($this->trackingUrl) {
            return $this->trackingUrl;
        }

        return url("/orders/track?number={$this->order->number}&email={$this->order->email}");
    }
}
