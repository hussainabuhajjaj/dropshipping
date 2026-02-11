<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Mail\QueueHealthReportMail;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QueueHealthReportService
{
    private const CACHE_PREFIX = 'queue-health-report';

    public function enabled(): bool
    {
        return (bool) config('services.queue_reporting.enabled', false);
    }

    public function recordProcessed(JobProcessed $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->recordOutcome(
            success: true,
            jobName: $this->resolveJobName($event->job?->resolveName()),
            queueName: $this->resolveQueueName($event->job?->getQueue()),
            error: null
        );
    }

    public function recordFailed(JobFailed $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->recordOutcome(
            success: false,
            jobName: $this->resolveJobName($event->job?->resolveName()),
            queueName: $this->resolveQueueName($event->job?->getQueue()),
            error: $event->exception->getMessage()
        );
    }

    /**
     * Send summary email for the previous completed reporting bucket.
     *
     * @return array<string, mixed>|null
     */
    public function sendPreviousBucketReport(bool $force = false): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $recipients = $this->reportRecipients();
        if ($recipients === []) {
            return null;
        }

        $now = CarbonImmutable::now('UTC');
        $currentBucket = $this->bucketStartAt($now);
        $targetBucket = $currentBucket->subMinutes($this->intervalMinutes());
        $bucketId = $this->bucketId($targetBucket);
        $cacheKey = $this->bucketCacheKey($bucketId);

        $bucket = Cache::get($cacheKey);
        if (! is_array($bucket)) {
            return null;
        }

        if (! $force && ! empty($bucket['email_sent_at'])) {
            return null;
        }

        $report = $this->buildReport($bucket, $targetBucket);
        $sendEmpty = (bool) config('services.queue_reporting.send_empty', false);
        if (! $sendEmpty && ((int) ($report['total_processed'] ?? 0)) === 0) {
            $this->markReportSent($bucketId);
            return null;
        }

        Mail::to($recipients)->send(new QueueHealthReportMail($report));
        $this->markReportSent($bucketId);

        return $report;
    }

    private function recordOutcome(bool $success, string $jobName, string $queueName, ?string $error): void
    {
        $bucketStart = $this->bucketStartAt(CarbonImmutable::now('UTC'));
        $bucketId = $this->bucketId($bucketStart);

        $this->mutateBucket($bucketId, function (array $bucket) use ($success, $jobName, $queueName, $error): array {
            $bucket['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
            $bucket['total_processed'] = (int) ($bucket['total_processed'] ?? 0) + 1;

            if ($success) {
                $bucket['success_count'] = (int) ($bucket['success_count'] ?? 0) + 1;
            } else {
                $bucket['failed_count'] = (int) ($bucket['failed_count'] ?? 0) + 1;
            }

            $queueStats = $bucket['queues'][$queueName] ?? ['success' => 0, 'failed' => 0];
            $queueStats[$success ? 'success' : 'failed']++;
            $bucket['queues'][$queueName] = $queueStats;

            $jobStats = $bucket['jobs'][$jobName] ?? ['success' => 0, 'failed' => 0];
            $jobStats[$success ? 'success' : 'failed']++;
            $bucket['jobs'][$jobName] = $jobStats;

            if (! $success) {
                $bucket['failures'] ??= [];
                $bucket['failures'][] = [
                    'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                    'queue' => $queueName,
                    'job' => $jobName,
                    'error' => $error,
                ];
                $bucket['failures'] = array_slice($bucket['failures'], -25);
            }

            return $bucket;
        });
    }

    private function markReportSent(string $bucketId): void
    {
        $this->mutateBucket($bucketId, function (array $bucket): array {
            $bucket['email_sent_at'] = CarbonImmutable::now('UTC')->toIso8601String();
            return $bucket;
        });
    }

    /**
     * @return array<int, string>
     */
    private function reportRecipients(): array
    {
        $emails = config('services.queue_reporting.emails', []);
        if (! is_array($emails)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($email) => is_string($email) ? trim($email) : '',
            $emails
        )));
    }

    private function intervalMinutes(): int
    {
        return max(1, (int) config('services.queue_reporting.interval_minutes', 10));
    }

    private function bucketStartAt(CarbonImmutable $time): CarbonImmutable
    {
        $interval = $this->intervalMinutes();
        $minute = (int) floor($time->minute / $interval) * $interval;

        return $time->setSecond(0)->setMinute($minute);
    }

    private function bucketId(CarbonImmutable $bucketStart): string
    {
        return $bucketStart->format('YmdHi');
    }

    private function bucketCacheKey(string $bucketId): string
    {
        return self::CACHE_PREFIX . ':' . $bucketId;
    }

    private function bucketLockKey(string $bucketId): string
    {
        return self::CACHE_PREFIX . ':lock:' . $bucketId;
    }

    /**
     * @param callable(array<string, mixed>):array<string, mixed> $mutator
     */
    private function mutateBucket(string $bucketId, callable $mutator): void
    {
        $cacheKey = $this->bucketCacheKey($bucketId);
        $lockKey = $this->bucketLockKey($bucketId);
        $ttl = now()->addHours(48);

        $runner = function () use ($cacheKey, $bucketId, $mutator, $ttl): void {
            $bucket = Cache::get($cacheKey, $this->defaultBucket($bucketId));
            if (! is_array($bucket)) {
                $bucket = $this->defaultBucket($bucketId);
            }

            $mutated = $mutator($bucket);
            Cache::put($cacheKey, $mutated, $ttl);
        };

        try {
            Cache::lock($lockKey, 5)->block(2, $runner);
        } catch (\Throwable $e) {
            Log::warning('Queue report lock failed; writing without lock', [
                'bucket' => $bucketId,
                'error' => $e->getMessage(),
            ]);
            $runner();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultBucket(string $bucketId): array
    {
        $start = CarbonImmutable::createFromFormat('YmdHi', $bucketId, 'UTC');
        $end = $start->addMinutes($this->intervalMinutes());

        return [
            'bucket_id' => $bucketId,
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
            'total_processed' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'queues' => [],
            'jobs' => [],
            'failures' => [],
            'updated_at' => null,
            'email_sent_at' => null,
        ];
    }

    /**
     * @param array<string, mixed> $bucket
     * @return array<string, mixed>
     */
    private function buildReport(array $bucket, CarbonImmutable $bucketStart): array
    {
        $success = (int) ($bucket['success_count'] ?? 0);
        $failed = (int) ($bucket['failed_count'] ?? 0);
        $total = (int) ($bucket['total_processed'] ?? ($success + $failed));
        $totalForRate = max(1, $total);

        $failureRate = round(($failed / $totalForRate) * 100, 2);
        $successRate = round(($success / $totalForRate) * 100, 2);

        $queues = is_array($bucket['queues'] ?? null) ? $bucket['queues'] : [];
        $jobs = is_array($bucket['jobs'] ?? null) ? $bucket['jobs'] : [];
        $failures = is_array($bucket['failures'] ?? null) ? $bucket['failures'] : [];

        $topFailedJobs = collect($jobs)
            ->map(function ($stats, $job): array {
                $failed = (int) ($stats['failed'] ?? 0);
                $success = (int) ($stats['success'] ?? 0);
                return [
                    'job' => (string) $job,
                    'failed' => $failed,
                    'success' => $success,
                    'total' => $failed + $success,
                ];
            })
            ->filter(static fn (array $item): bool => $item['failed'] > 0)
            ->sortByDesc('failed')
            ->take(5)
            ->values()
            ->all();

        $topQueues = collect($queues)
            ->map(function ($stats, $queue): array {
                $failed = (int) ($stats['failed'] ?? 0);
                $success = (int) ($stats['success'] ?? 0);
                return [
                    'queue' => (string) $queue,
                    'failed' => $failed,
                    'success' => $success,
                    'total' => $failed + $success,
                ];
            })
            ->sortByDesc('total')
            ->take(5)
            ->values()
            ->all();

        $analysis = [];
        if ($failed === 0) {
            $analysis[] = 'No failed jobs recorded in this interval.';
        } elseif ($failureRate > 10) {
            $analysis[] = "Failure rate is high at {$failureRate}%.";
        } elseif ($failureRate > 3) {
            $analysis[] = "Failure rate is elevated at {$failureRate}%.";
        } else {
            $analysis[] = "Failure rate is controlled at {$failureRate}%.";
        }

        if ($topQueues !== []) {
            $busiest = $topQueues[0];
            $analysis[] = "Busiest queue: {$busiest['queue']} ({$busiest['total']} jobs).";
        }

        if ($topFailedJobs !== []) {
            $topFailed = $topFailedJobs[0];
            $analysis[] = "Top failing job: {$topFailed['job']} ({$topFailed['failed']} failures).";
        }

        return [
            'bucket_id' => (string) ($bucket['bucket_id'] ?? $this->bucketId($bucketStart)),
            'starts_at' => (string) ($bucket['starts_at'] ?? $bucketStart->toIso8601String()),
            'ends_at' => (string) ($bucket['ends_at'] ?? $bucketStart->addMinutes($this->intervalMinutes())->toIso8601String()),
            'period_label' => $bucketStart->format('Y-m-d H:i') . ' - ' . $bucketStart->addMinutes($this->intervalMinutes())->format('H:i') . ' UTC',
            'total_processed' => $total,
            'success_count' => $success,
            'failed_count' => $failed,
            'success_rate' => $successRate,
            'failure_rate' => $failureRate,
            'top_queues' => $topQueues,
            'top_failed_jobs' => $topFailedJobs,
            'recent_failures' => array_slice($failures, -10),
            'analysis' => $analysis,
        ];
    }

    private function resolveQueueName(?string $queue): string
    {
        $queue = trim((string) $queue);
        return $queue !== '' ? $queue : 'default';
    }

    private function resolveJobName(?string $job): string
    {
        $job = trim((string) $job);
        return $job !== '' ? $job : 'unknown';
    }
}

