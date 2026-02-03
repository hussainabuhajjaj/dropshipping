<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use YieldStudio\LaravelExpoNotifier\Dto\ExpoMessage;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class ExpoPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $title,
        protected string $body,
        protected array $data = [],
        protected ?string $channelId = 'default',
        protected ?string $sound = 'default',
        protected ?int $badge = null,
        protected bool $shouldBatch = false
    ) {}

    public function via(object $notifiable): array
    {
        return [ExpoNotificationsChannel::class];
    }

    public function toExpoNotification(object $notifiable): ExpoMessage
    {
        $tokens = method_exists($notifiable, 'expoTokens')
            ? $notifiable->expoTokens()->pluck('value')->all()
            : [];

        $message = (new ExpoMessage())
            ->to($tokens)
            ->title($this->title)
            ->body($this->body)
            ->channelId($this->channelId)
            ->sound($this->sound);

        if ($this->badge !== null) {
            $message->badge($this->badge);
        }

        if (! empty($this->data)) {
            $message->jsonData($this->data);
        }

        if ($this->shouldBatch) {
            $message->shouldBatch();
        }

        return $message;
    }
}

