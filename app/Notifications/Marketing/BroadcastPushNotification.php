<?php

declare(strict_types=1);

namespace App\Notifications\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use YieldStudio\LaravelExpoNotifier\Dto\ExpoMessage;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class BroadcastPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly ?string $body,
        private readonly string $type,
        private readonly array $payload = [],
        private readonly string $channelId = 'default'
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (method_exists($notifiable, 'expoTokens')) {
            $channels[] = ExpoNotificationsChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return array_merge([
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
        ], $this->payload);
    }

    public function toExpoNotification(object $notifiable): ExpoMessage
    {
        $tokens = method_exists($notifiable, 'expoTokens')
            ? $notifiable->expoTokens()->pluck('value')->all()
            : [];

        return (new ExpoMessage())
            ->to($tokens)
            ->title($this->title)
            ->body($this->body)
            ->channelId($this->channelId)
            ->jsonData(array_merge($this->payload, ['type' => $this->type]));
    }
}
