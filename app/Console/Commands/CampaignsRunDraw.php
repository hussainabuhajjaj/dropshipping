<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Campaigns\Services\LuckyDrawService;
use App\Models\StorefrontCampaign;
use Illuminate\Console\Command;

class CampaignsRunDraw extends Command
{
    protected $signature = 'campaigns:run-draw
        {campaign? : Lucky-draw campaign slug}
        {--dry-run : Log what would happen without selecting winners}';

    protected $description = 'Run the random lucky draw for a lucky-draw campaign';

    public function handle(LuckyDrawService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $campaigns = $this->resolveCampaigns();

        if ($campaigns->isEmpty()) {
            $this->warn('No lucky-draw campaigns found.');
            return 0;
        }

        foreach ($campaigns as $campaign) {
            $participantCount = $campaign->participations()->whereNotNull('spot_number')->count();

            if ($campaign->winners()->whereIn('prize_type', ['grand', 'runner_up'])->exists()) {
                $this->line("[SKIP] Campaign #{$campaign->id} ({$campaign->name}): draw already run.");
                continue;
            }

            if ($participantCount === 0) {
                $this->line("[SKIP] Campaign #{$campaign->id} ({$campaign->name}): no participants.");
                continue;
            }

            if ($dryRun) {
                $this->line("[DRY-RUN] Campaign #{$campaign->id} ({$campaign->name}): would draw from {$participantCount} participants.");
                continue;
            }

            $result = $service->runDraw($campaign);

            $this->info("[DRAW] Campaign #{$campaign->id} ({$campaign->name}): "
                . ($result['grand'] ? 'grand winner selected' : 'no grand winner')
                . ", {$result['runner_ups']->count()} runner-ups.");
        }

        return 0;
    }

    private function resolveCampaigns(): \Illuminate\Support\Collection
    {
        $slug = $this->argument('campaign');

        if ($slug) {
            return StorefrontCampaign::query()
                ->where('slug', $slug)
                ->where('type', 'lucky_draw')
                ->get();
        }

        return StorefrontCampaign::query()->where('type', 'lucky_draw')->get();
    }
}
