<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Models\ReturnRequest;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReturnApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ReturnRequest $returnRequest,
        public ?string $returnLabelUrl = null,
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
            'return_label_url' => $this->returnLabelUrl,
            'order_url' => url("/orders/track?number={$this->returnRequest->order->number}&email={$this->returnRequest->order->email}"),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? ($this->returnRequest->customer->name ?? 'Customer');
        $orderNumber = $this->returnRequest->order->number;

        $message = (new MailMessage)
            ->subject("Your return request for order #{$orderNumber} has been approved")
            ->greeting("Hi {$name},")
            ->line("Good news! Your return request for order #{$orderNumber} has been approved.")
            ->line("Reason: {$this->returnRequest->reason}");

        if ($this->returnLabelUrl) {
            $message->line('We\'ve created a prepaid return shipping label for you.')
                ->action('Download Return Label', $this->returnLabelUrl)
                ->line('Please print the label, attach it to your package, and drop it off at the nearest shipping location.');
        } else {
            $message->line('Please ship the item back to us at your earliest convenience.')
                ->line('Once we receive the item, we\'ll process your refund within 3-5 business days.');
        }

        $message->line('If you have any questions about the return process, please don\'t hesitate to contact us.')
            ->action('View Order', url("/orders/track?number={$orderNumber}&email={$this->returnRequest->order->email}"));

        return $message;
    }

    public function toWhatsApp(object $notifiable): string
    {
        $orderNumber = $this->returnRequest->order->number;
        $label = $this->returnLabelUrl ? " Download label: {$this->returnLabelUrl}" : '';
        return "Your return for order #{$orderNumber} was approved!{$label} Ship the item back and we'll refund you within 3-5 days.";
    }
}
