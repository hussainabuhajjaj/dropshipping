<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Fulfillment\Clients\CJ\CjAlertService;
use App\Models\Product;
use Illuminate\Console\Command;

class AutoHideStaleCjProducts extends Command
{
    protected $signature = 'products:auto-hide-stale-cj
        {--stale-hours=48 : Mark products stale after this many hours}
        {--chunk=500 : Chunk size}
        {--dry-run : Only report what would be hidden}
        {--notify-always : Send email alert even when no stale products are found}';

    protected $description = 'Auto-hide stale CJ products (not synced recently) and send summary alerts.';

    public function handle(): int
    {
        $staleHours = max(1, (int) $this->option('stale-hours'));
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $notifyAlways = (bool) $this->option('notify-always');
        $cutoff = now()->subHours($staleHours);

        $query = Product::query()
            ->whereNotNull('cj_pid')
            ->where('cj_sync_enabled', true)
            ->where('is_active', true)
            ->where(function ($inner) use ($cutoff): void {
                $inner->whereNull('cj_synced_at')
                    ->orWhere('cj_synced_at', '<', $cutoff);
            })
            ->orderBy('id');

        $totalCandidates = (clone $query)->count();
        if ($totalCandidates === 0) {
            $this->info('No stale active CJ products found.');

            if ($notifyAlways) {
                CjAlertService::alert('Stale CJ products scan: no stale products', [
                    'stale_hours' => $staleHours,
                    'cutoff' => $cutoff->toDateTimeString(),
                    'candidates' => 0,
                    'hidden' => 0,
                    'dry_run' => $dryRun,
                ]);
            }

            return self::SUCCESS;
        }

        $this->info("Found {$totalCandidates} stale active CJ products.");

        $processed = 0;
        $hidden = 0;
        $sample = [];

        $query->chunkById($chunk, function ($rows) use (&$processed, &$hidden, &$sample, $dryRun): void {
            $ids = $rows->pluck('id')->all();
            $processed += count($ids);

            foreach ($rows as $row) {
                if (count($sample) >= 20) {
                    break;
                }

                $sample[] = [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'cj_pid' => (string) $row->cj_pid,
                    'cj_synced_at' => optional($row->cj_synced_at)->toDateTimeString(),
                ];
            }

            if ($dryRun) {
                $hidden += count($ids);
                return;
            }

            $hidden += Product::query()
                ->whereIn('id', $ids)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        });

        $mode = $dryRun ? 'DRY RUN' : 'APPLIED';
        $this->table(['Metric', 'Value'], [
            ['Mode', $mode],
            ['Stale hours', (string) $staleHours],
            ['Processed', (string) $processed],
            ['Hidden', (string) $hidden],
        ]);

        if ($hidden > 0 || $notifyAlways) {
            CjAlertService::alert(
                $dryRun ? 'Stale CJ products dry-run report' : 'Stale CJ products auto-hidden',
                [
                    'stale_hours' => $staleHours,
                    'cutoff' => $cutoff->toDateTimeString(),
                    'processed' => $processed,
                    'hidden' => $hidden,
                    'dry_run' => $dryRun,
                    'sample_products' => $sample,
                ]
            );
        }

        return self::SUCCESS;
    }
}

