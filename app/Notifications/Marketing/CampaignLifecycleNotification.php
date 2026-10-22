<?php

namespace App\Notifications\Marketing;

use App\Models\StorefrontCampaign;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use YieldStudio\LaravelExpoNotifier\Dto\ExpoMessage;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class CampaignLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly StorefrontCampaign $campaign,
        private readonly string $event, // started | ending_soon | ended
        private readonly array $channelOverrides = [],
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        if (! ($notifiable->marketing_opt_in ?? true)) {
            return [];
        }

        $config = $this->campaign->notificationConfig();
        $eventConfig = $config[$this->configKey()] ?? [];

        $channels = [];

        if ($eventConfig['push'] ?? true) {
            if (method_exists($notifiable, 'expoTokens')) {
                $channels[] = ExpoNotificationsChannel::class;
            }
        }

        if ($eventConfig['email'] ?? true) {
            $channels[] = 'mail';
        }

        if ($eventConfig['whatsapp'] ?? false) {
            if ($notifiable->phone) {
                $channels[] = WhatsAppChannel::class;
            }
        }

        $channels[] = 'database';

        return $channels;
    }

    public function toMail(object $notifiable): array
    {
        return [
            'subject' => $this->getSubject(),
            'html' => $this->getEmailHtml(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'campaign_' . $this->event,
            'campaign_id' => $this->campaign->id,
            'campaign_slug' => $this->campaign->slug,
            'title' => $this->getSubject(),
            'body' => $this->getBody(),
            'action_url' => url('/campaigns/' . $this->campaign->slug),
        ];
    }

    public function toExpoNotification(object $notifiable): ExpoMessage
    {
        $tokens = method_exists($notifiable, 'expoTokens')
            ? $notifiable->expoTokens()->pluck('value')->all()
            : [];

        return (new ExpoMessage())
            ->to($tokens)
            ->title($this->getSubject())
            ->body($this->getBody(80))
            ->channelId('campaigns')
            ->jsonData([
                'type' => 'campaign_' . $this->event,
                'campaign_id' => $this->campaign->id,
                'campaign_slug' => $this->campaign->slug,
                'screen' => 'Campaigns',
            ]);
    }

    public function toWhatsApp(object $notifiable): string
    {
        $url = url('/campaigns/' . $this->campaign->slug);
        return "{$this->getSubject()}\n\n{$this->getBody()}\n\n👉 $url";
    }

    private function configKey(): string
    {
        return match ($this->event) {
            'started' => 'on_start',
            'ending_soon' => 'on_ending_soon',
            'ended' => 'on_end',
            default => $this->event,
        };
    }

    private function getSubject(): string
    {
        $locale = app()->getLocale();
        $name = $this->campaign->localizedValue('name', $locale) ?: $this->campaign->name;

        return match ($this->event) {
            'started' => $locale === 'fr'
                ? "🔥 {$name} est lancé !"
                : "🔥 {$name} is live!",
            'ending_soon' => $locale === 'fr'
                ? "⏰ Dernière chance — {$name} se termine bientôt !"
                : "⏰ Last chance — {$name} ends soon!",
            'ended' => $locale === 'fr'
                ? "🎉 {$name} est terminé — à la prochaine !"
                : "🎉 {$name} has ended — see you next time!",
            default => $name,
        };
    }

    private function getBody(int $maxLength = 0): string
    {
        $locale = app()->getLocale();
        $subtitle = $this->campaign->localizedValue('hero_subtitle', $locale) ?: $this->campaign->hero_subtitle;

        $body = match ($this->event) {
            'started' => $locale === 'fr'
                ? ($subtitle ?: "Ne manquez pas nos offres exceptionnelles !")
                : ($subtitle ?: "Don't miss out on our exclusive offers!"),
            'ending_soon' => $locale === 'fr'
                ? "Plus que quelques heures. Dépêchez-vous avant la fin des offres !"
                : "Only hours left. Hurry before the deals are gone!",
            'ended' => $locale === 'fr'
                ? "Merci d'avoir participé. Restez connecté pour nos prochaines offres."
                : "Thanks for participating. Stay tuned for our next offers.",
            default => $subtitle ?? '',
        };

        if ($maxLength > 0 && mb_strlen($body) > $maxLength) {
            $body = mb_substr($body, 0, $maxLength - 3) . '...';
        }

        return $body;
    }

    private function getEmailHtml(): string
    {
        $locale = app()->getLocale();
        $name = $this->campaign->localizedValue('name', $locale) ?: $this->campaign->name;
        $url = url('/campaigns/' . $this->campaign->slug);

        $heading = $locale === 'fr' ? $name : $name;
        $cta = $locale === 'fr' ? 'Voir la campagne' : 'View campaign';
        $unsubscribe = $locale === 'fr'
            ? 'Si vous ne souhaitez plus recevoir ces emails, cliquez ici'
            : 'If you no longer wish to receive these emails, click here';

        return <<<HTML
        <div style="font-family:sans-serif;max-width:600px;margin:0 auto;">
            <div style="background:#0f172a;padding:30px;text-align:center;">
                <h1 style="color:#f59e0b;margin:0;">{$heading}</h1>
            </div>
            <div style="padding:30px;background:#fff;">
                <p style="font-size:16px;color:#333;">{$this->getBody()}</p>
                <a href="{$url}" style="display:inline-block;padding:12px 30px;background:#f59e0b;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;margin:20px 0;">
                    {$cta}
                </a>
            </div>
            <div style="padding:20px;text-align:center;font-size:12px;color:#999;">
                <p>{$unsubscribe}</p>
            </div>
        </div>
HTML;
    }
}
