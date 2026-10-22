<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AdminResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = url(route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage())
            ->subject('Reset your admin password')
            ->line('You are receiving this email because we received a password reset request for your admin account.')
            ->action('Reset password', $resetUrl)
            ->line('If you did not request a password reset, no further action is required.');
    }
}
