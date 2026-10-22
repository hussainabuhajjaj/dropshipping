<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerShipmentOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Shipment $shipment, $fulfillment_status)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $shipment = $this->shipment;
        $order = $shipment->order;
        return [
            'order_number' => $order?->number,
            'tracking_number' => $shipment?->tracking_number,
            'status' => $this->fulfillment_status,
            'tracking_url' => $shipment?->tracking_url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shipment = $this->shipment;
        $order = $shipment->order;
        $orderNumber = $order?->number ?? '#';

        $mail = (new MailMessage())
            ->subject("Your order {$orderNumber} has shipped")
            ->line("Good news! Your order {$orderNumber} is on the way.")
            ->lineIf($tracking = $shipment?->tracking_number, "Tracking: {$tracking}");

        if ($url = $shipment?->tracking_url) {
            $mail->action('Track shipment', $url);
        }

        return $mail;
    }
}
