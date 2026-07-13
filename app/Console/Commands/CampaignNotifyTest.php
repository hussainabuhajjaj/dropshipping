<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendCampaignLifecycleNotification;
use App\Models\CustomerSegment;
use App\Models\StorefrontCampaign;
use App\Services\SegmentEngine;
use Illuminate\Console\Command;

class CampaignNotifyTest extends Command
{
    protected $signature = 'campaign:notify-test
        {campaign? : Campaign ID or slug}
        {event? : on_start, on_ending_soon, or on_end}
        {--dry-run : Show what would be sent without dispatching}
        {--list : List available campaigns and events}';

    protected $description = 'Manually dispatch campaign notifications for testing';

    public function handle(SegmentEngine $engine): int
    {
        if ($this->option('list')) {
            return $this->listCampaigns($engine);
        }

        $campaignId = $this->argument('campaign');
        $event = $this->argument('event') ?? 'on_start';
        $dryRun = (bool) $this->option('dry-run');

        if (! $campaignId) {
            $this->error('Specify a campaign ID/slug or use --list to see available campaigns.');
            return 1;
        }

        $campaign = is_numeric($campaignId)
            ? StorefrontCampaign::find((int) $campaignId)
            : StorefrontCampaign::where('slug', $campaignId)->first();

        if (! $campaign) {
            $this->error("Campaign not found: {$campaignId}");
            return 1;
        }

        $validEvents = ['on_start', 'on_ending_soon', 'on_end'];
        if (! in_array($event, $validEvents, true)) {
            $this->error("Invalid event: {$event}. Valid: " . implode(', ', $validEvents));
            return 1;
        }

        $config = $campaign->notificationConfig();
        $eventConfig = $config[$event] ?? [];
        $enabledChannels = array_keys(array_filter($eventConfig));

        $this->info("=== Campaign Notification Test ===");
        $this->line("  Campaign:  {$campaign->name} (ID: {$campaign->id})");
        $this->line("  Status:    {$campaign->status}");
        $this->line("  Event:     {$event}");
        $this->line("  Channels:  " . (empty($enabledChannels) ? 'none' : implode(', ', $enabledChannels)));

        if (empty($enabledChannels)) {
            $this->warn('No channels enabled for this event. Update notification_config on the campaign.');
            return 1;
        }

        // Segment info
        $segmentIds = $campaign->segmentIds();
        if (! empty($segmentIds)) {
            $segments = CustomerSegment::whereIn('id', $segmentIds)->where('is_active', true)->get();
            $this->line("  Segments:  {$segments->pluck('name')->implode(', ')}");
            foreach ($segments as $seg) {
                $this->line("    -> {$seg->name}: {$engine->count($seg)} matching customers");
            }
        } else {
            $this->line("  Segments:  none (sends to ALL opted-in customers)");
        }

        if ($dryRun) {
            $this->warn('Dry-run — no notifications dispatched.');
            return 0;
        }

        $this->line('');
        $this->info("Dispatching {$event} notifications...");

        SendCampaignLifecycleNotification::dispatch($campaign, $event);

        $this->info("Done. Notification queued on 'notifications' queue.");
        $this->line("Run 'php artisan queue:work --queue=notifications' to process.");

        return 0;
    }

    private function listCampaigns(SegmentEngine $engine): int
    {
        $this->info("=== Available Campaigns ===");

        $campaigns = StorefrontCampaign::query()
            ->orderBy('starts_at')
            ->get();

        if ($campaigns->isEmpty()) {
            $this->warn('No campaigns found.');
            return 0;
        }

        foreach ($campaigns as $c) {
            $config = $c->notificationConfig();
            $events = [];
            foreach (['on_start', 'on_ending_soon', 'on_end'] as $ev) {
                $channels = array_keys(array_filter($config[$ev] ?? []));
                if (! empty($channels)) {
                    $events[] = "{$ev}(" . implode(',', $channels) . ')';
                }
            }

            $segCount = '';
            $segmentIds = $c->segmentIds();
            if (! empty($segmentIds)) {
                $segments = CustomerSegment::whereIn('id', $segmentIds)->where('is_active', true)->get();
                $totalCustomers = $segments->sum(fn ($s) => $engine->count($s));
                $segCount = " [segments: {$segments->count()} groups, ~{$totalCustomers} customers]";
            }

            $this->line("  [{$c->id}] {$c->name}");
            $this->line("        slug: {$c->slug}  |  {$c->starts_at?->format('M j')} → {$c->ends_at?->format('M j')}  |  {$c->status}");
            $this->line("        events: " . implode(', ', $events) . $segCount);
            $this->line('');
        }

        $this->info('Usage: php artisan campaign:notify-test {id|slug} {on_start|on_ending_soon|on_end}');

        return 0;
    }
}
