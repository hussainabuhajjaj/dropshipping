<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Currency\CustomerMoneyPresenter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmedNotification extends Notification
{

    public function __construct(
        public Order $order,
        public ?string $trackingUrl = null,
        public ?Payment $payment = null,
    )
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail', WhatsAppChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $presented = $this->presentedTotalXof();

        return [
            'order_number' => $this->order->number,
            'status' => $this->order->status,
            'payment_status' => $this->order->payment_status,
            'total' => $presented['amount'],
            'currency' => $presented['currency'],
            'order_total' => $this->order->grand_total,
            'order_currency' => $this->order->currency,
            'total_xof' => $presented['amount'],
            'currency_xof' => 'XOF',
            'tracking_url' => $this->trackingLink(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? ($this->order->guest_name ?? $this->order->email ?? 'Customer');
        $presented = $this->presentedTotalXof();
        $line = "Total: {$presented['currency']} {$presented['formatted']}";
        $giveawayLine = $this->giveawayLine();
        
        $mail = (new MailMessage)
            ->subject("We've received your order #{$this->order->number}")
            ->greeting("Hi {$name},")
            ->line("Order #{$this->order->number} is confirmed.")
            ->line($line)
            ->when(! $presented['ok'], fn (MailMessage $mail) => $mail->line('Note: FX conversion was unavailable; displayed totals may be inaccurate.'))
            ->action('Track order', $this->trackingLink())
            ->line('We’ll send tracking once the supplier ships. Duties and VAT were shown at checkout.');

        if ($giveawayLine) {
            $mail->line('')->line($giveawayLine);
        }

        return $mail;
    }

    public function toWhatsApp(object $notifiable): string
    {
        $presented = $this->presentedTotalXof();
        $line = "Total: {$presented['currency']} {$presented['formatted']}";
        $giveawayLine = $this->giveawayLine();
        $message = "Hi {$notifiable->name}, order #{$this->order->number} is confirmed. {$line}. Track: {$this->trackingLink()}";
        if ($giveawayLine) {
            $message .= " 🎉 {$giveawayLine}";
        }
        return $message;
    }

    private function giveawayLine(): ?string
    {
        $threshold = $this->order->currency === 'USD' ? 50 : 30000;
        $subtotal = (float) ($this->order->subtotal ?? 0);
        if ($subtotal >= $threshold) {
            return '🎉 Congratulations! You\'ve been entered into the iPhone 17 giveaway! Visit ' . url('/promotions/iphone-giveaway') . ' for details.';
        }
        return null;
    }

    private function trackingLink(): string
    {
        return $this->trackingUrl ?? url("/orders/track?number={$this->order->number}&email={$this->order->email}");
    }

    /**
     * Customer-facing totals are always XOF.
     *
     * @return array{amount: float, currency: string, formatted: string, ok: bool}
     */
    private function presentedTotalXof(): array
    {
        $presenter = app(CustomerMoneyPresenter::class);

        // Prefer exact charged XOF when available.
        $paymentCurrency = strtoupper((string) ($this->payment?->currency ?? ''));
        $paymentAmount = is_numeric($this->payment?->amount) ? (float) $this->payment->amount : null;

        if ($paymentCurrency === 'XOF' && $paymentAmount !== null && $paymentAmount > 0) {
            return [
                'amount' => $paymentAmount,
                'currency' => 'XOF',
                'formatted' => $presenter->format($paymentAmount, 'XOF'),
                'ok' => true,
            ];
        }

        $orderCurrency = (string) ($this->order->currency ?? config('currency.base', 'USD'));
        $orderAmount = (float) ($this->order->grand_total ?? 0);
        $presented = $presenter->present($orderAmount, $orderCurrency, $this->payment);

        return [
            'amount' => (float) $presented['amount'],
            'currency' => (string) $presented['currency'],
            'formatted' => (string) $presented['formatted'],
            'ok' => (bool) $presented['ok'],
        ];
    }
}
