<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Domain\Orders\Models\Order;
use App\Services\Currency\CustomerMoneyPresenter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class OrderPaymentLinkNotification extends Notification
{
    public function __construct(
        public Order $order,
        public string $paymentUrl,
    ) {
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
        $fromCurrency = (string) ($this->order->currency ?? config('currency.base', 'USD'));

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

        $bodyHtml = view('emails.orders.pending-payment-body', [
            'name' => $name,
            'order' => $this->order,
            'currency' => $presenter->displayCurrency(),
            'paymentUrl' => $this->paymentUrl,
            'items' => $lines,
            'summary' => $summary,
        ])->render();

        return (new MailMessage)
            ->subject("Payment link for your order #{$this->order->number}")
            ->view('emails.base', [
                'title' => "Complete payment for order #{$this->order->number}",
                'preheader' => Str::limit("Click to complete payment for order #{$this->order->number}.", 120),
                'bodyHtml' => $bodyHtml,
                'actionUrl' => $this->paymentUrl,
                'actionLabel' => 'Pay now',
            ]);
    }
}
