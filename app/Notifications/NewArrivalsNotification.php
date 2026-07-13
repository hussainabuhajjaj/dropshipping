<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewArrivalsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private array $productNames,
        private int $productCount,
        private bool $sendPush = true,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail'];
        if ($this->sendPush) {
            $channels[] = \App\Notifications\Channels\ExpoChannel::class;
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $productLines = collect($this->productNames)
            ->map(fn ($n) => '  * ' . $n)
            ->implode("\n");

        return (new MailMessage)
            ->subject('New Trending Arrivals Just Landed!')
            ->greeting('Hello ' . ($notifiable->first_name ?? 'there') . '!')
            ->line('We have just added ' . $this->productCount . ' new trending products to our collection.')
            ->line('Here is what is fresh:')
            ->line($productLines)
            ->action('Browse New Arrivals', url('/collections/new-arrivals'))
            ->line('Shop now before they sell out!');
    }

    public function toExpo(object $notifiable): array
    {
        return [
            'title' => 'New Trending Arrivals',
            'body' => $this->productCount . ' new products just landed. Check them out!',
            'data' => ['url' => url('/collections/new-arrivals')],
        ];
    }
}
