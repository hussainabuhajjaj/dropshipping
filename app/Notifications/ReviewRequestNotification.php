<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly OrderItem $orderItem)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toArray(object $notifiable): array
    {
        $product = $this->orderItem->productVariant?->product;
        $orderNumber = $this->orderItem->order?->number;
        $reviewUrl = route('products.show', ['product' => $product?->id]) . '#reviews';

        return [
            'order_number' => $orderNumber,
            'product_name' => $product?->name,
            'action_url' => $reviewUrl,
            'action_label' => 'Write a review',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->orderItem->productVariant?->product;
        $orderNumber = $this->orderItem->order?->number;
        $reviewUrl = route('products.show', ['product' => $product?->id]) . '#reviews';

        return (new MailMessage())
            ->subject('How was your recent purchase?')
            ->greeting('We\'d love to hear from you')
            ->line("Your order #{$orderNumber} has been delivered. How did you like the {$product?->name}?")
            ->action('Write a Review', $reviewUrl)
            ->line('Your feedback helps other customers make informed decisions.');
    }
}
