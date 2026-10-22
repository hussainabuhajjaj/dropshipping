<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Models\ReturnRequest;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReturnRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ReturnRequest $returnRequest,
        public string $rejectionReason,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail', WhatsAppChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'return_request_id' => $this->returnRequest->id,
            'order_number' => $this->returnRequest->order->number,
            'status' => $this->returnRequest->status,
            'rejection_reason' => $this->rejectionReason,
            'order_url' => url("/orders/track?number={$this->returnRequest->order->number}&email={$this->returnRequest->order->email}"),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? ($this->returnRequest->customer->name ?? 'Customer');
        $orderNumber = $this->returnRequest->order->number;

        return (new MailMessage)
            ->subject("Update on your return request for order #{$orderNumber}")
            ->greeting("Hi {$name},")
            ->line("We've reviewed your return request for order #{$orderNumber}.")
            ->line("Unfortunately, we're unable to approve this return request at this time.")
            ->line("Reason: {$this->rejectionReason}")
            ->line('If you believe this is an error or would like to discuss this further, please contact our support team.')
            ->action('Contact Support', url('/contact'))
            ->line('We appreciate your understanding.');
    }

    public function toWhatsApp(object $notifiable): string
    {
        $orderNumber = $this->returnRequest->order->number;
        return "Your return request for order #{$orderNumber} could not be approved. Reason: {$this->rejectionReason}. Contact us if you have questions.";
    }
}
