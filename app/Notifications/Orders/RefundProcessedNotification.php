<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Models\Order;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundProcessedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public float $amount,
        public string $currency,
        public ?string $reason = null,
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
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'order_url' => url("/orders/track?number={$this->order->number}&email={$this->order->email}"),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? ($this->order->customer_name ?? $this->order->email ?? 'Customer');

        return (new MailMessage)
            ->subject("Refund processed for order #{$this->order->number}")
            ->greeting("Hi {$name},")
            ->line("We’ve processed your refund of {$this->currency} {$this->amount} for order #{$this->order->number}.")
            ->line($this->reason ? "Reason: {$this->reason}" : null)
            ->line('Depending on your payment provider, it may take a few days to appear.')
            ->action('View order', url("/orders/track?number={$this->order->number}&email={$this->order->email}"));
    }

    public function toWhatsApp(object $notifiable): string
    {
        $reason = $this->reason ? " Reason: {$this->reason}." : '';
        return "Your refund for order #{$this->order->number} was processed: {$this->currency} {$this->amount}.{$reason} It may take a few days to appear.";
    }
}
