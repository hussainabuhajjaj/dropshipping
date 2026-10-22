<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Campaigns\Services\LuckyDrawService;
use App\Models\StorefrontCampaign;
use Illuminate\Console\Command;

class CampaignsAnnounceWinners extends Command
{
    protected $signature = 'campaigns:announce-winners
        {campaign? : Lucky-draw campaign slug}
        {--dry-run : Log what would happen without announcing}';

    protected $description = 'Mark lucky-draw winners as announced and notify them';

    public function handle(LuckyDrawService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $campaigns = $this->resolveCampaigns();

        if ($campaigns->isEmpty()) {
            $this->warn('No lucky-draw campaigns found.');
            return 0;
        }

        foreach ($campaigns as $campaign) {
            $pending = $campaign->winners()->whereNull('announced_at')->count();

            if ($pending === 0) {
                $this->line("[SKIP] Campaign #{$campaign->id} ({$campaign->name}): all winners already announced.");
                continue;
            }

            if ($dryRun) {
                $this->line("[DRY-RUN] Campaign #{$campaign->id} ({$campaign->name}): would announce {$pending} winners.");
                continue;
            }

            $announced = $service->announceWinners($campaign);

            $this->info("[ANNOUNCE] Campaign #{$campaign->id} ({$campaign->name}): announced {$announced} winners.");
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
