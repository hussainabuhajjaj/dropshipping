<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Campaigns\Services\LuckyDrawService;
use App\Models\StorefrontCampaign;
use Illuminate\Console\Command;

class CampaignsIssueGuaranteedRewards extends Command
{
    protected $signature = 'campaigns:issue-guaranteed-rewards
        {campaign? : Lucky-draw campaign slug}
        {--dry-run : Log what would happen without issuing rewards}';

    protected $description = 'Issue guaranteed rewards to non-winning lucky-draw participants';

    public function handle(LuckyDrawService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $campaigns = $this->resolveCampaigns();

        if ($campaigns->isEmpty()) {
            $this->warn('No lucky-draw campaigns found.');
            return 0;
        }

        foreach ($campaigns as $campaign) {
            $eligible = $campaign->participations()
                ->whereNotNull('spot_number')
                ->whereNull('reward_code')
                ->count();

            if ($eligible === 0) {
                $this->line("[SKIP] Campaign #{$campaign->id} ({$campaign->name}): all rewards already issued.");
                continue;
            }

            if ($dryRun) {
                $this->line("[DRY-RUN] Campaign #{$campaign->id} ({$campaign->name}): would issue {$eligible} guaranteed rewards.");
                continue;
            }

            $issued = $service->issueGuaranteedRewards($campaign);

            $this->info("[REWARDS] Campaign #{$campaign->id} ({$campaign->name}): issued {$issued} guaranteed rewards.");
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
