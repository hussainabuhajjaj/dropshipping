<?php

declare(strict_types=1);

namespace App\Notifications\Marketing;

use App\Models\Coupon;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewsletterWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public NewsletterSubscriber $subscriber,
        public Coupon $coupon,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', 'Our Store');

        return (new MailMessage)
            ->subject("Welcome to {$appName}! Here's 10% off")
            ->greeting('Thanks for subscribing!')
            ->line("You're now on the list for exclusive deals, new arrivals, and style inspiration.")
            ->line('As a thank you, here\'s a discount on your first order:')
            ->line("**Coupon Code: {$this->coupon->code}**")
            ->line("Valid for **{$this->coupon->amount}% off** your entire order — expires {$this->coupon->ends_at->format('M j, Y')}.")
            ->action('Shop Now', url('/products'))
            ->line('Start browsing our latest collection and use your code at checkout.')
            ->line('Happy shopping!');
    }
}