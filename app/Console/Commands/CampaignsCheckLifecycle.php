<?php

namespace App\Console\Commands;

use App\Jobs\SendCampaignLifecycleNotification;
use App\Models\StorefrontCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CampaignsCheckLifecycle extends Command
{
    protected $signature = 'campaigns:check-lifecycle
        {--dry-run : Log what would happen without sending}';

    protected $description = 'Check campaign lifecycle events and dispatch notifications';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();
        $checked = 0;
        $dispatched = 0;

        $campaigns = StorefrontCampaign::query()
            ->where('is_active', true)
            ->whereIn('status', ['active', 'approved', 'scheduled'])
            ->get();

        foreach ($campaigns as $campaign) {
            $config = $campaign->notificationConfig();

            // Campaign just started (within last 15 min)
            if ($campaign->starts_at && $campaign->status === 'active') {
                $startedAgo = $now->diffInMinutes($campaign->starts_at, false);
                if ($startedAgo >= 0 && $startedAgo <= 15) {
                    $eventConfig = $config['on_start'] ?? [];
                    $this->processEvent($campaign, 'on_start', 'started', $eventConfig, $dryRun, $dispatched);
                }
            }

            // Campaign ending soon (hours_before before end)
            if ($campaign->ends_at && $campaign->status === 'active') {
                $eventConfig = $config['on_ending_soon'] ?? [];
                $hoursBefore = $eventConfig['hours_before'] ?? 24;
                $timeUntilEnd = $now->diffInHours($campaign->ends_at, false);

                if ($timeUntilEnd > 0 && $timeUntilEnd <= $hoursBefore) {
                    $this->processEvent($campaign, 'on_ending_soon', 'ending_soon', $eventConfig, $dryRun, $dispatched);
                }
            }

            // Campaign just ended (within last 15 min)
            if ($campaign->ends_at) {
                $endedAgo = $now->diffInMinutes($campaign->ends_at, false);
                if ($endedAgo >= 0 && $endedAgo <= 15) {
                    $eventConfig = $config['on_end'] ?? [];
                    $this->processEvent($campaign, 'on_end', 'ended', $eventConfig, $dryRun, $dispatched);
                }
            }

            $checked++;
        }

        $this->info("Checked {$checked} campaigns, dispatched {$dispatched} notifications.");

        if ($dryRun) {
            $this->warn('Dry-run — no notifications were actually sent.');
        }

        return 0;
    }

    private function processEvent(
        StorefrontCampaign $campaign,
        string $configKey,
        string $eventName,
        array $eventConfig,
        bool $dryRun,
        int &$dispatched,
    ): void {
        $channels = array_filter([
            'push' => $eventConfig['push'] ?? false,
            'email' => $eventConfig['email'] ?? false,
            'whatsapp' => $eventConfig['whatsapp'] ?? false,
        ]);

        if (empty($channels)) {
            $this->line("[SKIP] Campaign #{$campaign->id} ({$campaign->name}) / {$eventName}: no channels enabled");
            return;
        }

        $channelLabels = implode(', ', array_keys($channels));

        if ($dryRun) {
            $this->line("[DRY-RUN] Campaign #{$campaign->id} ({$campaign->name}) / {$eventName} → {$channelLabels}");
            return;
        }

        SendCampaignLifecycleNotification::dispatch($campaign, $eventName);
        $dispatched++;

        $this->line("[DISPATCH] Campaign #{$campaign->id} ({$campaign->name}) / {$eventName} → {$channelLabels}");
    }
}
