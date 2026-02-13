<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Support\Models\SupportConversation;
use App\Models\User;
use App\Notifications\Support\SupportEscalationDigestNotification;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

class SendSupportEscalationDigest extends Command
{
    protected $signature = 'support:escalation-digest
        {--sla-minutes= : Override SLA wait threshold in minutes}
        {--limit= : Override number of top overdue rows to include}
        {--dry-run : Analyze and print without sending notifications}
        {--force : Send even when overdue count is zero}';

    protected $description = 'Send support escalation digest email/database notifications to admins and staff.';

    public function handle(): int
    {
        $enabled = (bool) config('support_chat.digest.enabled', true);
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $enabled && ! $dryRun) {
            $this->warn('Support escalation digest is disabled (SUPPORT_CHAT_DIGEST_ENABLED=false).');

            return self::SUCCESS;
        }

        $slaMinutes = max(1, (int) ($this->option('sla-minutes') ?? config('support_chat.escalation.sla_minutes', 15)));
        $topRows = max(1, (int) ($this->option('limit') ?? config('support_chat.digest.max_rows', 10)));
        $threshold = now()->subMinutes($slaMinutes);

        $overdueQuery = SupportConversation::query()->overdueSla($threshold);

        $overdueCount = (clone $overdueQuery)->count();
        $unassignedOverdueCount = (clone $overdueQuery)->whereNull('assigned_user_id')->count();
        $assignedOverdueCount = max(0, $overdueCount - $unassignedOverdueCount);

        $items = (clone $overdueQuery)
            ->with(['customer:id,first_name,last_name,email'])
            ->orderByRaw('COALESCE(last_customer_message_at, last_message_at, created_at) asc')
            ->limit($topRows)
            ->get()
            ->map(function (SupportConversation $conversation): array {
                $customer = $conversation->customer;

                return [
                    'conversation_id' => $conversation->id,
                    'wait_minutes' => $this->waitMinutes($conversation),
                    'status' => (string) $conversation->status,
                    'assigned_user_id' => $conversation->assigned_user_id ? (int) $conversation->assigned_user_id : null,
                    'customer' => $customer
                        ? trim((string) ($customer->first_name . ' ' . $customer->last_name)) . ' <' . $customer->email . '>'
                        : 'Guest',
                ];
            })
            ->values()
            ->all();

        $oldest = $items[0] ?? null;

        $summary = [
            'sla_minutes' => $slaMinutes,
            'generated_at' => now()->toIso8601String(),
            'overdue_count' => $overdueCount,
            'unassigned_overdue_count' => $unassignedOverdueCount,
            'assigned_overdue_count' => $assignedOverdueCount,
            'oldest_wait_minutes' => (int) ($oldest['wait_minutes'] ?? 0),
            'oldest_conversation_id' => (int) ($oldest['conversation_id'] ?? 0),
            'items' => $items,
        ];

        $this->table(['Metric', 'Value'], [
            ['SLA minutes', (string) $slaMinutes],
            ['Overdue conversations', (string) $overdueCount],
            ['Unassigned overdue', (string) $unassignedOverdueCount],
            ['Assigned overdue', (string) $assignedOverdueCount],
            ['Rows in digest', (string) count($items)],
            ['Mode', $dryRun ? 'dry-run' : 'live'],
            ['Force', $force ? 'yes' : 'no'],
        ]);

        if ($dryRun) {
            return self::SUCCESS;
        }

        $sendEmpty = (bool) config('support_chat.digest.send_empty', false);
        if ($overdueCount === 0 && ! $sendEmpty && ! $force) {
            $this->info('No overdue conversations. Digest skipped.');

            return self::SUCCESS;
        }

        $recipients = User::query()
            ->supportAgents()
            ->get();

        if ($recipients->isEmpty()) {
            $this->warn('No active admin/staff users found for support escalation digest.');

            return self::SUCCESS;
        }

        foreach ($recipients as $recipient) {
            $recipient->notify(new SupportEscalationDigestNotification($summary));
        }

        $this->info('Support escalation digest sent to ' . $recipients->count() . ' user(s).');

        return self::SUCCESS;
    }

    private function waitMinutes(SupportConversation $conversation): int
    {
        $lastTouch = $conversation->last_customer_message_at
            ?? $conversation->last_message_at
            ?? $conversation->created_at;

        if (! $lastTouch instanceof CarbonInterface) {
            return 0;
        }

        return max(0, $lastTouch->diffInMinutes(now()));
    }
}
