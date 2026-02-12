<?php

declare(strict_types=1);

namespace App\Services\Cj;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CjCatalogImportTracker
{
    private const RUN_PREFIX = 'cj_catalog:import_run:';
    private const RUN_LOCK_PREFIX = 'cj_catalog:import_run_lock:';
    private const ACTIVE_RUN_PREFIX = 'cj_catalog:import_active:';

    /**
     * @param array<int, string> $pids
     */
    public function start(int $userId, array $pids, string $context): string
    {
        $trackingKey = (string) Str::uuid();
        $run = [
            'id' => $trackingKey,
            'user_id' => $userId,
            'context' => $context,
            'status' => 'queued',
            'total' => count($pids),
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'failed_pids' => [],
            'started_at' => Carbon::now()->toDateTimeString(),
            'finished_at' => null,
            'updated_at' => Carbon::now()->toDateTimeString(),
        ];

        Cache::put($this->runKey($trackingKey), $run, now()->addDays(2));
        Cache::put($this->activeRunKey($userId), $trackingKey, now()->addDays(2));

        return $trackingKey;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $trackingKey): ?array
    {
        $run = Cache::get($this->runKey($trackingKey));

        return is_array($run) ? $run : null;
    }

    public function getActiveKey(int $userId): ?string
    {
        $value = Cache::get($this->activeRunKey($userId));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function markSuccess(string $trackingKey, string $pid): void
    {
        $this->mutate($trackingKey, function (array $run): array {
            $run['status'] = 'running';
            $run['processed'] = (int) ($run['processed'] ?? 0) + 1;
            $run['success'] = (int) ($run['success'] ?? 0) + 1;

            return $run;
        });
    }

    public function markFailure(string $trackingKey, string $pid): void
    {
        $this->mutate($trackingKey, function (array $run) use ($pid): array {
            $run['status'] = 'running';
            $run['processed'] = (int) ($run['processed'] ?? 0) + 1;
            $run['failed'] = (int) ($run['failed'] ?? 0) + 1;

            $failedPids = is_array($run['failed_pids'] ?? null) ? $run['failed_pids'] : [];
            if ($pid !== '' && ! in_array($pid, $failedPids, true)) {
                $failedPids[] = $pid;
            }
            $run['failed_pids'] = array_values($failedPids);

            return $run;
        });
    }

    /**
     * @param callable(array<string, mixed>):array<string, mixed> $mutator
     */
    private function mutate(string $trackingKey, callable $mutator): void
    {
        $runKey = $this->runKey($trackingKey);
        $lockKey = $this->runLockKey($trackingKey);

        $runner = function () use ($runKey, $mutator): void {
            $run = Cache::get($runKey);
            if (! is_array($run)) {
                return;
            }

            $mutated = $mutator($run);
            $mutated['updated_at'] = Carbon::now()->toDateTimeString();

            $total = (int) ($mutated['total'] ?? 0);
            $processed = (int) ($mutated['processed'] ?? 0);
            if ($total > 0 && $processed >= $total) {
                $mutated['status'] = ((int) ($mutated['failed'] ?? 0) > 0)
                    ? 'completed_with_failures'
                    : 'completed';
                $mutated['finished_at'] = Carbon::now()->toDateTimeString();
            }

            Cache::put($runKey, $mutated, now()->addDays(2));
        };

        try {
            Cache::lock($lockKey, 5)->block(2, $runner);
        } catch (\Throwable $e) {
            Log::warning('CJ catalog import tracker lock failed; continuing without lock', [
                'tracking_key' => $trackingKey,
                'error' => $e->getMessage(),
            ]);
            $runner();
        }
    }

    private function runKey(string $trackingKey): string
    {
        return self::RUN_PREFIX . $trackingKey;
    }

    private function runLockKey(string $trackingKey): string
    {
        return self::RUN_LOCK_PREFIX . $trackingKey;
    }

    private function activeRunKey(int $userId): string
    {
        return self::ACTIVE_RUN_PREFIX . $userId;
    }
}

