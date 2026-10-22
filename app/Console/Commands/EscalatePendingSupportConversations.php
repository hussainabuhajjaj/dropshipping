<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Support\Models\SupportConversation;
use App\Models\User;
use App\Notifications\Support\AdminSupportConversationAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class EscalatePendingSupportConversations extends Command
{
    protected $signature = 'support:escalate-pending
        {--sla-minutes= : Override SLA wait threshold in minutes}
        {--repeat-minutes= : Override repeat-notification cooldown in minutes}
        {--limit= : Override max conversations to process in one run}
        {--force : Ignore cooldown and notify even if recently escalated}
        {--dry-run : Analyze and print counts without sending notifications}';

    protected $description = 'Escalate pending support conversations that exceeded SLA and notify admins.';

    public function handle(): int
    {
        $enabled = (bool) config('support_chat.escalation.enabled', true);
        $dryRun = (bool) $this->option('dry-run');
        if (! $enabled && ! $dryRun) {
            $this->warn('Support escalation is disabled (SUPPORT_CHAT_ESCALATION_ENABLED=false).');

            return self::SUCCESS;
        }

        $slaMinutes = max(1, (int) ($this->option('sla-minutes') ?? config('support_chat.escalation.sla_minutes', 15)));
        $repeatMinutes = max(1, (int) ($this->option('repeat-minutes') ?? config('support_chat.escalation.repeat_minutes', 60)));
        $limit = max(1, (int) ($this->option('limit') ?? config('support_chat.escalation.max_per_run', 200)));
        $force = (bool) $this->option('force');

        $threshold = now()->subMinutes($slaMinutes);

        $query = SupportConversation::query()
            ->overdueSla($threshold)
            ->orderByRaw('COALESCE(last_customer_message_at, last_message_at, created_at) asc')
            ->limit($limit);

        $conversations = $query->get();
        if ($conversations->isEmpty()) {
            $this->info('No pending support conversations exceeded SLA.');

            return self::SUCCESS;
        }

        $admins = User::query()
            ->supportAgents()
            ->get()
            ->keyBy('id');

        if ($admins->isEmpty()) {
            $this->warn('No active admin/staff users found for escalation notifications.');

            return self::SUCCESS;
        }

        $escalated = 0;
        $skippedCooldown = 0;
        $sentNotifications = 0;

        foreach ($conversations as $conversation) {
            $cacheKey = 'support:sla-alert:conversation:' . $conversation->id;
            if (! $dryRun && ! $force && Cache::has($cacheKey)) {
                $skippedCooldown++;
                continue;
            }

            $waitMinutes = $this->computeWaitMinutes($conversation);
            $reason = sprintf(
                'SLA breach: customer has waited %d minutes without agent reply (threshold %d minutes).',
                $waitMinutes,
                $slaMinutes
            );

            $recipients = $this->resolveRecipients($admins->all(), $conversation->assigned_user_id);
            if (! $dryRun) {
                foreach ($recipients as $recipient) {
                    $recipient->notify(new AdminSupportConversationAlert($conversation, $reason));
                    $sentNotifications++;
                }

                if (! $force) {
                    Cache::put($cacheKey, now()->toIso8601String(), now()->addMinutes($repeatMinutes));
                }
            }

            $escalated++;
        }

        $this->info($dryRun ? 'Dry run completed.' : 'Support escalation run completed.');
        $this->table(['Metric', 'Value'], [
            ['SLA minutes', (string) $slaMinutes],
            ['Repeat cooldown minutes', (string) $repeatMinutes],
            ['Candidates', (string) $conversations->count()],
            ['Escalated conversations', (string) $escalated],
            ['Skipped by cooldown', (string) $skippedCooldown],
            ['Notifications sent', (string) $sentNotifications],
            ['Mode', $dryRun ? 'dry-run' : 'live'],
            ['Force cooldown bypass', $force ? 'yes' : 'no'],
        ]);

        return self::SUCCESS;
    }

    private function computeWaitMinutes(SupportConversation $conversation): int
    {
        $lastTouch = $conversation->last_customer_message_at
            ?? $conversation->last_message_at
            ?? $conversation->created_at;

        if (! $lastTouch instanceof Carbon) {
            return 0;
        }

        return max(0, $lastTouch->diffInMinutes(now()));
    }

    /**
     * @param array<int, User> $adminsById
     * @return array<int, User>
     */
    private function resolveRecipients(array $adminsById, ?int $assignedUserId): array
    {
        if ($assignedUserId && isset($adminsById[$assignedUserId])) {
            return [$adminsById[$assignedUserId]];
        }

        return array_values($adminsById);
    }
}
