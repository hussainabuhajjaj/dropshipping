<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendNewsletterCampaignChunk;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignLog;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class NewsletterCampaignService
{
    public function createAndQueueCampaign(
        array $data,
        Builder $subscriberQuery,
        ?User $actor = null,
        bool $sendNow = false,
        ?int $limit = null
    ): NewsletterCampaign
    {
        $campaign = NewsletterCampaign::create([
            'subject' => $data['subject'],
            'body_markdown' => $data['body_markdown'],
            'action_url' => $data['action_url'] ?? null,
            'action_label' => $data['action_label'] ?? null,
            'status' => 'queued',
            'sent_by' => $actor?->id,
            'sent_at' => null,
            'total_subscribers' => 0,
        ]);

        $subscriberIds = null;
        if ($limit !== null) {
            $subscriberIds = (clone $subscriberQuery)->limit($limit)->pluck('id')->all();
            $subscriberQuery = NewsletterSubscriber::query()->whereKey($subscriberIds);
        }

        $count = 0;
        $subscriberQuery->chunkById(200, function ($subscribers) use ($campaign, &$count, $sendNow) {
            $logIds = [];

            foreach ($subscribers as $subscriber) {
                /** @var NewsletterSubscriber $subscriber */
                if (! $subscriber->unsubscribe_token) {
                    $subscriber->ensureUnsubscribeToken();
                }

                $log = NewsletterCampaignLog::create([
                    'newsletter_campaign_id' => $campaign->id,
                    'newsletter_subscriber_id' => $subscriber->id,
                    'email' => $subscriber->email,
                    'tracking_token' => $this->generateTrackingToken(),
                    'status' => 'queued',
                ]);

                $logIds[] = $log->id;
                $count++;
            }

            if ($logIds) {
                if ($sendNow) {
                    SendNewsletterCampaignChunk::dispatchSync($campaign->id, $logIds);
                } else {
                    SendNewsletterCampaignChunk::dispatch($campaign->id, $logIds);
                }
            }
        });

        $totalSubscribers = $subscriberIds !== null ? count($subscriberIds) : $count;
        $payload = [
            'total_subscribers' => $totalSubscribers,
        ];
        if (! $sendNow) {
            $payload['status'] = $count > 0 ? 'queued' : 'empty';
        } elseif ($count === 0) {
            $payload['status'] = 'empty';
        }

        $campaign->update($payload);

        return $campaign;
    }

    private function generateTrackingToken(): string
    {
        $token = Str::random(48);
        $attempts = 0;

        while ($attempts < 3 && NewsletterCampaignLog::query()->where('tracking_token', $token)->exists()) {
            $attempts++;
            $token = Str::random(48);
        }

        return $token;
    }
}
