<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Models\Order;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InTransitNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ?string $trackingNumber = null,
        public ?string $currentLocation = null,
        public ?string $estimatedDelivery = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail', WhatsAppChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_number' => $this->order->number,
            'status' => 'in_transit',
            'tracking_number' => $this->trackingNumber,
            'current_location' => $this->currentLocation,
            'estimated_delivery' => $this->estimatedDelivery,
            'tracking_url' => $this->trackingLink(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? ($this->order->guest_name ?? $this->order->email ?? 'Customer');
        
        return (new MailMessage)
            ->subject("Your order #{$this->order->number} is on the way")
            ->greeting("Hi {$name},")
            ->line("Your order #{$this->order->number} is now in transit!")
            ->when($this->currentLocation, function ($mail) {
                return $mail->line("**Current location:** {$this->currentLocation}");
            })
            ->when($this->estimatedDelivery, function ($mail) {
                return $mail->line("**Expected delivery:** {$this->estimatedDelivery}");
            })
            ->action('Track shipment', $this->trackingLink())
            ->line('You can track your package in real-time using the link above.');
    }

    public function toWhatsApp(object $notifiable): string
    {
        $location = $this->currentLocation ? " Currently at: {$this->currentLocation}." : '';
        $eta = $this->estimatedDelivery ? " ETA: {$this->estimatedDelivery}." : '';
        
        return "Good news! Order #{$this->order->number} is on the way.{$location}{$eta} Track: {$this->trackingLink()}";
    }

    private function trackingLink(): string
    {
        return url("/orders/track?number={$this->order->number}&email={$this->order->email}");
    }
}
