<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Order $order,
        protected string $previousStatus,
        protected string $newStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = $this->order->getCustomerStatusLabel();
        $statusExplanation = $this->order->getCustomerStatusExplanation();

        $message = new MailMessage();
        $message
            ->subject("Order {$this->order->number}: $statusLabel")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your order **#{$this->order->number}** has been updated.")
            ->line('')
            ->line("**Status:** $statusLabel")
            ->line("**Update:** $statusExplanation")
            ->line('');

        // Include tracking info if available
        if ($this->newStatus === 'in_transit' || $this->newStatus === 'out_for_delivery') {
            $shipment = $this->order->shipments()->first();
            if ($shipment && $shipment->tracking_number) {
                $message->line("**Tracking Number:** {$shipment->tracking_number}");
                if ($shipment->tracking_url) {
                    $message->action('Track Your Package', $shipment->tracking_url);
                }
            }
        }

        // Show refund info if refunded
        if ($this->newStatus === 'refunded') {
            $message->line('');
            $message->line("**Refund Amount:** \$" . number_format($this->order->refund_amount / 100, 2));
            if ($this->order->refund_reason) {
                $message->line("**Reason:** {$this->order->refund_reason->label()}");
            }
            $message->line('Your refund will appear in your account within 3-5 business days.');
        }

        // Issue detection alert
        if ($this->newStatus === 'issue_detected') {
            $message->line('');
            $message->line('We detected an issue with your order. Our team will contact you shortly with an update.');
        }

        $message
            ->line('')
            ->action('View Order', $this->trackingLink())
            ->line('Thank you for your purchase!')
            ->salutation('Best regards');

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->number,
            'status' => $this->newStatus,
            'status_label' => $this->order->getCustomerStatusLabel(),
            'order_url' => $this->trackingLink(),
        ];
    }

    private function trackingLink(): string
    {
        return url("/orders/track?number={$this->order->number}&email={$this->order->email}");
    }
}
