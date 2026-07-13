<?php

declare(strict_types=1);

namespace App\Notifications\Marketing;

use App\Models\Order;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use YieldStudio\LaravelExpoNotifier\Dto\ExpoMessage;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class CrossSellNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly Collection $recommendations,
        public readonly string $couponCode = 'WELCOME10',
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
        $names = $this->recommendations->pluck('name')->take(3)->values()->all();

        return [
            'type' => 'cross_sell',
            'order_number' => $this->order->number,
            'recommended_products' => $names,
            'coupon_code' => $this->couponCode,
            'title' => $locale === 'fr'
                ? 'Complétez votre commande'
                : 'Complete your order',
            'body' => $locale === 'fr'
                ? 'Découvrez ces articles qui pourraient vous intéresser.'
                : 'Check out these items you might like.',
            'action_url' => url('/products'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->getLocale($notifiable);
        $products = $this->recommendations->take(3);
        $lines = $products->map(fn ($p) => "• {$p->name}")->implode("\n");

        $mail = (new MailMessage())
            ->subject(
                $locale === 'fr'
                    ? "Vous aimerez peut-être aussi"
                    : "You might also like"
            )
            ->greeting(
                $locale === 'fr'
                    ? "Merci pour votre commande #{$this->order->number} !"
                    : "Thanks for your order #{$this->order->number}!"
            )
            ->line(
                $locale === 'fr'
                    ? 'En complément de votre achat, ces articles pourraient vous intéresser :'
                    : 'To go with your purchase, you might like these items:'
            )
            ->line($lines)
            ->action(
                $locale === 'fr' ? 'Voir les articles' : 'View items',
                url('/products')
            )
            ->line('—')
            ->line(
                $locale === 'fr'
                    ? "Utilisez le code <strong>{$this->couponCode}</strong> pour 10% de réduction sur votre prochaine commande."
                    : "Use code <strong>{$this->couponCode}</strong> for 10% off your next order."
            );

        return $mail;
    }

    public function toExpoNotification(object $notifiable): ExpoMessage
    {
        $tokens = method_exists($notifiable, 'expoTokens')
            ? $notifiable->expoTokens()->pluck('value')->all()
            : [];

        $locale = $this->getLocale($notifiable);
        $productName = $this->recommendations->first()?->name;

        $title = $locale === 'fr'
            ? 'Complétez votre commande'
            : 'Complete your order';

        $body = $locale === 'fr'
            ? ($productName ? "Découvrez {$productName} et d'autres articles." : 'Des articles qui pourraient vous plaire.')
            : ($productName ? "Check out {$productName} and more items you might like." : 'Items you might like.');

        return (new ExpoMessage())
            ->to($tokens)
            ->title($title)
            ->body($body)
            ->channelId('marketing')
            ->jsonData([
                'type' => 'cross_sell',
                'order_number' => $this->order->number,
                'screen' => 'Products',
            ]);
    }

    public function toWhatsApp(object $notifiable): string
    {
        $locale = $this->getLocale($notifiable);
        $names = $this->recommendations->pluck('name')->take(3)->implode(', ');
        $url = url('/products');

        if ($locale === 'fr') {
            return "Merci pour votre commande #{$this->order->number} !\n\nEn complément, ces articles pourraient vous plaire : {$names}\n\n👉 {$url}\n\nUtilisez le code {$this->couponCode} pour 10% de réduction.";
        }

        return "Thanks for order #{$this->order->number}! You might also like: {$names}\n\n👉 {$url}\n\nUse code {$this->couponCode} for 10% off your next order.";
    }

    private function getLocale(object $notifiable): string
    {
        if (method_exists($notifiable, 'preferredLocale')) {
            return $notifiable->preferredLocale();
        }

        return app()->getLocale();
    }
}
