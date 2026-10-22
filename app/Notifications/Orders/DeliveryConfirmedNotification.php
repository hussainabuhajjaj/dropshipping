<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Models\Order;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use YieldStudio\LaravelExpoNotifier\Dto\ExpoMessage;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class DeliveryConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ?string $deliveredAt = null,
        public ?string $trackingUrl = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast', 'mail', WhatsAppChannel::class];

        if (method_exists($notifiable, 'expoTokens') && $notifiable->expoTokens()->exists()) {
            $channels[] = ExpoNotificationsChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        $locale = $this->getLocale($notifiable);

        return [
            'order_number' => $this->order->number,
            'status' => $this->order->status,
            'delivered_at' => $this->deliveredAt,
            'tracking_url' => $this->trackingLink(),
            'title' => $locale === 'fr'
                ? 'Commande livrée'
                : 'Order delivered',
            'body' => $locale === 'fr'
                ? "Votre commande #{$this->order->number} a été livrée."
                : "Your order #{$this->order->number} was delivered.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->getLocale($notifiable);
        $name = $this->getNotifiableName($notifiable);

        $mail = (new MailMessage)
            ->subject(
                $locale === 'fr'
                    ? "Livré : commande #{$this->order->number}"
                    : "Delivered: order #{$this->order->number}"
            )
            ->greeting($locale === 'fr' ? "Bonjour {$name}," : "Hi {$name},");

        if ($locale === 'fr') {
            $date = $this->deliveredAt ? " le {$this->deliveredAt}" : '';
            $mail->line("Votre commande #{$this->order->number} a été livrée{$date}.");
        } else {
            $date = $this->deliveredAt ? " on {$this->deliveredAt}" : '';
            $mail->line("Your order #{$this->order->number} was delivered{$date}.");
        }

        $mail->action(
            $locale === 'fr' ? 'Voir la commande' : 'View order',
            $this->trackingLink()
        );

        $mail->line(
            $locale === 'fr'
                ? 'Si quelque chose ne va pas, répondez à cet email et nous vous aiderons immédiatement.'
                : 'If anything is off, reply and we will help immediately.'
        );

        return $mail;
    }

    public function toWhatsApp(object $notifiable): string
    {
        $locale = $this->getLocale($notifiable);
        $date = $this->deliveredAt ? " on {$this->deliveredAt}" : '';

        if ($locale === 'fr') {
            return "Votre commande #{$this->order->number} a été livrée{$date}. Si quelque chose ne va pas, répondez et nous vous aiderons. Suivi : {$this->trackingLink()}";
        }

        return "Order #{$this->order->number} shows delivered{$date}. If anything is off, reply and we'll fix it. Track: {$this->trackingLink()}";
    }

    public function toExpoNotification(object $notifiable): ExpoMessage
    {
        $tokens = method_exists($notifiable, 'expoTokens')
            ? $notifiable->expoTokens()->pluck('value')->all()
            : [];

        $locale = $this->getLocale($notifiable);

        return (new ExpoMessage())
            ->to($tokens)
            ->title($locale === 'fr' ? 'Commande livrée 📦' : 'Order delivered 📦')
            ->body(
                $locale === 'fr'
                    ? "Votre commande #{$this->order->number} est arrivée !"
                    : "Your order #{$this->order->number} has arrived!"
            )
            ->channelId('orders')
            ->jsonData([
                'type' => 'delivery_confirmed',
                'order_number' => $this->order->number,
                'screen' => 'Orders',
            ]);
    }

    private function trackingLink(): string
    {
        return $this->trackingUrl ?? url("/orders/track?number={$this->order->number}&email={$this->order->email}");
    }

    private function getNotifiableName(object $notifiable): string
    {
        if (method_exists($notifiable, 'name')) {
            return $notifiable->name;
        }

        return $this->order->guest_name ?? $this->order->email ?? 'Customer';
    }

    private function getLocale(object $notifiable): string
    {
        if ($this->locale) {
            return $this->locale;
        }

        if (method_exists($notifiable, 'preferredLocale')) {
            return $notifiable->preferredLocale();
        }

        return app()->getLocale();
    }
}
