<?php

declare(strict_types=1);

namespace App\Notifications\Orders;

use App\Domain\Orders\Models\Order;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use YieldStudio\LaravelExpoNotifier\Dto\ExpoMessage;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class OrderShippedNotification extends Notification
{
    public function __construct(
        public Order $order,
        public ?string $trackingNumber = null,
        public ?string $carrier = null,
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
            'tracking_number' => $this->trackingNumber,
            'carrier' => $this->carrier,
            'tracking_url' => $this->trackingLink(),
            'title' => $locale === 'fr'
                ? 'Commande expédiée'
                : 'Order shipped',
            'body' => $locale === 'fr'
                ? "Votre commande #{$this->order->number} est en route !"
                : "Your order #{$this->order->number} is on its way!",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->getLocale($notifiable);
        $name = $this->getNotifiableName($notifiable);

        $message = (new MailMessage)
            ->subject(
                $locale === 'fr'
                    ? "Votre commande #{$this->order->number} a été expédiée !"
                    : "Your order #{$this->order->number} has shipped!"
            )
            ->greeting($locale === 'fr' ? "Bonjour {$name}," : "Hi {$name},")
            ->line(
                $locale === 'fr'
                    ? "Bonne nouvelle ! Votre commande #{$this->order->number} est en route."
                    : "Great news! Your order #{$this->order->number} is on its way."
            );

        if ($this->trackingNumber) {
            $message->line(
                $locale === 'fr'
                    ? "Numéro de suivi : {$this->trackingNumber}"
                    : "Tracking Number: {$this->trackingNumber}"
            );
        }

        if ($this->carrier) {
            $message->line(
                $locale === 'fr'
                    ? "Transporteur : {$this->carrier}"
                    : "Carrier: {$this->carrier}"
            );
        }

        $message->action(
            $locale === 'fr' ? 'Suivre votre colis' : 'Track Your Package',
            $this->trackingLink()
        );

        if ($locale === 'fr') {
            $message->line('La livraison prend généralement 7 à 14 jours ouvrés selon votre localisation.')
                ->line('Merci pour votre commande !');
        } else {
            $message->line('Delivery usually takes 7-14 business days depending on your location.')
                ->line('Thank you for your order!');
        }

        return $message;
    }

    public function toWhatsApp(object $notifiable): string
    {
        $locale = $this->getLocale($notifiable);
        $tracking = $this->trackingNumber
            ? ($locale === 'fr'
                ? " Suivi : {$this->trackingNumber}."
                : " Tracking: {$this->trackingNumber}.")
            : '';

        if ($locale === 'fr') {
            return "Votre commande #{$this->order->number} a été expédiée !{$tracking} Suivez-la ici : {$this->trackingLink()}";
        }

        return "Your order #{$this->order->number} has shipped!{$tracking} Track it here: {$this->trackingLink()}";
    }

    public function toExpoNotification(object $notifiable): ExpoMessage
    {
        $tokens = method_exists($notifiable, 'expoTokens')
            ? $notifiable->expoTokens()->pluck('value')->all()
            : [];

        $locale = $this->getLocale($notifiable);

        return (new ExpoMessage())
            ->to($tokens)
            ->title($locale === 'fr' ? 'Commande expédiée 🚚' : 'Order shipped 🚚')
            ->body(
                $locale === 'fr'
                    ? "Votre commande #{$this->order->number} est en route ! Suivez-la en temps réel."
                    : "Your order #{$this->order->number} is on its way! Track it in real-time."
            )
            ->channelId('orders')
            ->jsonData([
                'type' => 'order_shipped',
                'order_number' => $this->order->number,
                'screen' => 'Orders',
            ]);
    }

    private function trackingLink(): string
    {
        if ($this->trackingUrl) {
            return $this->trackingUrl;
        }

        return url("/orders/track?number={$this->order->number}&email={$this->order->email}");
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
