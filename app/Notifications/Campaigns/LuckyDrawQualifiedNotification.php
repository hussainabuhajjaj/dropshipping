<?php

declare(strict_types=1);

namespace App\Notifications\Campaigns;

use App\Domain\Campaigns\Models\CampaignParticipation;
use App\Models\StorefrontCampaign;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use YieldStudio\LaravelExpoNotifier\Dto\ExpoMessage;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class LuckyDrawQualifiedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly StorefrontCampaign $campaign,
        private readonly CampaignParticipation $participation,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        if (method_exists($notifiable, 'expoTokens')) {
            $channels[] = ExpoNotificationsChannel::class;
        }

        if ($notifiable->phone) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = app()->getLocale();
        $name = $this->campaign->localizedValue('name', $locale) ?: $this->campaign->name;

        return (new MailMessage)
            ->subject($this->subject($locale, $name))
            ->greeting($locale === 'fr' ? "Félicitations !" : 'Congratulations!')
            ->line($this->body($locale, $name))
            ->line($this->spotLine($locale, $name))
            ->action($locale === 'fr' ? 'Voir ma participation' : 'View my entry', url('/campaigns/' . $this->campaign->slug));
    }

    public function toArray(object $notifiable): array
    {
        $locale = app()->getLocale();
        $name = $this->campaign->localizedValue('name', $locale) ?: $this->campaign->name;

        return [
            'type' => 'lucky_draw_qualified',
            'campaign_id' => $this->campaign->id,
            'campaign_slug' => $this->campaign->slug,
            'spot_number' => $this->participation->spot_number,
            'title' => $this->subject($locale, $name),
            'body' => $this->body($locale, $name) . ' ' . $this->spotLine($locale, $name),
            'action_url' => url('/campaigns/' . $this->campaign->slug),
        ];
    }

    public function toExpoNotification(object $notifiable): ExpoMessage
    {
        $locale = app()->getLocale();
        $name = $this->campaign->localizedValue('name', $locale) ?: $this->campaign->name;
        $tokens = $notifiable->expoTokens()->pluck('value')->all();

        return (new ExpoMessage())
            ->to($tokens)
            ->title($this->subject($locale, $name))
            ->body($this->body($locale, $name))
            ->channelId('campaigns')
            ->jsonData([
                'type' => 'lucky_draw_qualified',
                'campaign_id' => $this->campaign->id,
                'campaign_slug' => $this->campaign->slug,
                'screen' => 'Campaigns',
            ]);
    }

    public function toWhatsApp(object $notifiable): string
    {
        $locale = app()->getLocale();
        $name = $this->campaign->localizedValue('name', $locale) ?: $this->campaign->name;

        return $this->subject($locale, $name)
            . "\n\n" . $this->body($locale, $name)
            . "\n\n" . $this->spotLine($locale, $name)
            . "\n\n👉 " . url('/campaigns/' . $this->campaign->slug);
    }

    private function subject(string $locale, string $name): string
    {
        return $locale === 'fr'
            ? "🎉 Vous êtes qualifié pour le tirage au sort {$name} !"
            : "🎉 You're qualified for the {$name} lucky draw!";
    }

    private function body(string $locale, string $name): string
    {
        return $locale === 'fr'
            ? "Votre commande éligible a été enregistrée pour le tirage au sort {$name}."
            : "Your qualifying order has been registered for the {$name} lucky draw.";
    }

    private function spotLine(string $locale, string $name): string
    {
        if ($this->participation->hasReservedSpot()) {
            $max = (int) ($this->campaign->luckyDrawConfig()['max_participants'] ?? 0);

            return $locale === 'fr'
                ? "✅ Vous avez sécurisé l'un des {$max} emplacements du tirage au sort (#{$this->participation->spot_number})."
                : "✅ You secured one of the {$max} lucky draw spots (spot #{$this->participation->spot_number}).";
        }

        return $locale === 'fr'
            ? "Vous recevrez une récompense garantie à la fin de la campagne."
            : "You will receive a guaranteed reward when the campaign ends.";
    }
}
