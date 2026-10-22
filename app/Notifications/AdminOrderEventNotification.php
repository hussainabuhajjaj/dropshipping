<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdminOrderEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $event,
        public ?string $detail = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $title = $this->event;
        $body = $this->detail;
        $orderUrl = url("/admin/orders/{$this->order->id}");

        return [
            'title' => $title,
            'body' => $body,
            'event' => $this->event,
            'order_id' => $this->order->id,
            'order_number' => $this->order->number,
            'status' => $this->order->status,
            'payment_status' => $this->order->payment_status,
            'detail' => $this->detail,
            'action_url' => $orderUrl,
            'action_label' => 'View order',
            'admin_url' => $orderUrl,
        ];
    }
}
