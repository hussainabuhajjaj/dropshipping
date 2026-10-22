<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PhoneVerificationOtpNotification extends Notification
{
    public function __construct(
        private readonly string $code,
        private readonly ?string $phone = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $line = $this->phone
            ? "Use this code to verify the phone number {$this->phone}."
            : 'Use this code to verify your phone number.';

        return (new MailMessage())
            ->subject("Your phone verification code: {$this->code}")
            ->line($line)
            ->line("Your 4-digit verification code is: {$this->code}")
            ->line('This code expires in 10 minutes.');
    }
}
