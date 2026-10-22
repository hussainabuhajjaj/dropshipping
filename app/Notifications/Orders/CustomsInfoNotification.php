<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Models\Order;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomsInfoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ?string $note = null,
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
            'note' => $this->note,
            'tracking_url' => $this->trackingLink(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Customs information for order #{$this->order->number}")
            ->greeting("Hi " . ($notifiable->name ?? ($this->order->customer_name ?? $this->order->email ?? 'Customer')) . ',')
            ->line('Your shipment is at customs. Duties/VAT were shown at checkout; the carrier may contact you.')
            ->line($this->note)
            ->action('Track order', $this->trackingLink())
            ->line('If you need help with customs, reply to this message.');
    }

    public function toWhatsApp(object $notifiable): string
    {
        $note = $this->note ? " {$this->note}" : '';

        return "Heads up: order #{$this->order->number} is in customs."
            . " Duties/VAT were estimated at checkout; carrier may request ID or payment."
            . $note
            . " Track: {$this->trackingLink()}";
    }

    private function trackingLink(): string
    {
        return $this->trackingUrl ?? url("/orders/track?number={$this->order->number}&email={$this->order->email}");
    }
}
