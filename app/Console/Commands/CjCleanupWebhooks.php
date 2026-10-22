<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CjCleanupWebhooks extends Command
{
    protected $signature = 'cj:webhooks-cleanup {--dry-run}';
    protected $description = 'Deduplicate CJ webhook logs by message_id and optionally remove duplicates (keeps earliest record).';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        $duplicates = DB::table('cj_webhook_logs')
            ->selectRaw('message_id, COUNT(*) as cnt, MIN(id) as keep_id')
            ->whereNotNull('message_id')
            ->groupBy('message_id')
            ->having('cnt', '>', 1)
            ->get();

        $totalGroups = $duplicates->count();
        $totalRows = 0;

        foreach ($duplicates as $row) {
            $ids = DB::table('cj_webhook_logs')
                ->where('message_id', $row->message_id)
                ->orderBy('id')
                ->pluck('id')
                ->toArray();

            $toDelete = array_filter($ids, fn($id) => $id != $row->keep_id);
            $totalRows += count($toDelete);

            if ($dry) {
                $this->line("Would delete " . count($toDelete) . " rows for message_id={$row->message_id}");
            } else {
                if (! empty($toDelete)) {
                    DB::table('cj_webhook_logs')->whereIn('id', $toDelete)->delete();
                    $this->info("Deleted " . count($toDelete) . " rows for message_id={$row->message_id}");
                }
            }
        }

        $this->line("Found {$totalGroups} duplicate message_id groups, {$totalRows} rows affected.");

        if ($dry) {
            $this->comment('Dry run complete. Re-run without --dry-run to perform deletions.');
        } else {
            $this->info('Cleanup complete. Consider running migrations to add unique constraint (if not already applied).');
        }

        return 0;
    }
}
