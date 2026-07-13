<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OrderItem;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use YieldStudio\LaravelExpoNotifier\Dto\ExpoMessage;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class ReviewRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly OrderItem $orderItem)
    {
    }

    public function via(object $notifiable): array
    {
        if (! ($notifiable->marketing_opt_in ?? true)) {
            return [];
        }

        $channels = ['mail', 'database'];

        if (method_exists($notifiable, 'expoTokens') && $notifiable->expoTokens()->exists()) {
            $channels[] = ExpoNotificationsChannel::class;
        }

        if (isset($notifiable->phone) && $notifiable->phone) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        $locale = $this->getLocale($notifiable);
        $product = $this->orderItem->productVariant?->product;
        $orderNumber = $this->orderItem->order?->number;
        $reviewUrl = $this->reviewUrl($product);

        return [
            'order_number' => $orderNumber,
            'product_name' => $product?->name,
            'title' => $locale === 'fr' ? 'Donnez votre avis' : 'Write a review',
            'body' => $locale === 'fr'
                ? "Comment s'est passé votre achat de {$product?->name} ?"
                : "How was your {$product?->name}?",
            'action_url' => $reviewUrl,
            'action_label' => $locale === 'fr' ? 'Écrire un avis' : 'Write a review',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->getLocale($notifiable);
        $product = $this->orderItem->productVariant?->product;
        $orderNumber = $this->orderItem->order?->number;
        $reviewUrl = $this->reviewUrl($product);

        $mail = (new MailMessage())
            ->subject(
                $locale === 'fr'
                    ? 'Comment s\'est passé votre achat ?'
                    : 'How was your recent purchase?'
            )
            ->greeting(
                $locale === 'fr'
                    ? 'Nous aimerions avoir votre avis'
                    : 'We\'d love to hear from you'
            );

        if ($locale === 'fr') {
            $mail->line("Votre commande #{$orderNumber} a été livrée. Que pensez-vous de {$product?->name} ?");
        } else {
            $mail->line("Your order #{$orderNumber} has been delivered. How did you like the {$product?->name}?");
        }

        $mail->action(
            $locale === 'fr' ? 'Écrire un avis' : 'Write a Review',
            $reviewUrl
        );

        if ($locale === 'fr') {
            $mail->line('Votre avis aide d\'autres clients à faire le bon choix.');
        } else {
            $mail->line('Your feedback helps other customers make informed decisions.');
        }

        return $mail;
    }

    public function toExpoNotification(object $notifiable): ExpoMessage
    {
        $tokens = method_exists($notifiable, 'expoTokens')
            ? $notifiable->expoTokens()->pluck('value')->all()
            : [];

        $locale = $this->getLocale($notifiable);
        $product = $this->orderItem->productVariant?->product;
        $productName = $product?->name ?? ($locale === 'fr' ? 'votre article' : 'your item');

        return (new ExpoMessage())
            ->to($tokens)
            ->title($locale === 'fr' ? 'Donnez votre avis' : 'Write a review')
            ->body(
                $locale === 'fr'
                    ? "Comment s'est passé {$productName} ?"
                    : "How was {$productName}?"
            )
            ->channelId('marketing')
            ->jsonData([
                'type' => 'review_request',
                'order_item_id' => $this->orderItem->id,
                'screen' => 'Orders',
            ]);
    }

    public function toWhatsApp(object $notifiable): string
    {
        $locale = $this->getLocale($notifiable);
        $product = $this->orderItem->productVariant?->product;
        $productName = $product?->name ?? 'your item';
        $url = $this->reviewUrl($product);

        if ($locale === 'fr') {
            return "Votre commande #{$this->orderItem->order?->number} a été livrée !\n\nQue pensez-vous de {$productName} ? Donnez votre avis ici : {$url}";
        }

        return "Your order #{$this->orderItem->order?->number} was delivered!\n\nHow did you like {$productName}? Leave a review: {$url}";
    }

    private function reviewUrl($product): string
    {
        return $product
            ? route('products.show', ['product' => $product->id]) . '#reviews'
            : url('/orders');
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
