<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Models\Order;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShippingDelayNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ?string $eta = null,
        public ?string $reason = null,
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
            'eta' => $this->eta,
            'reason' => $this->reason,
            'tracking_url' => $this->trackingLink(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Update on your order #{$this->order->number} — slight delay")
            ->greeting("Hi " . ($notifiable->name ?? ($this->order->customer_name ?? $this->order->email ?? 'Customer')) . ',')
            ->line("We’re seeing a delay on order #{$this->order->number}.")
            ->line($this->reason ? "Reason: {$this->reason}" : null)
            ->line($this->eta ? "New ETA: {$this->eta}" : null)
            ->action('Track order', $this->trackingLink())
            ->line('We’re monitoring closely and will keep you updated.');
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "Sorry for the delay on order #{$this->order->number}."
            . ($this->eta ? " New ETA: {$this->eta}." : '')
            . ($this->reason ? " Reason: {$this->reason}." : '')
            . " Track: {$this->trackingLink()}";
    }

    private function trackingLink(): string
    {
        return $this->trackingUrl ?? url("/orders/track?number={$this->order->number}&email={$this->order->email}");
    }
}
