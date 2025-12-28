<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Domain\Orders\Models\Order;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public ?string $trackingUrl = null)
    {
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
            'payment_status' => $this->order->payment_status,
            'total' => $this->order->grand_total,
            'currency' => $this->order->currency,
            'tracking_url' => $this->trackingLink(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? ($this->order->guest_name ?? $this->order->email ?? 'Customer');
        
        return (new MailMessage)
            ->subject("We've received your order #{$this->order->number}")
            ->greeting("Hi {$name},")
            ->line("Order #{$this->order->number} is confirmed.")
            ->line("Total: {$this->order->currency} {$this->order->grand_total}")
            ->action('Track order', $this->trackingLink())
            ->line('We’ll send tracking once the supplier ships. Duties and VAT were shown at checkout.');
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "Hi {$notifiable->name}, order #{$this->order->number} is confirmed. Total: {$this->order->currency} {$this->order->grand_total}. Track: {$this->trackingLink()}";
    }

    private function trackingLink(): string
    {
        return $this->trackingUrl ?? url("/orders/track?number={$this->order->number}&email={$this->order->email}");
    }
}
