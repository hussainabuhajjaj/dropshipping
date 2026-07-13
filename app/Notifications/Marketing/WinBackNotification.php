<?php

declare(strict_types=1);

namespace App\Notifications\Marketing;

use App\Models\Customer;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use YieldStudio\LaravelExpoNotifier\Dto\ExpoMessage;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class WinBackNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Customer $customer,
        public readonly string $couponCode = 'MISSYOU10',
        public readonly ?int $inactiveDays = null,
        public readonly bool $enablePush = true,
        public readonly bool $enableWhatsApp = true,
        public readonly bool $enableEmail = true,
    ) {
    }

    public function via(object $notifiable): array
    {
        if (! ($notifiable->marketing_opt_in ?? true)) {
            return [];
        }

        $channels = [];

        if ($this->enableEmail) {
            $channels[] = 'mail';
        }

        if ($this->enablePush && method_exists($notifiable, 'expoTokens')) {
            $tokens = $notifiable->expoTokens()->pluck('value')->all();
            if (! empty($tokens)) {
                $channels[] = ExpoNotificationsChannel::class;
            }
        }

        if ($this->enableWhatsApp && isset($notifiable->phone) && $notifiable->phone) {
            $channels[] = WhatsAppChannel::class;
        }

        $channels[] = 'database';

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        $locale = $this->getLocale($notifiable);

        return [
            'type' => 'win_back',
            'coupon_code' => $this->couponCode,
            'title' => $locale === 'fr'
                ? 'Vous nous manquez !'
                : 'We miss you!',
            'body' => $locale === 'fr'
                ? "Utilisez le code {$this->couponCode} pour 10% de réduction sur votre prochaine commande."
                : "Use code {$this->couponCode} for 10% off your next order.",
            'action_url' => url('/'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->getLocale($notifiable);
        $name = $notifiable->first_name ?? false
            ? $notifiable->first_name
            : ($locale === 'fr' ? 'Cher client' : 'Valued customer');

        $days = $this->inactiveDays
            ? ($locale === 'fr' ? "{$this->inactiveDays} jours" : "{$this->inactiveDays} days")
            : '';

        return (new MailMessage())
            ->subject(
                $locale === 'fr'
                    ? 'Vous nous manquez — profitez de 10% de réduction'
                    : 'We miss you — enjoy 10% off'
            )
            ->greeting($locale === 'fr' ? "Bonjour {$name}," : "Hi {$name},")
            ->line(
                $days
                    ? ($locale === 'fr'
                        ? "Cela fait {$days} que nous ne vous avons pas vu. Nous aimerions vous revoir !"
                        : "It's been {$days} since we last saw you. We'd love to see you again!")
                    : ($locale === 'fr'
                        ? "Cela faisait longtemps ! Nous avons quelque chose de spécial pour vous."
                        : "It's been a while! We have something special for you.")
            )
            ->line(
                $locale === 'fr'
                    ? "Utilisez le code <strong>{$this->couponCode}</strong> pour 10% de réduction sur votre prochaine commande."
                    : "Use code <strong>{$this->couponCode}</strong> for 10% off your next order."
            )
            ->action(
                $locale === 'fr' ? 'Commencer mes achats' : 'Start shopping',
                url('/')
            )
            ->line(
                $locale === 'fr'
                    ? 'Cette offre est valable pour une durée limitée. Ne la manquez pas !'
                    : 'This offer is valid for a limited time. Don\'t miss out!'
            );
    }

    public function toExpoNotification(object $notifiable): ExpoMessage
    {
        $tokens = method_exists($notifiable, 'expoTokens')
            ? $notifiable->expoTokens()->pluck('value')->all()
            : [];

        $locale = $this->getLocale($notifiable);

        return (new ExpoMessage())
            ->to($tokens)
            ->title($locale === 'fr' ? 'Vous nous manquez ! 💝' : 'We miss you! 💝')
            ->body(
                $locale === 'fr'
                    ? "Utilisez le code {$this->couponCode} pour 10% de réduction."
                    : "Use code {$this->couponCode} for 10% off your next order."
            )
            ->channelId('marketing')
            ->jsonData([
                'type' => 'win_back',
                'screen' => 'Home',
            ]);
    }

    public function toWhatsApp(object $notifiable): string
    {
        $locale = $this->getLocale($notifiable);
        $url = url('/');

        if ($locale === 'fr') {
            $days = $this->inactiveDays ? " Cela fait {$this->inactiveDays} jours." : '';
            return "Vous nous manquez !{$days} Profitez de 10% de réduction avec le code {$this->couponCode}.\n\n👉 {$url}";
        }

        $days = $this->inactiveDays ? " It's been {$this->inactiveDays} days." : '';
        return "We miss you!{$days} Enjoy 10% off your next order with code {$this->couponCode}.\n\n👉 {$url}";
    }

    private function getLocale(object $notifiable): string
    {
        if (method_exists($notifiable, 'preferredLocale')) {
            return $notifiable->preferredLocale();
        }

        return app()->getLocale();
    }
}
