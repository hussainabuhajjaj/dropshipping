<?php

declare(strict_types=1);

namespace App\Notifications\Customers;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Customer $customer)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'name' => $this->customer->name,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->customer->first_name ?? $this->customer->name ?? 'there';

        return (new MailMessage)
            ->subject('Welcome to ' . config('app.name', 'Our Store') . '!')
            ->greeting("Hi {$name},")
            ->line('Welcome to ' . config('app.name', 'Our Store') . '! We\'re excited to have you.')
            ->line('Your account has been created successfully. You can now:')
            ->line('• Browse thousands of products')
            ->line('• Track your orders in real-time')
            ->line('• Save items to your wishlist')
            ->line('• Manage your addresses and preferences')
            ->action('Start Shopping', url('/products'))
            ->line('If you have any questions, our support team is here to help.')
            ->line('Thank you for choosing us!');
    }
}
