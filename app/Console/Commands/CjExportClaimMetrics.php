<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;

class CjExportClaimMetrics extends Command
{
    protected $signature = 'cj:export-claim-metrics {--pushgateway= : Pushgateway URL} {--job= : Job name for pushgateway} {--pattern= : Redis key pattern (default cj:processing:*)} {--dry-run : only print metrics}';

    protected $description = 'Count CJ claim keys and optionally push a metric to Prometheus Pushgateway';

    public function handle(): int
    {
        $pattern = $this->option('pattern') ?? 'cj:processing:*';
        $pushgateway = $this->option('pushgateway') ?? env('PUSHGATEWAY_URL');
        $job = $this->option('job') ?? env('PUSHGATEWAY_JOB', 'cj_claims');
        $dry = (bool) $this->option('dry-run');

        $this->line("Scanning Redis for pattern: $pattern");

        $cursor = 0;
        $count = 0;

        do {
            $result = Redis::scan($cursor, 'MATCH', $pattern, 'COUNT', 1000);
            if (!is_array($result)) {
                break;
            }

            if (array_key_exists(0, $result) && array_key_exists(1, $result) && is_array($result[1])) {
                $cursor = (int)$result[0];
                $keys = $result[1];
            } else {
                [$cursor, $keys] = $result;
            }

            foreach ($keys as $key) {
                if (!is_string($key) || $key === '') {
                    continue;
                }

                // skip owner set keys
                if (strpos($key, 'owner:') !== false) {
                    continue;
                }

                $count++;
            }
        } while ($cursor != 0);

        $this->info("CJ claim keys count: $count");

        if ($dry || ! $pushgateway) {
            if (! $pushgateway) {
                $this->line('Pushgateway URL not configured; run with --pushgateway or set PUSHGATEWAY_URL');
            }
            return Command::SUCCESS;
        }

        // Build simple prometheus metric
        $metric = "# HELP cj_claims_total Number of CJ claim keys\n# TYPE cj_claims_total gauge\ncj_claims_total $count\n";

        try {
            $url = rtrim($pushgateway, '/') . '/metrics/job/' . urlencode($job);
            $resp = Http::withHeaders(['Content-Type' => 'text/plain; version=0.0.4'])->post($url, $metric);
            if ($resp->successful()) {
                $this->info('Pushed metric to Pushgateway');
                return Command::SUCCESS;
            }

            $this->error('Failed to push to Pushgateway: ' . $resp->body());
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Error pushing to Pushgateway: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
