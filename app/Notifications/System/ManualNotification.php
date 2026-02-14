<?php

declare(strict_types=1);

namespace App\Notifications\System;

use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use YieldStudio\LaravelExpoNotifier\Dto\ExpoMessage;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class ManualNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<int, string|class-string> $channels
     */
    public function __construct(
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public array $channels = ['database', 'broadcast'],
    ) {
    }

    public function via(object $notifiable): array
    {
        $resolved = [];

        foreach ($this->channels as $channel) {
            if ($channel === 'mail' && ! $this->canSendMail($notifiable)) {
                continue;
            }

            if ($channel === 'push' || $channel === ExpoNotificationsChannel::class) {
                if ($this->canSendExpoPush($notifiable)) {
                    $resolved[] = ExpoNotificationsChannel::class;
                    continue;
                }

                if ($this->canSendBroadcast($notifiable)) {
                    $resolved[] = 'broadcast';
                }

                continue;
            }

            $resolved[] = $channel;
        }

        return array_values(array_unique($resolved, SORT_REGULAR));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject($this->title)
            ->line($this->body);

        if ($this->actionUrl) {
            $mail->action($this->actionLabel ?: 'View update', $this->actionUrl);
        }

        return $mail;
    }

    public function toWhatsApp(object $notifiable): string
    {
        $action = $this->actionUrl ? " {$this->actionUrl}" : '';

        return trim("{$this->title} - {$this->body}{$action}");
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
            ->channelId('default')
            ->sound('default')
            ->jsonData([
                'type' => 'manual_notification',
                'title' => $this->title,
                'body' => $this->body,
                'action_url' => $this->actionUrl,
                'action_label' => $this->actionLabel,
            ]);
    }

    private function canSendMail(object $notifiable): bool
    {
        $email = method_exists($notifiable, 'getAttribute')
            ? $notifiable->getAttribute('email')
            : null;

        return is_string($email) && trim($email) !== '';
    }

    private function canSendExpoPush(object $notifiable): bool
    {
        return method_exists($notifiable, 'expoTokens') && $this->notifiableAllowsPush($notifiable);
    }

    private function canSendBroadcast(object $notifiable): bool
    {
        $role = method_exists($notifiable, 'getAttribute')
            ? (string) $notifiable->getAttribute('role')
            : '';

        return in_array($role, ['admin', 'staff'], true);
    }

    private function notifiableAllowsPush(object $notifiable): bool
    {
        $metadata = method_exists($notifiable, 'getAttribute')
            ? $notifiable->getAttribute('metadata')
            : null;
        $metadata = is_array($metadata) ? $metadata : [];
        $preferences = is_array($metadata['preferences'] ?? null) ? $metadata['preferences'] : [];
        $notifications = is_array($preferences['notifications'] ?? null) ? $preferences['notifications'] : [];

        if (array_key_exists('push', $notifications) && $notifications['push'] === false) {
            return false;
        }

        return true;
    }
}
