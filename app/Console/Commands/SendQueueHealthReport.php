<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Queue\QueueHealthReportService;
use Illuminate\Console\Command;

class SendQueueHealthReport extends Command
{
    protected $signature = 'queue:report-email {--force : Send even if the interval was already reported}';

    protected $description = 'Send queue health report email with success/failure counts and analysis.';

    public function handle(QueueHealthReportService $service): int
    {
        if (! $service->enabled()) {
            $this->warn('Queue reporting is disabled. Set QUEUE_REPORTING_ENABLED=true.');
            return self::SUCCESS;
        }

        $report = $service->sendPreviousBucketReport((bool) $this->option('force'));

        if (! is_array($report)) {
            $this->info('No queue report email sent (no data, no recipients, or already sent).');
            return self::SUCCESS;
        }

        $this->info('Queue report email sent.');
        $this->table(['Metric', 'Value'], [
            ['Period', (string) ($report['period_label'] ?? 'N/A')],
            ['Total processed', (string) ($report['total_processed'] ?? 0)],
            ['Success', (string) ($report['success_count'] ?? 0)],
            ['Failed', (string) ($report['failed_count'] ?? 0)],
            ['Success rate', (string) (($report['success_rate'] ?? 0) . '%')],
            ['Failure rate', (string) (($report['failure_rate'] ?? 0) . '%')],
        ]);

        return self::SUCCESS;
    }
}

