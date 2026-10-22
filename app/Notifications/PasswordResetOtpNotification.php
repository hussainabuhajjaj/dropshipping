<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtpNotification extends Notification
{
    public function __construct(private readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Your password reset code: {$this->code}")
            ->line("Your 4-digit password reset code is: {$this->code}")
            ->line('This code expires in 10 minutes.')
            ->line('If you did not request this code, please ignore this email.');
    }
}
