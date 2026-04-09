<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Notifications\Channels\WhatsAppChannel;
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
        $presented = $this->presentedTotal();

        return [
            'order_number' => $this->order->number,
            'status' => $this->order->status,
            'payment_status' => $this->order->payment_status,
            'total' => $presented['amount'],
            'currency' => $presented['currency'],
            'order_total' => $this->order->grand_total,
            'order_currency' => $this->order->currency,
            'tracking_url' => $this->trackingLink(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? ($this->order->guest_name ?? $this->order->email ?? 'Customer');
        $presented = $this->presentedTotal();
        $line = "Total: {$presented['currency']} {$presented['formatted']}";

        $orderCurrency = strtoupper((string) ($this->order->currency ?? 'USD'));
        $presentedCurrency = strtoupper((string) $presented['currency']);

        if ($presentedCurrency !== '' && $presentedCurrency !== $orderCurrency) {
            $line .= " (Order total: {$orderCurrency} " . $this->formatMoney((float) ($this->order->grand_total ?? 0), $orderCurrency) . ')';
        }
        
        return (new MailMessage)
            ->subject("We've received your order #{$this->order->number}")
            ->greeting("Hi {$name},")
            ->line("Order #{$this->order->number} is confirmed.")
            ->line($line)
            ->action('Track order', $this->trackingLink())
            ->line('We’ll send tracking once the supplier ships. Duties and VAT were shown at checkout.');
    }

    public function toWhatsApp(object $notifiable): string
    {
        $presented = $this->presentedTotal();
        $line = "Total: {$presented['currency']} {$presented['formatted']}";
        return "Hi {$notifiable->name}, order #{$this->order->number} is confirmed. {$line}. Track: {$this->trackingLink()}";
    }

    private function trackingLink(): string
    {
        return $this->trackingUrl ?? url("/orders/track?number={$this->order->number}&email={$this->order->email}");
    }

    /**
     * Prefer the provider-facing charged amount/currency (payment) when available.
     *
     * @return array{amount: float, currency: string, formatted: string}
     */
    private function presentedTotal(): array
    {
        $paymentCurrency = strtoupper((string) ($this->payment?->currency ?? ''));
        $paymentAmount = is_numeric($this->payment?->amount) ? (float) $this->payment->amount : null;

        if ($paymentCurrency !== '' && $paymentAmount !== null && $paymentAmount > 0) {
            return [
                'amount' => $paymentAmount,
                'currency' => $paymentCurrency,
                'formatted' => $this->formatMoney($paymentAmount, $paymentCurrency),
            ];
        }

        $orderCurrency = strtoupper((string) ($this->order->currency ?? 'USD'));
        $orderAmount = (float) ($this->order->grand_total ?? 0);

        return [
            'amount' => $orderAmount,
            'currency' => $orderCurrency,
            'formatted' => $this->formatMoney($orderAmount, $orderCurrency),
        ];
    }

    private function formatMoney(float $amount, string $currency): string
    {
        $currency = strtoupper($currency);
        $decimals = (int) (config('currency.decimals.' . $currency) ?? ($currency === 'XOF' || $currency === 'XAF' ? 0 : 2));
        return number_format($amount, $decimals, '.', ',');
    }
}
