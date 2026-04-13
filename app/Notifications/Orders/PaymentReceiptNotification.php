<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Services\Currency\CustomerMoneyPresenter;
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
        $presenter = app(CustomerMoneyPresenter::class);
        $xof = $presenter->displayCurrency();
        $presented = $presenter->present(
            is_numeric($this->payment->amount) ? (float) $this->payment->amount : 0.0,
            (string) ($this->payment->currency ?? $this->order->currency ?? config('currency.base', 'USD')),
            $this->payment
        );

        return [
            'order_number' => $this->order->number,
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
            'amount_xof' => $presented['amount'],
            'currency_xof' => $xof,
            'payment_method' => $this->payment->method,
            'paid_at' => $this->payment->paid_at,
            'order_url' => url("/orders/track?number={$this->order->number}&email={$this->order->email}"),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? ($this->order->guest_name ?? $this->order->email ?? 'Customer');
        $items = $this->order->orderItems()->with('productVariant.product')->get();
        $presenter = app(CustomerMoneyPresenter::class);
        $fromCurrency = (string) ($this->order->currency ?? config('currency.base', 'USD'));
        $xof = $presenter->displayCurrency();

        $itemsList = $items->map(function ($item) use ($presenter, $fromCurrency, $xof) {
            $productName = $item->snapshot['name'] ?? $item->productVariant?->product?->name ?? 'Product';
            $variant = $item->snapshot['variant'] ?? '';
            $variantText = $variant ? " ({$variant})" : '';
            $qty = (int) ($item->quantity ?? 1);

            $lineTotal = $presenter->present((float) ($item->total ?? 0), $fromCurrency, $this->payment);
            return "{$productName}{$variantText} × {$qty} — {$xof} {$lineTotal['formatted']}";
        })->implode("\n");

        $totals = $presenter->presentOrderTotals($this->order, $this->payment);
        $subtotalFmt = $presenter->format($totals['subtotal'], $xof);
        $shippingFmt = $presenter->format($totals['shipping'], $xof);
        $taxFmt = $presenter->format($totals['tax'], $xof);
        $discountFmt = $presenter->format($totals['discount'], $xof);

        // Total paid as XOF (convert provider currency to XOF if needed).
        $paidFromCurrency = (string) ($this->payment->currency ?? $fromCurrency);
        $paidAmountRaw = is_numeric($this->payment->amount) ? (float) $this->payment->amount : (float) ($this->order->grand_total ?? 0);
        $paidPresented = $presenter->present($paidAmountRaw, $paidFromCurrency, $this->payment);

        return (new MailMessage)
            ->subject("Payment Receipt — Order #{$this->order->number}")
            ->greeting("Hi {$name},")
            ->line("Thank you for your payment. Here's your receipt for order #{$this->order->number}.")
            ->line('')
            ->line('**Order Items:**')
            ->line($itemsList)
            ->line('')
            ->line("**Subtotal:** {$xof} {$subtotalFmt}")
            ->line("**Shipping:** {$xof} {$shippingFmt}")
            ->when($this->order->discount_total > 0, function ($mail) {
                $presenter = app(CustomerMoneyPresenter::class);
                $xof = $presenter->displayCurrency();
                $totals = $presenter->presentOrderTotals($this->order, $this->payment);
                return $mail->line("**Discount:** -{$xof} " . $presenter->format($totals['discount'], $xof));
            })
            ->when($this->order->tax_total > 0, function ($mail) {
                $presenter = app(CustomerMoneyPresenter::class);
                $xof = $presenter->displayCurrency();
                $totals = $presenter->presentOrderTotals($this->order, $this->payment);
                return $mail->line("**Tax:** {$xof} " . $presenter->format($totals['tax'], $xof));
            })
            ->line("**Total Paid:** {$xof} {$paidPresented['formatted']}")
            ->when(! $totals['ok'] || ! $paidPresented['ok'], fn (MailMessage $mail) => $mail->line('Note: FX conversion was unavailable; displayed totals may be inaccurate.'))
            ->line('')
            ->line("**Payment Method:** " . ucfirst(str_replace('_', ' ', $this->payment->method ?? 'card')))
            ->line("**Payment ID:** {$this->payment->gateway_transaction_id}")
            ->line("**Date:** " . $this->payment->paid_at?->format('M d, Y h:i A'))
            ->action('View Order', url("/orders/track?number={$this->order->number}&email={$this->order->email}"))
            ->line('Keep this email for your records.');
    }
}
