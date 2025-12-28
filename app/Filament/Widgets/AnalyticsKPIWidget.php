<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AnalyticsKPIWidget extends BaseWidget
{
    protected ?string $heading = 'Key Performance Indicators';

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $today = today();
        $yesterday = today()->subDay();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Today's metrics
        $todayOrders = Order::whereDate('created_at', $today)->count();
        $todayRevenue = (float) Payment::where('status', 'paid')
            ->whereDate('paid_at', $today)
            ->sum('amount');

        // Yesterday comparison
        $yesterdayOrders = Order::whereDate('created_at', $yesterday)->count();
        $yesterdayRevenue = (float) Payment::where('status', 'paid')
            ->whereDate('paid_at', $yesterday)
            ->sum('amount');

        // This month
        $monthOrders = Order::whereBetween('created_at', [$thisMonth, now()])->count();
        $monthRevenue = (float) Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$thisMonth, now()])
            ->sum('amount');

        // Last month
        $lastMonthEnd = $thisMonth->copy()->subDay();
        $lastMonthStart = $lastMonth;
        $prevMonthOrders = Order::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $prevMonthRevenue = (float) Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        // Payment success/failure
        $paidPayments = Payment::where('status', 'paid')->count();
        $failedPayments = Payment::where('status', 'failed')->count();
        $totalPayments = $paidPayments + $failedPayments;
        $paymentSuccessRate = $totalPayments > 0 ? round(($paidPayments / $totalPayments) * 100, 1) : 0;

        // AOV (Average Order Value)
        $totalSales = (float) Payment::where('status', 'paid')->sum('amount');
        $totalOrders = Order::count();
        $aov = $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0;

        // Fulfillment lead time
        $fulfilledOrders = Order::whereNotNull('fulfilled_at')->count();
        $avgLeadTime = 0;
        if ($fulfilledOrders > 0) {
            $avgLeadTime = Order::whereNotNull('fulfilled_at')
                ->selectRaw('AVG(DATEDIFF(fulfilled_at, created_at)) as avg_days')
                ->value('avg_days');
            $avgLeadTime = round((float)($avgLeadTime ?? 0), 1);
        }

        // Refund rate
        $refundedAmount = (float) Payment::where('status', 'refunded')->sum('amount');
        $refundRate = $totalSales > 0 ? round(($refundedAmount / $totalSales) * 100, 1) : 0;

        // Conversion rate (estimate: paid orders / total orders)
        $conversionRate = $totalOrders > 0 ? round(($paidPayments / $totalOrders) * 100, 1) : 0;

        return [
            Stat::make('Today\'s Revenue', '$' . number_format($todayRevenue, 2))
                ->description($yesterdayRevenue > 0 ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1) . '% vs yesterday' : 'First day')
                ->descriptionIcon($todayRevenue >= $yesterdayRevenue ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayRevenue >= $yesterdayRevenue ? 'success' : 'warning'),

            Stat::make('Today\'s Orders', (string) $todayOrders)
                ->description($yesterdayOrders > 0 ? round((($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100, 1) . '% vs yesterday' : 'First day')
                ->descriptionIcon($todayOrders >= $yesterdayOrders ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayOrders >= $yesterdayOrders ? 'success' : 'warning'),

            Stat::make('This Month Revenue', '$' . number_format($monthRevenue, 2))
                ->description($prevMonthRevenue > 0 ? round((($monthRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100, 1) . '% vs last month' : 'First month')
                ->color('info'),

            Stat::make('Conversion Rate', $conversionRate . '%')
                ->description('Paid / Total orders')
                ->color($conversionRate >= 2 ? 'success' : ($conversionRate >= 1 ? 'warning' : 'danger')),

            Stat::make('Average Order Value', '$' . number_format($aov, 2))
                ->description('Total sales / orders')
                ->color('warning'),

            Stat::make('Payment Success Rate', $paymentSuccessRate . '%')
                ->description('Paid / (Paid + Failed)')
                ->color($paymentSuccessRate >= 95 ? 'success' : ($paymentSuccessRate >= 80 ? 'warning' : 'danger')),

            Stat::make('Avg Fulfillment Lead Time', $avgLeadTime . ' days')
                ->description('From order to fulfillment')
                ->color($avgLeadTime <= 3 ? 'success' : ($avgLeadTime <= 7 ? 'warning' : 'danger')),

            Stat::make('Refund Rate', $refundRate . '%')
                ->description('Refunded / Total sales')
                ->color($refundRate < 2 ? 'success' : ($refundRate < 5 ? 'warning' : 'danger')),
        ];
    }
}
