<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\NewsletterCampaignMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignLog;
use App\Services\NewsletterCampaignRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendNewsletterCampaignChunk implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $campaignId;
    /** @var array<int> */
    public array $logIds;

    public function __construct(int $campaignId, array $logIds)
    {
        $this->campaignId = $campaignId;
        $this->logIds = $logIds;
    }

    public function handle(NewsletterCampaignRenderer $renderer): void
    {
        $campaign = NewsletterCampaign::query()->find($this->campaignId);
        if (! $campaign) {
            return;
        }

        $logs = NewsletterCampaignLog::query()
            ->whereIn('id', $this->logIds)
            ->with('subscriber')
            ->get();

        foreach ($logs as $log) {
            $subscriber = $log->subscriber;
            if (! $subscriber) {
                $log->update([
                    'status' => 'failed',
                    'error_message' => 'Subscriber not found.',
                ]);
                continue;
            }

            if ($subscriber->unsubscribed_at) {
                $log->update([
                    'status' => 'unsubscribed',
                    'error_message' => null,
                ]);
                continue;
            }

            if (! $subscriber->unsubscribe_token) {
                $subscriber->ensureUnsubscribeToken();
            }

            try {
                if (! $log->tracking_token) {
                    $log->tracking_token = Str::random(48);
                    $log->save();
                }

                $openUrl = route('newsletter.track.open', ['token' => $log->tracking_token]);
                $clickUrl = route('newsletter.track.click', ['token' => $log->tracking_token]);

                $html = $renderer->renderWithTracking($campaign, $subscriber, $openUrl);
                Mail::to($log->email)->send(new NewsletterCampaignMail($campaign, $html, $clickUrl, $campaign->action_label));

                $log->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null,
                ]);
            } catch (\Throwable $e) {
                Log::error('Newsletter campaign send failed', [
                    'campaign_id' => $campaign->id,
                    'log_id' => $log->id,
                    'email' => $log->email,
                    'error' => $e->getMessage(),
                ]);
                $log->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        $remaining = NewsletterCampaignLog::query()
            ->where('newsletter_campaign_id', $campaign->id)
            ->where('status', 'queued')
            ->count();

        if ($remaining === 0) {
            $sentCount = NewsletterCampaignLog::query()
                ->where('newsletter_campaign_id', $campaign->id)
                ->where('status', 'sent')
                ->count();
            $failedCount = NewsletterCampaignLog::query()
                ->where('newsletter_campaign_id', $campaign->id)
                ->where('status', 'failed')
                ->count();
            $unsubscribedCount = NewsletterCampaignLog::query()
                ->where('newsletter_campaign_id', $campaign->id)
                ->where('status', 'unsubscribed')
                ->count();

            $status = 'sent';
            if ($sentCount === 0 && $failedCount === 0 && $unsubscribedCount > 0) {
                $status = 'empty';
            } elseif ($sentCount === 0 && $failedCount > 0) {
                $status = 'failed';
            }

            $campaign->update([
                'status' => $status,
                'sent_at' => now(),
            ]);
        }
    }
}
