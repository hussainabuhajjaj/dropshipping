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
            'payment_method' => $this->payment->provider,
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

        // Log for debugging
        \Log::info('PaymentReceiptNotification items count', [
            'order_id' => $this->order->id,
            'order_number' => $this->order->number,
            'items_count' => $items->count(),
            'items' => $items->toArray(),
        ]);

        // Calculate subtotal from actual order items for accuracy
        $calculatedSubtotal = $items->sum(function ($item) {
            return (float) ($item->total ?? 0);
        });

        // Fallback to order's stored subtotal if calculated is 0 but order has subtotal
        if ($calculatedSubtotal === 0 && $this->order->subtotal > 0) {
            $calculatedSubtotal = (float) $this->order->subtotal;
            \Log::info('PaymentReceiptNotification: Using order subtotal as fallback', [
                'order_id' => $this->order->id,
                'order_number' => $this->order->number,
                'calculated_subtotal' => 0,
                'order_subtotal' => $this->order->subtotal,
            ]);
        }

        // Build items list with fallback for missing data
        $itemsList = $items->map(function ($item) use ($presenter, $fromCurrency, $xof) {
            // Try to get product name from snapshot first, then from relationships
            $productName = 'Product';
            if (isset($item->snapshot['name']) && !empty($item->snapshot['name'])) {
                $productName = $item->snapshot['name'];
            } elseif ($item->productVariant && $item->productVariant->product) {
                $productName = $item->productVariant->product->name;
            }

            $variant = $item->snapshot['variant'] ?? '';
            $variantText = $variant ? " ({$variant})" : '';
            $qty = (int) ($item->quantity ?? 1);

            // Detect if price is in USD (small values) but order currency is XOF
            $itemTotal = (float) ($item->total ?? 0);
            $itemCurrency = $fromCurrency;

            // If total is very small (< 1000) and currency is XOF, it's likely USD that wasn't converted
            if ($fromCurrency === $xof && $itemTotal > 0 && $itemTotal < 1000) {
                $itemCurrency = 'USD'; // Treat as USD
            }

            if ($itemCurrency === $xof) {
                $lineTotalFormatted = $presenter->format($itemTotal, $xof);
            } else {
                $lineTotal = $presenter->present($itemTotal, $itemCurrency, $this->payment);
                $lineTotalFormatted = $lineTotal['formatted'];
            }

            return "{$productName}{$variantText} × {$qty} — {$xof} {$lineTotalFormatted}";
        })->implode("\n");

        // Fallback if items list is empty
        if (empty($itemsList) || $items->count() === 0) {
            $itemsList = 'No items details available';
            \Log::warning('PaymentReceiptNotification: No items found', [
                'order_id' => $this->order->id,
                'order_number' => $this->order->number,
                'items_count' => $items->count(),
            ]);
        }

        // Use order's stored totals directly to match payment summary
        $orderSubtotal = (float) ($this->order->subtotal ?? 0);
        $orderShipping = (float) ($this->order->shipping_total ?? 0);
        $orderTax = (float) ($this->order->tax_total ?? 0);
        $orderDiscount = (float) ($this->order->discount_total ?? 0);
        $orderGrandTotal = (float) ($this->order->grand_total ?? 0);

        // Format totals in XOF
        $subtotalFmt = $presenter->format($orderSubtotal, $xof);
        $shippingFmt = $presenter->format($orderShipping, $xof);
        $taxFmt = $presenter->format($orderTax, $xof);
        $discountFmt = $presenter->format($orderDiscount, $xof);

        // Total paid as XOF - use order's grand_total directly (already in correct currency)
        $paidAmountRaw = $orderGrandTotal;
        $paidPresented = [
            'amount' => $paidAmountRaw,
            'formatted' => $presenter->format($paidAmountRaw, $xof),
            'ok' => true
        ];

        $totals = $presenter->presentOrderTotals($this->order, $this->payment);

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
            ->when($this->order->discount_total > 0, function ($mail) use ($totals) {
                $presenter = app(CustomerMoneyPresenter::class);
                $xof = $presenter->displayCurrency();
                return $mail->line("**Discount:** -{$xof} " . $presenter->format($totals['discount'], $xof));
            })
            ->when($this->order->tax_total > 0, function ($mail) use ($totals) {
                $presenter = app(CustomerMoneyPresenter::class);
                $xof = $presenter->displayCurrency();
                return $mail->line("**Tax:** {$xof} " . $presenter->format($totals['tax'], $xof));
            })
            ->line("**Total Paid:** {$xof} {$paidPresented['formatted']}")
            ->when(! $totals['ok'] || ! $paidPresented['ok'], fn (MailMessage $mail) => $mail->line('Note: FX conversion was unavailable; displayed totals may be inaccurate.'))
            ->line('')
            ->line("**Payment Method:** " . $this->getFormattedPaymentMethod())
            ->line("**Payment ID:** {$this->payment->provider_reference}")
            ->line("**Date:** " . $this->payment->paid_at?->format('M d, Y h:i A'))
            ->action('View Order', url("/orders/track?number={$this->order->number}&email={$this->order->email}"))
            ->line('Keep this email for your records.');
    }

    private function getFormattedPaymentMethod(): string
    {
        $provider = $this->payment->provider ?? 'paystack';

        return match($provider) {
            'mobile_money' => 'Mobile Money',
            'card' => 'Card',
            'paystack' => 'Paystack',
            default => ucfirst(str_replace('_', ' ', $provider)),
        };
    }
}
