<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Orders\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminFulfillmentIssue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly OrderItem $orderItem, private readonly string $message)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $orderUrl = url("/admin/orders/{$this->orderItem->order_id}");

        return [
            'title' => 'Fulfillment issue',
            'body' => $this->message,
            'order_number' => $this->orderItem->order?->number,
            'order_item_id' => $this->orderItem->id,
            'status' => $this->orderItem->fulfillment_status,
            'provider' => $this->orderItem->fulfillmentProvider?->code ?? 'unknown',
            'message' => $this->message,
            'action_url' => $orderUrl,
            'action_label' => 'View order',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $orderNumber = $this->orderItem->order?->number ?? '#';
        return (new MailMessage())
            ->subject("Fulfillment issue on order {$orderNumber}")
            ->line("Order {$orderNumber} has a fulfillment issue.")
            ->line("Status: {$this->orderItem->fulfillment_status}")
            ->line("Provider: " . ($this->orderItem->fulfillmentProvider?->code ?? 'unknown'))
            ->line($this->message)
            ->action('View Order', url("/admin/orders/{$this->orderItem->order_id}"));
    }
}
