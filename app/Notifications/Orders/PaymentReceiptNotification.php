<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
        $items = $this->order->items()->with('productVariant.product')->get();
        
        $itemsList = $items->map(function ($item) {
            $productName = $item->snapshot['name'] ?? $item->productVariant?->product?->name ?? 'Product';
            $variant = $item->snapshot['variant'] ?? '';
            $variantText = $variant ? " ({$variant})" : '';
            $qty = $item->quantity;
            $price = number_format((float) $item->unit_price, 2);
            $total = number_format((float) $item->total, 2);
            return "{$productName}{$variantText} × {$qty} — {$this->order->currency} {$total}";
        })->implode("\n");

        return (new MailMessage)
            ->subject("Payment Receipt — Order #{$this->order->number}")
            ->greeting("Hi {$name},")
            ->line("Thank you for your payment. Here's your receipt for order #{$this->order->number}.")
            ->line('')
            ->line('**Order Items:**')
            ->line($itemsList)
            ->line('')
            ->line("**Subtotal:** {$this->order->currency} " . number_format((float) $this->order->subtotal, 2))
            ->line("**Shipping:** {$this->order->currency} " . number_format((float) $this->order->shipping_total, 2))
            ->when($this->order->discount_total > 0, function ($mail) {
                return $mail->line("**Discount:** -{$this->order->currency} " . number_format((float) $this->order->discount_total, 2));
            })
            ->when($this->order->tax_total > 0, function ($mail) {
                return $mail->line("**Tax:** {$this->order->currency} " . number_format((float) $this->order->tax_total, 2));
            })
            ->line("**Total Paid:** {$this->order->currency} " . number_format((float) $this->order->grand_total, 2))
            ->line('')
            ->line("**Payment Method:** " . ucfirst(str_replace('_', ' ', $this->payment->method ?? 'card')))
            ->line("**Payment ID:** {$this->payment->gateway_transaction_id}")
            ->line("**Date:** " . $this->payment->paid_at?->format('M d, Y h:i A'))
            ->action('View Order', url("/orders/track?number={$this->order->number}&email={$this->order->email}"))
            ->line('Keep this email for your records.');
    }
}
