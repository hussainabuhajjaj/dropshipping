<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<int, array{id:int,sku:?string,title:?string,stock:int,threshold:int,product:int}> $variants */
    public function __construct(private array $variants)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject('Low stock alert')
            ->line('The following variants are below threshold:');

        foreach ($this->variants as $variant) {
            $message->line(sprintf(
                '• %s (SKU: %s) — stock %d / threshold %d',
                $variant['title'] ?: 'Variant #' . $variant['id'],
                $variant['sku'] ?: 'N/A',
                $variant['stock'],
                $variant['threshold']
            ));
        }

        $message->line('Consider replenishing or disabling these variants.');

        return $message;
    }
}
