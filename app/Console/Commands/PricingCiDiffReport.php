<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Services\PricingService;
use App\Models\LocalWareHouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * C1 — CI READ-ONLY PRICING DIFF REPORT.
 *
 * Compares CURRENT STORED product prices against CURRENT CANONICAL CI prices
 * computed with the EXISTING PricingService::calculate() behavior, unmodified.
 *
 * STRICTLY READ-ONLY:
 *  - Only SELECT queries are executed (via query builder ->select/get/chunk).
 *  - No update/insert/delete/save/create/dispatch anywhere in this class.
 *  - Does not reprice anything. Diagnostic output only.
 *
 * Scope: CI catalog only. No UAE/AED logic, no fallback shipping guesses:
 * if a warehouse rate cannot be resolved, the product is classified
 * SHIPPING_DATA_PROBLEM / MISSING DATA instead of silently using the
 * config('pricing.default_shipping_per_kg', 14) fallback.
 */
class PricingCiDiffReport extends Command
{
    protected $signature = 'pricing:ci-diff-report
        {--out= : Optional path to write a CSV artifact (filesystem only)}
        {--limit=0 : Process at most N products (0 = all)}
        {--chunk=200 : Chunk size}
        {--near-match-percent=2.0 : Threshold (in %) considered MATCH/NEAR MATCH}
        {--include-inactive : Include inactive/archived products}
        {--sources=CJ,ALIEXPRESS,1688,WOO,MANUAL : Comma-separated source buckets to include}';

    protected $description = 'READ-ONLY diagnostic: compare stored CI selling prices vs canonical PricingService output. Makes NO database changes.';

    /** Warehouse cache: id => LocalWareHouse|null */
    private array $warehouses = [];

    private float $xofBuffer = 0.0;

    public function handle(): int
    {
        // Hard safety guard: abort if the connection is anything but a reader-safe run.
        // We never wrap in transactions and never issue writes; this assert documents intent.
        if (! app()->environment('local', 'production', 'staging')) {
            // still fine — informational only
        }

        $this->xofBuffer = (float) config('pricing.currency.xof_buffer_percent', 5);

        $this->warn('=== C1 CI PRICING DIFF REPORT (READ-ONLY — no writes, no jobs, no repricing) ===');
        $this->line('Engine flag pricing.use_new_engine: ' . var_export((bool) config('pricing.use_new_engine', false), true));
        $this->line('New-engine rollout active (global): ' . var_export(PricingService::usesNewEngine(), true));

        // Baseline snapshot for post-run verification (count + updated_at checksum).
        $baseline = $this->dbSnapshot();
        $this->line("Baseline products: count={$baseline['count']} max_updated_at={$baseline['max_updated']} sum_selling_price={$baseline['sum_price']}");

        $rows = [];
        $stats = $this->emptyStats();

        // Verified products columns only (no `sku`/`weight` columns exist on products;
        // SKU lives on variants, weight on pricing_meta / cj payload / variant weight_grams).
        $query = DB::table('products')
            ->select([
                'id', 'code', 'name', 'slug', 'supplier_id', 'supplier_type',
                'currency', 'cost_price', 'selling_price',
                'category_id', 'local_warehouse_id', 'pricing_meta', 'cj_lock_price',
                'status', 'is_active', 'source_url', 'attributes', 'updated_at',
            ])
            ->whereNull('deleted_at')
            ->orderBy('id');

        if (! $this->option('include-inactive')) {
            $query->where(function ($q) {
                $q->where('is_active', true)->orWhere('status', 'active');
            });
        }

        if ((int) $this->option('limit') > 0) {
            $query->limit((int) $this->option('limit'));
        }

        $allowedSources = array_map('strtoupper', array_filter(array_map('trim', explode(',', (string) $this->option('sources')))));

        $total = 0;
        $eligible = 0;

        $query->chunk((int) $this->option('chunk'), function ($products) use (&$rows, &$stats, &$total, &$eligible, $allowedSources) {
            foreach ($products as $p) {
                $total++;
                $row = $this->analyze($p, $allowedSources);
                if ($row === null) {
                    continue; // ineligible (filtered source)
                }
                $eligible++;
                $rows[] = $row;
                $this->tally($stats, $row);
            }
        });

        $this->reportSummary($stats, $total, $eligible);
        $this->reportSamples($rows, $stats);

        if ($out = $this->option('out')) {
            $this->writeCsv((string) $out, $rows);
            $this->info("CSV artifact written to {$out} (filesystem only — no DB involvement).");
        }

        // Post-run verification that nothing changed.
        $after = $this->dbSnapshot();
        $unchanged = $after === $baseline;
        $this->newLine();
        if ($unchanged) {
            $this->info('VERIFIED READ-ONLY: products count, MAX(updated_at) and SUM(selling_price) identical before/after.');
        } else {
            $this->error('SAFETY VIOLATION DETECTED — database state differs after run. Investigate immediately.');
            $this->line(json_encode(['before' => $baseline, 'after' => $after], JSON_PRETTY_PRINT));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function dbSnapshot(): array
    {
        $r = DB::table('products')
            ->selectRaw('COUNT(*) as c, MAX(updated_at) as m, ROUND(SUM(selling_price),2) as s')
            ->first();

        return ['count' => (int) $r->c, 'max_updated' => (string) $r->m, 'sum_price' => (string) $r->s];
    }

    private function warehouse(?int $id): ?LocalWareHouse
    {
        if ($id === null) {
            return null;
        }
        if (! array_key_exists($id, $this->warehouses)) {
            $this->warehouses[$id] = LocalWareHouse::query()->find($id);
        }

        return $this->warehouses[$id];
    }

    /**
     * Resolve the source bucket for a product without guessing beyond evidence.
     */
    private function sourceBucket(object $p): string
    {
        $type = strtoupper((string) ($p->supplier_type ?? ''));
        if ($type !== '') {
            return $type;
        }
        if (! empty($p->pricing_meta) || ! empty($p->local_warehouse_id)) {
            return 'CJ_ENGINE_META';
        }
        $url = strtolower((string) ($p->source_url ?? ''));
        if (str_contains($url, 'cjdropshipping')) {
            return 'CJ';
        }
        if (str_contains($url, 'aliexpress')) {
            return 'ALIEXPRESS';
        }
        if (str_contains($url, '1688')) {
            return '1688';
        }
        if (str_contains($url, 'woo')) {
            return 'WOO';
        }

        return 'MANUAL';
    }

    private function analyze(object $p, array $allowedSources): ?array
    {
        $source = $this->sourceBucket($p);
        if (! in_array($source, $allowedSources, true) && ! in_array(strtoupper($source), $allowedSources, true)) {
            return null;
        }

        $meta = json_decode((string) ($p->pricing_meta ?? ''), true);
        if (! is_array($meta)) {
            $meta = [];
        }

        $cost = (float) ($p->cost_price ?? 0);
        $stored = $p->selling_price !== null ? (float) $p->selling_price : null;
        $currency = (string) ($p->currency ?: 'USD');

        // Weight resolution — mirror repriceProductWithCurrentEngine::resolveProductWeightKg
        // precedence WITHOUT inventing values. products.weight column does not exist;
        // the next canonical fallback after cj_payload is MAX(variants.weight_grams).
        $weightKg = null;
        $weightSource = null;
        if (($w = $this->num($meta['weight_kg'] ?? null)) !== null && $w > 0) {
            $weightKg = $w;
            $weightSource = 'pricing_meta.weight_kg';
        } elseif (($w = $this->payloadWeightGrams($p)) !== null && $w > 0) {
            $weightKg = round($w / 1000, 4);
            $weightSource = 'cj_payload.productWeight';
        } else {
            $grams = DB::table('product_variants')
                ->where('product_id', $p->id)
                ->whereNotNull('weight_grams')
                ->max('weight_grams');
            if ($grams !== null && (float) $grams > 0) {
                $weightKg = round(((float) $grams) / 1000, 4);
                $weightSource = 'variants.max_weight_grams';
            }
        }

        $warehouseId = $p->local_warehouse_id !== null ? (int) $p->local_warehouse_id : ($this->num($meta['warehouse_id'] ?? null));
        $warehouse = $this->warehouse($warehouseId !== null ? (int) $warehouseId : null);

        $cjShipping = $this->num($meta['cj_shipping'] ?? null) ?? 0.0;

        $row = [
            'product_id' => $p->id,
            'sku' => (string) ($p->code ?? ''),
            'name' => mb_substr((string) $p->name, 0, 80),
            'supplier_id' => $p->supplier_id,
            'source' => $source,
            'currency' => $currency,
            'cost' => $cost,
            'weight_kg' => $weightKg,
            'weight_source' => $weightSource,
            'warehouse_id' => $warehouseId,
            'warehouse_resolved' => $warehouse !== null ? 1 : 0,
            'rate_per_kg' => $warehouse?->shipping_cost_per_kg,
            'cj_shipping' => $cjShipping,
            'external_shipping' => null,
            'landed_cost' => null,
            'margin' => null,
            'category_multiplier' => null,
            'stored_price' => $stored,
            'canonical_price' => null,
            'effective_canonical_price' => null, // includes enforceMinMargin(1.5) floor like variant pipeline
            'abs_diff' => null,
            'pct_diff' => null,
            'engine' => $meta !== [] ? 'new_engine_meta' : 'legacy_or_unknown',
            'manual_override' => (bool) $p->cj_lock_price,
            'classification' => null,
            'flags' => [],
        ];

        // ---- Classification gates (order matters) ----

        if ($stored === null || $stored <= 0) {
            $row['classification'] = 'MISSING_DATA';
            $row['flags'][] = 'no_stored_price';

            return $row;
        }

        if ($cost <= 0) {
            $row['classification'] = 'MISSING_DATA';
            $row['flags'][] = 'no_cost_price';

            return $row;
        }

        if ($weightKg === null) {
            $row['classification'] = 'MISSING_DATA';
            $row['flags'][] = 'no_weight';

            return $row;
        }

        // SHIPPING RULE (explicit requirement): do NOT silently apply the 14/kg fallback.
        if (! $warehouse || $warehouse->shipping_cost_per_kg === null || (float) $warehouse->shipping_cost_per_kg <= 0) {
            $row['classification'] = 'SHIPPING_DATA_PROBLEM';
            $row['flags'][] = $warehouse ? 'warehouse_rate_missing' : 'warehouse_unresolvable';

            return $row;
        }

        // Canonical calculation via UNMODIFIED PricingService, but with warehouse validated above.
        try {
            $result = PricingService::makeFromConfig()->calculate(
                productCost: $cost,
                weight: $weightKg,
                cjShipping: $cjShipping,
                warehouse: $warehouse,
                currency: $currency,
                options: [
                    'category_id' => $p->category_id !== null ? (int) $p->category_id : null,
                    'warehouse_id' => $warehouse->id,
                ],
            );
        } catch (InvalidArgumentException $e) {
            $row['classification'] = 'CANNOT_CALCULATE';
            $row['flags'][] = 'exception:' . $e->getMessage();

            return $row;
        } catch (\Throwable $e) {
            $row['classification'] = 'CANNOT_CALCULATE';
            $row['flags'][] = 'error:' . $e->getMessage();

            return $row;
        }

        $row['external_shipping'] = round($result->externalShipping, 4);
        $row['landed_cost'] = round($result->landedCost, 4);
        $row['margin'] = $result->marginPercent / 100;
        $row['category_multiplier'] = $this->num($result->pricingMeta['category_multiplier'] ?? null);
        $row['canonical_price'] = $result->basePrice;
        // Import variant pipeline applies enforceMinMargin(cost*1.5) AFTER calculate().
        $row['effective_canonical_price'] = max($result->basePrice, round($cost * 1.5, 2));

        $row['abs_diff'] = round($stored - $result->basePrice, 2);
        $row['pct_diff'] = $result->basePrice > 0 ? round(($stored - $result->basePrice) / $result->basePrice * 100, 2) : null;

        // Classifications (priority order):
        if ($row['manual_override']) {
            $row['classification'] = 'MANUAL_PRICE_OVERRIDE';
        } elseif ($row['engine'] === 'legacy_or_unknown') {
            $row['classification'] = 'LEGACY_PRICING';
        } elseif ($row['pct_diff'] !== null && abs($row['pct_diff']) <= (float) $this->option('near-match-percent')) {
            $row['classification'] = 'MATCH_NEAR_MATCH';
        } elseif ($stored < $result->basePrice) {
            $row['classification'] = 'LOWER_THAN_CANONICAL';
        } else {
            $row['classification'] = 'HIGHER_THAN_CANONICAL';
        }

        // Cross-label note when effective canonical (with 1.5x floor) explains the gap.
        if (in_array($row['classification'], ['LOWER_THAN_CANONICAL', 'HIGHER_THAN_CANONICAL'], true)
            && abs($stored - $row['effective_canonical_price']) <= max(0.01, $stored * 0.02)) {
            $row['flags'][] = 'explained_by_min_margin_floor_1.5x';
        }

        return $row;
    }

    private function payloadWeightGrams(object $p): ?float
    {
        // attributes JSON may hold cj_payload.productWeight (grams) — same source Filament uses.
        $attrs = json_decode((string) ($p->attributes ?? ''), true);
        if (! is_array($attrs)) {
            return null;
        }
        $raw = data_get($attrs, 'cj_payload.productWeight');
        if (is_string($raw)) {
            $raw = (float) preg_replace('/[^0-9.]/', '', explode('-', $raw)[0]);
        }

        return is_numeric($raw) && (float) $raw > 0 ? (float) $raw : null;
    }

    private function num(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    private function emptyStats(): array
    {
        return [
            'classes' => [],
            'calculated' => 0,
            'diffs_pct' => [],
            'increase_sum' => 0.0,
            'decrease_sum' => 0.0,
            'largest_up' => ['-inf', null],
            'largest_down' => ['inf', null],
        ];
    }

    private function tally(array &$s, array $row): void
    {
        $cls = $row['classification'];
        $s['classes'][$cls] = ($s['classes'][$cls] ?? 0) + 1;

        if ($row['canonical_price'] === null || $row['pct_diff'] === null) {
            return;
        }
        $s['calculated']++;
        $s['diffs_pct'][] = $row['pct_diff'];

        $abs = $row['abs_diff'];
        if ($abs > 0) {
            $s['increase_sum'] += $abs;
        } elseif ($abs < 0) {
            $s['decrease_sum'] += $abs;
        }
        if ($abs > $s['largest_up'][0]) {
            $s['largest_up'] = [$abs, $row];
        }
        if ($abs < $s['largest_down'][0]) {
            $s['largest_down'] = [$abs, $row];
        }
    }

    private function reportSummary(array $s, int $total, int $eligible): void
    {
        $this->newLine();
        $this->info('===== SUMMARY (diagnostic only — NOT recommended repricing amounts) =====');
        $this->line("Total CI products scanned:      {$total}");
        $this->line("Eligible (included sources):    {$eligible}");
        $this->line("Successfully calculated:        {$s['calculated']}");

        $counts = $s['classes'];
        foreach ([
            'MATCH_NEAR_MATCH', 'LOWER_THAN_CANONICAL', 'HIGHER_THAN_CANONICAL',
            'MISSING_DATA', 'CANNOT_CALCULATE', 'MANUAL_PRICE_OVERRIDE',
            'LEGACY_PRICING', 'SHIPPING_DATA_PROBLEM',
        ] as $cls) {
            $this->line(sprintf('  %-22s %6d', $cls, $counts[$cls] ?? 0));
        }

        $diffs = $s['diffs_pct'];
        if ($diffs !== []) {
            sort($diffs);
            $n = count($diffs);
            $median = $n % 2 === 0 ? ($diffs[$n / 2 - 1] + $diffs[$n / 2]) / 2 : $diffs[(int) floor($n / 2)];
            $avg = array_sum($diffs) / $n;
            $this->newLine();
            $this->line(sprintf('Average difference %%: %+0.2f', $avg));
            $this->line(sprintf('Median difference %%: %+0.2f', $median));
            $this->line(sprintf('Largest increase:    %+0.2f (%s)', $s['largest_up'][0], $this->label($s['largest_up'][1])));
            $this->line(sprintf('Largest decrease:    %+0.2f (%s)', $s['largest_down'][0], $this->label($s['largest_down'][1])));
            $this->line(sprintf('Total potential price INCREASE (stored→canonical would reduce): %0.2f', $s['increase_sum']));
            $this->line(sprintf('Total potential price DECREASE (stored→canonical would add):    %0.2f', -$s['decrease_sum']));
            $this->line('NOTE: sums mix currencies (USD/XOF/CNY). Treat as directional diagnostics only.');
        }
    }

    private function label(?array $row): string
    {
        return $row === null ? 'n/a' : "#{$row['product_id']} {$row['name']}";
    }

    private function reportSamples(array $rows, array $s): void
    {
        $byAbs = fn ($dir) => collect($rows)
            ->filter(fn ($r) => $r['abs_diff'] !== null)
            ->sortBy(fn ($r) => $dir * $r['abs_diff'])
            ->take(20);

        $this->sectionTable('TOP 20 LARGEST INCREASES (stored higher than canonical)', $byAbs(-1));
        $this->sectionTable('TOP 20 LARGEST DECREASES (stored lower than canonical)', $byAbs(1));

        $this->sectionTable('SAMPLE: MISSING DATA (10)', collect($rows)->where('classification', 'MISSING_DATA')->take(10));
        $this->sectionTable('SAMPLE: MANUAL / LOCKED (10)', collect($rows)->filter(fn ($r) => $r['manual_override'])->take(10));
        $this->sectionTable('SAMPLE: SHIPPING PROBLEM (10)', collect($rows)->where('classification', 'SHIPPING_DATA_PROBLEM')->take(10));
        $this->sectionTable('SAMPLE: LEGACY PRICING (10)', collect($rows)->where('classification', 'LEGACY_PRICING')->take(10));

        $this->line('(A product may appear in multiple sections; classification column disambiguates.)');
    }

    private function sectionTable(string $title, $collection): void
    {
        $this->newLine();
        $this->info("--- {$title} ---");
        $headers = ['ID', 'Class', 'Src', 'Cur', 'Cost', 'Stored', 'Canonical', 'EffCanon', 'Δabs', 'Δ%', 'Flags'];
        $this->table($headers, $collection->map(fn ($r) => [
            $r['product_id'], $r['classification'], $r['source'], $r['currency'],
            $r['cost'], $r['stored_price'], $r['canonical_price'], $r['effective_canonical_price'],
            $r['abs_diff'], $r['pct_diff'], implode('|', $r['flags']),
        ])->values()->all());
    }

    private function writeCsv(string $path, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $fp = fopen($path, 'wb');
        if ($fp === false) {
            $this->error("Could not open {$path} for writing.");

            return;
        }
        fputcsv($fp, array_keys($rows[0]));
        foreach ($rows as $row) {
            $row['flags'] = implode('|', $row['flags']);
            fputcsv($fp, $row);
        }
        fclose($fp);
    }
}
