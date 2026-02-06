<?php

declare(strict_types=1);

namespace App\Notifications\Marketing;

use App\Models\MobileAnnouncement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use YieldStudio\LaravelExpoNotifier\Dto\ExpoMessage;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class MobileAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MobileAnnouncement $announcement)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = [];

        if ($this->announcement->send_database) {
            $channels[] = 'database';
        }

        if ($this->announcement->send_email && $this->notifiableAllowsEmail($notifiable)) {
            $channels[] = 'mail';
        }

        if ($this->announcement->send_push && $this->notifiableAllowsPush($notifiable)) {
            $channels[] = ExpoNotificationsChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => (string) $this->announcement->title,
            'body' => (string) $this->announcement->body,
            'action_url' => $this->announcement->action_href,
            'announcement_id' => (int) $this->announcement->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject((string) $this->announcement->title)
            ->line((string) $this->announcement->body);

        $actionUrl = $this->emailActionUrl();
        if ($actionUrl) {
            $mail->action('View update', $actionUrl);
        }

        return $mail;
    }

    public function toExpoNotification(object $notifiable): ExpoMessage
    {
        $tokens = method_exists($notifiable, 'expoTokens')
            ? $notifiable->expoTokens()->pluck('value')->all()
            : [];

        return (new ExpoMessage())
            ->to($tokens)
            ->title((string) $this->announcement->title)
            ->body((string) $this->announcement->body)
            ->channelId('default')
            ->sound('default')
            ->jsonData([
                'type' => 'announcement',
                'announcement_id' => (int) $this->announcement->id,
                'action_url' => $this->announcement->action_href,
            ]);
    }

    private function notifiableAllowsEmail(object $notifiable): bool
    {
        $email = method_exists($notifiable, 'getAttribute')
            ? $notifiable->getAttribute('email')
            : null;
        if (! is_string($email) || trim($email) === '') {
            return false;
        }

        $prefs = $this->notifiableNotificationPrefs($notifiable);

        if (array_key_exists('email', $prefs) && $prefs['email'] === false) {
            return false;
        }

        return true;
    }

    private function notifiableAllowsPush(object $notifiable): bool
    {
        $prefs = $this->notifiableNotificationPrefs($notifiable);

        if (array_key_exists('push', $prefs) && $prefs['push'] === false) {
            return false;
        }

        return true;
    }

    private function notifiableNotificationPrefs(object $notifiable): array
    {
        $metadata = method_exists($notifiable, 'getAttribute')
            ? $notifiable->getAttribute('metadata')
            : null;
        $metadata = is_array($metadata) ? $metadata : [];
        $preferences = is_array($metadata['preferences'] ?? null) ? $metadata['preferences'] : [];
        $notifications = is_array($preferences['notifications'] ?? null) ? $preferences['notifications'] : [];

        return $notifications;
    }

    private function emailActionUrl(): ?string
    {
        $href = $this->announcement->action_href;
        if (! $href) {
            return null;
        }

        return str_starts_with($href, 'http://') || str_starts_with($href, 'https://')
            ? $href
            : null;
    }
}
