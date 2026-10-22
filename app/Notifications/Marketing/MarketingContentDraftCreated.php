<?php

declare(strict_types=1);

namespace App\Notifications\Marketing;

use App\Models\MarketingContentDraft;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketingContentDraftCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private MarketingContentDraft $draft)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();

        return (new MailMessage())
            ->subject('New marketing draft pending review')
            ->line('A new marketing content draft needs review.')
            ->line('Type: ' . ($payload['target_type'] ?? ''))
            ->line('Locale: ' . ($payload['locale'] ?? ''))
            ->action('Review drafts', url('/admin/marketing-content-drafts'));
    }

    private function payload(): array
    {
        return [
            'draft_id' => $this->draft->id,
            'target_type' => $this->draft->target_type,
            'target_id' => $this->draft->target_id,
            'locale' => $this->draft->locale,
            'channel' => $this->draft->channel,
            'status' => $this->draft->status,
        ];
    }
}
