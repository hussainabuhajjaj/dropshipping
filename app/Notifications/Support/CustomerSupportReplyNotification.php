<?php

declare(strict_types=1);

namespace App\Notifications\Support;

use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Models\SupportMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use YieldStudio\LaravelExpoNotifier\Dto\ExpoMessage;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class CustomerSupportReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SupportConversation $conversation,
        private readonly SupportMessage $message
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $email = method_exists($notifiable, 'getAttribute')
            ? $notifiable->getAttribute('email')
            : null;

        if (is_string($email) && trim($email) !== '') {
            $channels[] = 'mail';
        }

        if (method_exists($notifiable, 'expoTokens')) {
            $channels[] = ExpoNotificationsChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'support_reply',
            'conversation_id' => $this->conversation->id,
            'conversation_uuid' => $this->conversation->uuid,
            'message_id' => $this->message->id,
            'message' => $this->message->body,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url', config('app.url')), '/') . '/support';

        return (new MailMessage())
            ->subject('New support reply')
            ->line('You have a new reply from support.')
            ->line($this->message->body)
            ->action('View support', $url);
    }

    public function toExpoNotification(object $notifiable): ExpoMessage
    {
        $tokens = method_exists($notifiable, 'expoTokens')
            ? $notifiable->expoTokens()->pluck('value')->all()
            : [];

        return (new ExpoMessage())
            ->to($tokens)
            ->title('Support replied')
            ->body($this->message->body)
            ->channelId('default')
            ->sound('default')
            ->jsonData([
                'type' => 'support_reply',
                'conversation_uuid' => $this->conversation->uuid,
                'message_id' => $this->message->id,
            ]);
    }
}

