<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportConversationResource\Widgets;

use App\Domain\Support\Models\SupportConversation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SupportConversationStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $baseQuery = SupportConversation::query();
        $slaMinutes = max(1, (int) config('support_chat.escalation.sla_minutes', 15));
        $slaThreshold = now()->subMinutes($slaMinutes);

        $pendingAgent = (clone $baseQuery)
            ->where('status', 'pending_agent')
            ->count();

        $handoffRequested = (clone $baseQuery)
            ->where('handoff_requested', true)
            ->whereIn('status', ['open', 'pending_agent'])
            ->count();

        $unassignedPending = (clone $baseQuery)
            ->where('status', 'pending_agent')
            ->whereNull('assigned_user_id')
            ->count();

        $resolvedToday = (clone $baseQuery)
            ->whereDate('resolved_at', now()->toDateString())
            ->count();

        $overdueSla = (clone $baseQuery)
            ->overdueSla($slaThreshold)
            ->count();

        $myOpen = 0;
        $adminId = auth(config('filament.auth.guard', 'admin'))->id();
        if ($adminId) {
            $myOpen = (clone $baseQuery)
                ->where('assigned_user_id', $adminId)
                ->whereIn('status', ['open', 'pending_agent', 'pending_customer'])
                ->count();
        }

        return [
            Stat::make('Pending Agent', (string) $pendingAgent)
                ->description('Waiting for support response')
                ->color($pendingAgent > 0 ? 'warning' : 'success'),
            Stat::make('Handoff Requested', (string) $handoffRequested)
                ->description('Escalated from AI/customer request')
                ->color($handoffRequested > 0 ? 'warning' : 'success'),
            Stat::make('Unassigned Pending', (string) $unassignedPending)
                ->description('Needs assignment')
                ->color($unassignedPending > 0 ? 'danger' : 'success'),
            Stat::make('Overdue SLA', (string) $overdueSla)
                ->description("Waiting > {$slaMinutes} minutes")
                ->color($overdueSla > 0 ? 'danger' : 'success'),
            Stat::make('My Open', (string) $myOpen)
                ->description('Assigned to me')
                ->color($myOpen > 0 ? 'info' : 'gray'),
            Stat::make('Resolved Today', (string) $resolvedToday)
                ->description('Closed in the last 24h')
                ->color($resolvedToday > 0 ? 'success' : 'gray'),
        ];
    }
}
