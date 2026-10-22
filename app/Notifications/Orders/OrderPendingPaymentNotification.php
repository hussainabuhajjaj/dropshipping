<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Domain\Orders\Models\Order;
use App\Services\Currency\CustomerMoneyPresenter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class OrderPendingPaymentNotification extends Notification
{

    public function __construct(public Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->order->loadMissing('orderItems.productVariant.product.images');

        $name = $notifiable->name ?? ($this->order->guest_name ?? $this->order->email ?? 'Customer');
        $items = $this->order->orderItems ?? collect();

        $presenter = app(CustomerMoneyPresenter::class);
        $currency = $presenter->displayCurrency(); // Always XOF for customers
        $fromCurrency = (string) ($this->order->currency ?? config('currency.base', 'USD'));
        $paymentUrl = route('orders.confirmation', ['number' => $this->order->number]);

        $lines = $items->map(function ($item) use ($presenter, $fromCurrency) {
            $snapshot = $item->snapshot ?? [];
            $meta = $item->meta ?? [];
            $name = $snapshot['name'] ?? 'Item';
            $variant = $snapshot['variant'] ?? null;
            $qty = (int) ($item->quantity ?? 1);
            $unitPresented = $presenter->present((float) ($item->unit_price ?? 0), $fromCurrency);
            $totalPresented = $presenter->present((float) ($item->total ?? 0), $fromCurrency);

            $media = is_array($meta['media'] ?? null) ? $meta['media'] : [];
            $image = $media[0] ?? null;
            if (! $image) {
                $image = $item->productVariant?->product?->images?->sortBy('position')->first()?->url;
            }

            return [
                'name' => $name,
                'variant' => $variant,
                'qty' => $qty,
                // Pass numeric amounts; the Blade view formats based on currency decimals (XOF => 0).
                'unit' => (float) $unitPresented['amount'],
                'total' => (float) $totalPresented['amount'],
                'image' => $image,
            ];
        })->values()->all();

        $totals = $presenter->presentOrderTotals($this->order);
        $summary = [
            'subtotal' => (float) $totals['subtotal'],
            'shipping' => (float) $totals['shipping'],
            'tax' => (float) $totals['tax'],
            'discount' => (float) $totals['discount'],
            'grand_total' => (float) $totals['total'],
        ];

        $preheader = "Complete payment to confirm order #{$this->order->number}.";

        $bodyHtml = view('emails.orders.pending-payment-body', [
            'name' => $name,
            'order' => $this->order,
            'currency' => $currency,
            'paymentUrl' => $paymentUrl,
            'items' => $lines,
            'summary' => $summary,
        ])->render();

        return (new MailMessage)
            ->subject("Payment pending for order #{$this->order->number}")
            ->view('emails.base', [
                'title' => "Finish your order #{$this->order->number}",
                'preheader' => Str::limit($preheader, 120),
                'bodyHtml' => $bodyHtml,
                'actionUrl' => $paymentUrl,
                'actionLabel' => 'Complete payment',
            ]);
    }
}
