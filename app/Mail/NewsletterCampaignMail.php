<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\NewsletterCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class NewsletterCampaignMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public NewsletterCampaign $campaign,
        public string $htmlBody,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {
    }

    public function build(): self
    {
        $preheader = Str::limit(trim(strip_tags($this->htmlBody)), 120);

        return $this->subject($this->campaign->subject)
            ->view('emails.base', [
                'title' => $this->campaign->subject,
                'preheader' => $preheader,
                'bodyHtml' => $this->htmlBody,
                'actionUrl' => $this->actionUrl ?? $this->campaign->action_url,
                'actionLabel' => $this->actionLabel ?? $this->campaign->action_label,
            ]);
    }
}
