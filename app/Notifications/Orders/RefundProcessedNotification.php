<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Models\Order;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Currency\CustomerMoneyPresenter;
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
        $presenter = app(CustomerMoneyPresenter::class);
        $xof = $presenter->displayCurrency();
        $presented = $presenter->present((float) $this->amount, (string) $this->currency);

        return (new MailMessage)
            ->subject("Refund processed for order #{$this->order->number}")
            ->greeting("Hi {$name},")
            ->line("We’ve processed your refund of {$xof} {$presented['formatted']} for order #{$this->order->number}.")
            ->when(! $presented['ok'], fn (MailMessage $mail) => $mail->line('Note: FX conversion was unavailable; displayed totals may be inaccurate.'))
            ->line($this->reason ? "Reason: {$this->reason}" : null)
            ->line('Depending on your payment provider, it may take a few days to appear.')
            ->action('View order', url("/orders/track?number={$this->order->number}&email={$this->order->email}"));
    }

    public function toWhatsApp(object $notifiable): string
    {
        $presenter = app(CustomerMoneyPresenter::class);
        $xof = $presenter->displayCurrency();
        $presented = $presenter->present((float) $this->amount, (string) $this->currency);
        $reason = $this->reason ? " Reason: {$this->reason}." : '';
        $note = ! $presented['ok'] ? ' (FX unavailable)' : '';
        return "Your refund for order #{$this->order->number} was processed: {$xof} {$presented['formatted']}{$note}.{$reason} It may take a few days to appear.";
    }
}
