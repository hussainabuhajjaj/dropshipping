<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Domain\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class OrderPendingPaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

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

        $formatMoney = static fn ($value) => number_format((float) $value, 2);
        $currency = $this->order->currency ?? 'USD';
        $paymentUrl = route('orders.confirmation', ['number' => $this->order->number]);

        $lines = $items->map(function ($item) use ($formatMoney, $currency) {
            $snapshot = $item->snapshot ?? [];
            $meta = $item->meta ?? [];
            $name = $snapshot['name'] ?? 'Item';
            $variant = $snapshot['variant'] ?? null;
            $qty = (int) ($item->quantity ?? 1);
            $unit = (float) ($item->unit_price ?? 0);
            $total = (float) ($item->total ?? 0);

            $media = is_array($meta['media'] ?? null) ? $meta['media'] : [];
            $image = $media[0] ?? null;
            if (! $image) {
                $image = $item->productVariant?->product?->images?->sortBy('position')->first()?->url;
            }

            return [
                'name' => $name,
                'variant' => $variant,
                'qty' => $qty,
                'unit' => $formatMoney($unit),
                'total' => $formatMoney($total),
                'image' => $image,
            ];
        })->values()->all();

        $summary = [
            'subtotal' => $formatMoney($this->order->subtotal),
            'shipping' => $formatMoney($this->order->shipping_total),
            'tax' => $formatMoney($this->order->tax_total),
            'discount' => $formatMoney($this->order->discount_total),
            'grand_total' => $formatMoney($this->order->grand_total),
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
