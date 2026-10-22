<?php

declare(strict_types=1);

namespace App\Notifications\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewsletterPromotionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $subject,
        public string $body,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject($this->subject)
            ->line($this->body);

        if ($this->actionUrl) {
            $mail->action($this->actionLabel ?: 'View offers', $this->actionUrl);
        }

        return $mail;
    }
}
