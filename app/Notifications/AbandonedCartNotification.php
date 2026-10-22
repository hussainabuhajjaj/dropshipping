<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AbandonedCart;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use YieldStudio\LaravelExpoNotifier\Dto\ExpoMessage;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class AbandonedCartNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AbandonedCart $cart,
        public readonly int $reminderNumber = 1,
        public readonly string $couponCode = 'SAVE10',
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
        $messages = $this->getMessages($locale);

        $msg = $messages[$this->reminderNumber] ?? $messages[1];

        return [
            'title' => $msg['title'],
            'body' => $msg['body'],
            'action_url' => url('/cart'),
            'action_label' => $locale === 'fr' ? 'Reprendre le paiement' : 'Resume checkout',
            'type' => 'abandoned_cart',
            'reminder_number' => $this->reminderNumber,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->getLocale($notifiable);
        $cart = $this->cart->cart_data ?? [];
        $lines = collect($cart)->map(function (array $line) {
            $name = $line['name'] ?? 'Item';
            $qty = (int) ($line['quantity'] ?? 1);
            $price = (float) ($line['price'] ?? 0);
            $subtotal = $qty * $price;
            return "{$name} x{$qty} — " . number_format($subtotal, 2) . ' FCFA';
        })->implode("\n");

        $messages = $this->getMessages($locale);

        $msg = $messages[$this->reminderNumber] ?? $messages[1];
        $subject = $msg['title'];
        $intro = $msg['body'];

        $mail = (new MailMessage())
            ->subject($subject)
            ->greeting($locale === 'fr' ? 'Finalisez votre commande' : 'Complete your purchase')
            ->line($intro)
            ->line($lines !== '' ? $lines : ($locale === 'fr' ? 'Articles sauvegardés pour vous.' : 'Items saved for you.'))
            ->action(
                $locale === 'fr' ? 'Reprendre le paiement' : 'Resume Checkout',
                url('/cart')
            )
            ->line(
                $locale === 'fr'
                    ? 'Si vous avez déjà finalisé votre commande, ignorez cet email.'
                    : 'If you already completed your order, you can ignore this email.'
            );

        if ($this->reminderNumber > 1) {
            $mail->line('—');
            $mail->line(
                $locale === 'fr'
                    ? "Utilisez le code <strong>{$this->couponCode}</strong> au paiement pour bénéficier de 10% de réduction."
                    : "Use code <strong>{$this->couponCode}</strong> at checkout for 10% off your order."
            );
        }

        return $mail;
    }

    public function toExpoNotification(object $notifiable): ExpoMessage
    {
        $tokens = method_exists($notifiable, 'expoTokens')
            ? $notifiable->expoTokens()->pluck('value')->all()
            : [];

        $locale = $this->getLocale($notifiable);
        $messages = $this->getMessages($locale);
        $msg = $messages[$this->reminderNumber] ?? $messages[1];

        return (new ExpoMessage())
            ->to($tokens)
            ->title($msg['title'])
            ->body($msg['body'])
            ->channelId('marketing')
            ->jsonData([
                'type' => 'abandoned_cart',
                'reminder_number' => $this->reminderNumber,
                'screen' => 'Cart',
            ]);
    }

    public function toWhatsApp(object $notifiable): string
    {
        $locale = $this->getLocale($notifiable);
        $url = url('/cart');

        $messages = [
            1 => $locale === 'fr'
                ? "Vous avez des articles dans votre panier ! Finalisez votre commande avant qu'ils ne soient épuisés.\n\n👉 $url"
                : "You left items in your cart! Complete your purchase before they sell out.\n\n👉 $url",
            2 => $locale === 'fr'
                ? "Toujours intéressé ? Votre panier vous attend. Utilisez le code {$this->couponCode} pour 10% de réduction.\n\n👉 $url"
                : "Still thinking about it? Your cart is still here. Use code {$this->couponCode} for 10% off.\n\n👉 $url",
            3 => $locale === 'fr'
                ? "Dernière chance ! Votre panier va expirer. Ne manquez pas cette occasion — commandez maintenant avec {$this->couponCode} pour 10% de réduction.\n\n👉 $url"
                : "Last chance! Your cart is expiring. Don't miss out — order now with {$this->couponCode} for 10% off.\n\n👉 $url",
        ];

        return $messages[$this->reminderNumber] ?? $messages[1];
    }

    private function getMessages(string $locale): array
    {
        return [
            1 => [
                'title' => $locale === 'fr'
                    ? 'Vous avez des articles dans votre panier'
                    : 'You left items in your cart',
                'body' => $locale === 'fr'
                    ? 'Finalisez votre achat avant que les articles ne soient épuisés.'
                    : 'Complete your purchase before items sell out.',
            ],
            2 => [
                'title' => $locale === 'fr'
                    ? 'Toujours intéressé ?'
                    : 'Still thinking about it?',
                'body' => $locale === 'fr'
                    ? "Votre panier vous attend. Utilisez le code {$this->couponCode} pour 10% de réduction."
                    : "Your cart is still here. Use code {$this->couponCode} for 10% off.",
            ],
            3 => [
                'title' => $locale === 'fr'
                    ? 'Dernière chance ! Votre panier va expirer'
                    : 'Last chance! Your cart is expiring',
                'body' => $locale === 'fr'
                    ? "Ne manquez pas cette occasion — commandez maintenant avec {$this->couponCode} pour 10% de réduction."
                    : "Don't miss out — complete your order now with {$this->couponCode} for 10% off.",
            ],
        ];
    }

    private function getLocale(object $notifiable): string
    {
        if (method_exists($notifiable, 'preferredLocale')) {
            return $notifiable->preferredLocale();
        }

        return app()->getLocale();
    }
}
