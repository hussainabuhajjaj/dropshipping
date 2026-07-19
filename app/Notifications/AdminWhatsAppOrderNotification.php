<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Orders\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminWhatsAppOrderNotification extends Notification
{
    public function __construct(
        public Order $order,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;

        return (new MailMessage)
            ->subject("New WhatsApp Order #{$order->number}")
            ->greeting('New WhatsApp Order Received')
            ->line("A new WhatsApp-assisted order has been created:")
            ->line("Order #: {$order->number}")
            ->line("Customer: " . ($order->guest_name ?? $order->email ?? 'N/A'))
            ->line("Phone: " . ($order->guest_phone ?? 'N/A'))
            ->line("Email: {$order->email}")
            ->line("Total: " . number_format((float) $order->grand_total, 2) . " {$order->currency}")
            ->line("Shipping: {$order->shippingAddress?->line1}, {$order->shippingAddress?->city}")
            ->line("Payment Status: Unpaid (pending admin approval)")
            ->action('View Order', url("/admin/orders/{$order->id}"))
            ->line('Review and approve this order to send a payment link to the customer.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New WhatsApp Order',
            'body' => "Order #{$this->order->number} created via WhatsApp. Total: " . number_format((float) $this->order->grand_total, 2) . " {$this->order->currency}",
            'order_id' => $this->order->id,
            'order_number' => $this->order->number,
            'status' => $this->order->status,
            'payment_status' => $this->order->payment_status,
            'action_url' => url("/admin/orders/{$this->order->id}"),
            'action_label' => 'Review order',
        ];
    }
}
