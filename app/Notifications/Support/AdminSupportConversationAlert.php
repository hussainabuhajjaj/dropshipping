<?php

declare(strict_types=1);

namespace App\Notifications\Support;

use App\Domain\Support\Models\SupportConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminSupportConversationAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SupportConversation $conversation,
        private readonly string $reason
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

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'support_conversation_alert',
            'conversation_id' => $this->conversation->id,
            'conversation_uuid' => $this->conversation->uuid,
            'customer_id' => $this->conversation->customer_id,
            'status' => $this->conversation->status,
            'reason' => $this->reason,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.url'), '/') . '/admin/support-conversations/' . $this->conversation->id . '/edit';

        return (new MailMessage())
            ->subject('Support conversation requires attention')
            ->line('Conversation #' . $this->conversation->id . ' needs an agent response.')
            ->line($this->reason)
            ->action('Open conversation', $url);
    }
}

