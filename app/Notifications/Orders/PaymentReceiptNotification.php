<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceiptNotification extends Notification
{

    public function __construct(
        public Order $order,
        public Payment $payment,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_number' => $this->order->number,
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
            'payment_method' => $this->payment->method,
            'paid_at' => $this->payment->paid_at,
            'order_url' => url("/orders/track?number={$this->order->number}&email={$this->order->email}"),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? ($this->order->guest_name ?? $this->order->email ?? 'Customer');
        $items = $this->order->orderItems()->with('productVariant.product')->get();

        $itemsList = $items->map(function ($item) {
            $productName = $item->snapshot['name'] ?? $item->productVariant?->product?->name ?? 'Product';
            $variant = $item->snapshot['variant'] ?? '';
            $variantText = $variant ? " ({$variant})" : '';
            $qty = $item->quantity;
            $price = number_format((float) $item->unit_price, 2);
            $orderCurrency = strtoupper((string) ($this->order->currency ?? 'USD'));
            $total = number_format((float) $item->total, $orderCurrency === 'USD' ? 3 : ($orderCurrency === 'XOF' || $orderCurrency === 'XAF' ? 0 : 2));
            return "{$productName}{$variantText} × {$qty} — {$orderCurrency} {$total}";
        })->implode("\n");

        $orderCurrency = strtoupper((string) ($this->order->currency ?? 'USD'));
        $paidCurrency = strtoupper((string) ($this->payment->currency ?? $orderCurrency));
        $paidAmount = is_numeric($this->payment->amount) ? (float) $this->payment->amount : (float) ($this->order->grand_total ?? 0);
        $paidDecimals = (int) (config('currency.decimals.' . $paidCurrency) ?? ($paidCurrency === 'XOF' || $paidCurrency === 'XAF' ? 0 : 2));

        return (new MailMessage)
            ->subject("Payment Receipt — Order #{$this->order->number}")
            ->greeting("Hi {$name},")
            ->line("Thank you for your payment. Here's your receipt for order #{$this->order->number}.")
            ->line('')
            ->line('**Order Items:**')
            ->line($itemsList)
            ->line('')
            ->line("**Subtotal:** {$orderCurrency} " . number_format((float) $this->order->subtotal, $orderCurrency === 'USD' ? 3 : ($orderCurrency === 'XOF' || $orderCurrency === 'XAF' ? 0 : 2)))
            ->line("**Shipping:** {$orderCurrency} " . number_format((float) $this->order->shipping_total, $orderCurrency === 'USD' ? 3 : ($orderCurrency === 'XOF' || $orderCurrency === 'XAF' ? 0 : 2)))
            ->when($this->order->discount_total > 0, function ($mail) {
                $orderCurrency = strtoupper((string) ($this->order->currency ?? 'USD'));
                return $mail->line("**Discount:** -{$orderCurrency} " . number_format((float) $this->order->discount_total, $orderCurrency === 'USD' ? 3 : ($orderCurrency === 'XOF' || $orderCurrency === 'XAF' ? 0 : 2)));
            })
            ->when($this->order->tax_total > 0, function ($mail) {
                $orderCurrency = strtoupper((string) ($this->order->currency ?? 'USD'));
                return $mail->line("**Tax:** {$orderCurrency} " . number_format((float) $this->order->tax_total, $orderCurrency === 'USD' ? 3 : ($orderCurrency === 'XOF' || $orderCurrency === 'XAF' ? 0 : 2)));
            })
            // Always show what the provider actually charged (XOF/XAF for mobile_money).
            ->line("**Total Paid:** {$paidCurrency} " . number_format($paidAmount, $paidDecimals, '.', ','))
            ->line('')
            ->line("**Payment Method:** " . ucfirst(str_replace('_', ' ', $this->payment->method ?? 'card')))
            ->line("**Payment ID:** {$this->payment->gateway_transaction_id}")
            ->line("**Date:** " . $this->payment->paid_at?->format('M d, Y h:i A'))
            ->action('View Order', url("/orders/track?number={$this->order->number}&email={$this->order->email}"))
            ->line('Keep this email for your records.');
    }
}
