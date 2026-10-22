<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CampaignProductQuery;
use App\Models\CustomerSegment;
use App\Models\StorefrontCampaign;
use App\Services\SegmentEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CampaignsSetupNewSeason extends Command
{
    protected $signature = 'campaigns:setup-new-season
        {--dry-run : Log what would happen without creating}
        {--force : Create even if similarly-named campaigns exist}';

    protected $description = 'Create a new seasonal campaign with segments, sourcing config, and notifications';

    private const TZ = 'Africa/Abidjan';

    public function handle(SegmentEngine $engine): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info('=== Setting Up New Season Campaign ===');

        // ─── 1. Check for existing ───────────────────────────────────
        $slug = 'fashion-week-abidjan-2026';
        $existing = StorefrontCampaign::where('slug', $slug)->first();

        if ($existing && ! $force) {
            $this->warn("Campaign '{$slug}' already exists. Use --force to recreate.");
            $this->line('');
            $this->showCampaignStatus($existing, $engine);
            return 1;
        }

        if ($existing && $force) {
            if (! $dryRun) {
                $existing->productQuery()->delete();
                $existing->delete();
                $this->line('Removed existing campaign for re-creation.');
            } else {
                $this->line('[DRY-RUN] Would remove existing campaign.');
            }
        }

        // ─── 2. Create segments ──────────────────────────────────────
        $this->info("\n--- Creating customer segments ---");

        $segments = [];
        $segmentDefs = $this->getSegmentDefinitions();

        foreach ($segmentDefs as $def) {
            if (! $dryRun) {
                $segment = CustomerSegment::updateOrCreate(
                    ['slug' => $def['slug']],
                    [
                        'name' => $def['name'],
                        'description' => $def['description'],
                        'rules' => $def['rules'],
                        'is_active' => true,
                    ]
                );
                $segments[$def['slug']] = $segment;
                $count = $engine->count($segment);
                $this->line("  [OK] {$def['name']} — {$count} matching customers");
            } else {
                $this->line("  [DRY-RUN] Would create segment: {$def['name']}");
            }
        }

        // ─── 3. Create campaign ─────────────────────────────────────
        $this->info("\n--- Creating campaign ---");

        $campaignData = $this->getCampaignData();
        $productQueryData = $this->getProductQueryData();

        if (! $dryRun) {
            $campaign = StorefrontCampaign::create($campaignData);

            $campaign->productQuery()->create($productQueryData);

            if (! empty($segments)) {
                $segmentIds = array_map(fn ($s) => $s->id, $segments);
                $campaign->update(['segment_ids' => $segmentIds]);
                $this->line("  [OK] Assigned " . count($segmentIds) . " segment(s) to campaign");
            }

            $this->line("  [OK] Created campaign: {$campaign->name} (ID: {$campaign->id})");
            $this->line("  [OK] Product query: " . $productQueryData['keywords']);
            $this->showCampaignStatus($campaign->fresh(), $engine);
        } else {
            $this->line("  [DRY-RUN] Would create campaign: {$campaignData['name']}");
            $this->line("  [DRY-RUN] Product query keywords: {$productQueryData['keywords']}");
        }

        // ─── 4. Summary ────────────────────────────────────────────
        $this->info("\n=== Setup complete ===");

        if (! $dryRun) {
            $this->line('');
            $this->info('Next steps:');
            $this->line('  The automated pipeline handles the rest:');
            $this->line('  1.  campaigns:source-products  (auto: every 6h) — searches CJ and imports products');
            $this->line('  2.  13 days before campaign start → CJ products are sourced');
            $this->line('  3.  Campaign goes live ' . Carbon::parse($campaignData['starts_at'])->format('M j, Y'));
            $this->line('  4.  campaigns:check-lifecycle  (auto: every 5min) — detects start event');
            $this->line('  5.  SendCampaignLifecycleNotification — sends push/email/WhatsApp to segment-matched customers');
            $this->line('  6.  HomeBuilderService — overrides home sections with sourced products');

            // Show how to trigger manually
            $this->line('');
            $this->info('To trigger sourcing immediately:');
            $this->line('  php artisan campaigns:source-products --campaign=' . $campaign->id);

            $this->info('To dry-run notifications:');
            $this->line('  php artisan campaigns:check-lifecycle --dry-run');

            $this->info('To manually dispatch notifications for testing:');
            $this->line("  php artisan campaign:notify-test {$campaign->id} on_start");
        }

        return 0;
    }

    private function showCampaignStatus(StorefrontCampaign $campaign, SegmentEngine $engine): void
    {
        $this->line('');
        $this->line('Campaign status:');
        $this->line("  Name:        {$campaign->name}");
        $this->line("  Status:      {$campaign->status}");
        $this->line("  Dates:       {$campaign->starts_at?->format('M j')} → {$campaign->ends_at?->format('M j, Y')}");
        $this->line("  Sourcing:    " . ($campaign->sourcingConfig()['enabled'] ? 'enabled' : 'disabled'));

        $segmentIds = $campaign->segmentIds();
        if (! empty($segmentIds)) {
            $segmentNames = CustomerSegment::whereIn('id', $segmentIds)->pluck('name')->implode(', ');
            $this->line("  Segments:    {$segmentNames}");
            foreach ($segmentIds as $sid) {
                $seg = CustomerSegment::find($sid);
                if ($seg) {
                    $this->line("    -> {$seg->name}: {$engine->count($seg)} matching customers");
                }
            }
        } else {
            $this->line("  Segments:    none (sends to all opted-in customers)");
        }

        $notifConfig = $campaign->notificationConfig();
        $this->line('  Notifications:');
        foreach (['on_start', 'on_ending_soon', 'on_end'] as $event) {
            $channels = $notifConfig[$event] ?? [];
            $active = array_keys(array_filter($channels));
            $this->line("    {$event}: " . (empty($active) ? 'none' : implode(', ', $active)));
        }
    }

    private function getSegmentDefinitions(): array
    {
        return [
            [
                'name' => 'French-Speaking CI Customers',
                'slug' => 'french-speaking-ci',
                'description' => 'Customers in Côte d\'Ivoire with French locale',
                'rules' => [
                    'operator' => 'and',
                    'conditions' => [
                        ['field' => 'locale', 'operator' => 'eq', 'value' => 'fr'],
                        ['field' => 'country_code', 'operator' => 'eq', 'value' => 'CI'],
                        ['field' => 'marketing_opt_in', 'operator' => 'eq', 'value' => true],
                    ],
                ],
            ],
            [
                'name' => 'Fashion Enthusiasts (High Spenders)',
                'slug' => 'fashion-enthusiasts',
                'description' => 'Customers who spend $200+ on fashion/accessories',
                'rules' => [
                    'operator' => 'and',
                    'conditions' => [
                        ['field' => 'total_spent', 'operator' => 'gte', 'value' => 200],
                        ['field' => 'marketing_opt_in', 'operator' => 'eq', 'value' => true],
                        ['field' => 'locale', 'operator' => 'in', 'value' => ['fr', 'en']],
                    ],
                ],
            ],
            [
                'name' => 'Recent Active Shoppers',
                'slug' => 'recent-active-shoppers',
                'description' => 'Customers who ordered in the last 60 days',
                'rules' => [
                    'operator' => 'and',
                    'conditions' => [
                        ['field' => 'last_order_days', 'operator' => 'lte', 'value' => 60],
                        ['field' => 'marketing_opt_in', 'operator' => 'eq', 'value' => true],
                    ],
                ],
            ],
        ];
    }

    private function getCampaignData(): array
    {
        return [
            'name' => 'Fashion Week Abidjan 2026',
            'slug' => 'fashion-week-abidjan-2026',
            'type' => 'seasonal',
            'status' => 'approved',
            'is_active' => true,
            'starts_at' => Carbon::parse('2026-10-19 00:00:00', self::TZ),
            'ends_at' => Carbon::parse('2026-10-31 23:59:59', self::TZ),
            'timezone' => self::TZ,
            'priority' => 85,
            'stacking_mode' => 'stackable',
            'placements' => ['home_hero', 'home_carousel', 'home_strip'],
            'hero_kicker' => '👗 Édition Limitée',
            'hero_subtitle' => 'Découvrez notre sélection Fashion Week. Pièces uniques, tendances 2026.',
            'locale_visibility' => ['fr', 'en'],
            'locale_overrides' => [
                [
                    'locale' => 'fr',
                    'name' => 'Fashion Week Abidjan 2026',
                    'hero_subtitle' => 'Découvrez notre sélection Fashion Week. Pièces uniques, tendances 2026.',
                ],
                [
                    'locale' => 'en',
                    'name' => 'Fashion Week Abidjan 2026',
                    'hero_subtitle' => 'Discover our Fashion Week selection. Unique pieces, 2026 trends.',
                ],
            ],
            'sourcing_config' => [
                'enabled' => true,
                'sourcing_days_before' => 13,
                'auto_create_collection' => true,
                'override_home_sections' => ['featured', 'trending'],
            ],
            'notification_config' => [
                'on_start' => ['push' => true, 'email' => true, 'whatsapp' => true],
                'on_ending_soon' => ['push' => true, 'email' => true, 'whatsapp' => false, 'hours_before' => 48],
                'on_end' => ['push' => false, 'email' => true, 'whatsapp' => false],
            ],
        ];
    }

    private function getProductQueryData(): array
    {
        return [
            'keywords' => 'women fashion dress, handbag designer, jewelry set african, scarf winter, clutch bag, earrings gold, sandals heels, skirt midi',
            'cj_category_id' => null,
            'min_price' => 5,
            'max_price' => 150,
            'max_products' => 60,
            'margin_percent' => 65,
            'auto_activate' => true,
            'sort_by' => 'sales',
        ];
    }
}
