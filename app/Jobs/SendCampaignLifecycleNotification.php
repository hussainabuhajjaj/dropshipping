<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\StorefrontCampaign;
use App\Notifications\Marketing\CampaignLifecycleNotification;
use App\Services\SegmentEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCampaignLifecycleNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        private readonly StorefrontCampaign $campaign,
        private readonly string $event,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $config = $this->campaign->notificationConfig();
        $eventConfig = $config[$this->event] ?? [];

        if (empty(array_filter($eventConfig))) {
            Log::info("[CampaignNotify] No channels enabled for {$this->campaign->name} / {$this->event}");
            return;
        }

        $batchSize = 100;

        $query = Customer::query()
            ->where('marketing_opt_in', true)
            ->whereNotNull('email')
            ->whereNull('deleted_at');

        $segmentIds = $this->campaign->segmentIds();

        if (! empty($segmentIds)) {
            $segments = CustomerSegment::whereIn('id', $segmentIds)->where('is_active', true)->get();

            if ($segments->isEmpty()) {
                Log::info("[CampaignNotify] Campaign #{$this->campaign->id} has segment_ids but no active segments found, skipping");
                return;
            }

            $engine = app(SegmentEngine::class);

            $query->where(function ($q) use ($segments, $engine) {
                $first = true;
                foreach ($segments as $segment) {
                    if ($first) {
                        $engine->apply($q, $segment);
                        $first = false;
                    } else {
                        $q->orWhere(function ($sub) use ($segment, $engine) {
                            $engine->apply($sub, $segment);
                        });
                    }
                }
            });

            Log::info("[CampaignNotify] Campaign #{$this->campaign->id} filtered by " . $segments->count() . ' segment(s)');
        }

        $query->chunk($batchSize, function ($customers) {
            foreach ($customers as $customer) {
                $customer->notify(new CampaignLifecycleNotification(
                    $this->campaign,
                    $this->event,
                ));
            }
        });

        Log::info("[CampaignNotify] Sent {$this->event} notifications for campaign #{$this->campaign->id} ({$this->campaign->name})");
    }
}
