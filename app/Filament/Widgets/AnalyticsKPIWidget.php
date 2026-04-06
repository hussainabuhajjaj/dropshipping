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
        $now = now();
        $lastMonthEnd = $thisMonth->copy()->subSecond();

        $todayOrders = Order::whereDate('created_at', $today)->count();
        $todayRevenue = (float) Order::where('payment_status', 'paid')
            ->whereDate('created_at', $today)
            ->sum('grand_total');
        $todayProfit = (float) Order::where('payment_status', 'paid')
            ->whereDate('created_at', $today)
            ->sum('gross_profit_amount');

        $yesterdayOrders = Order::whereDate('created_at', $yesterday)->count();
        $yesterdayRevenue = (float) Order::where('payment_status', 'paid')
            ->whereDate('created_at', $yesterday)
            ->sum('grand_total');
        $yesterdayProfit = (float) Order::where('payment_status', 'paid')
            ->whereDate('created_at', $yesterday)
            ->sum('gross_profit_amount');

        $monthRevenue = (float) Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$thisMonth, $now])
            ->sum('grand_total');
        $monthProfit = (float) Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$thisMonth, $now])
            ->sum('gross_profit_amount');

        $lastMonthStart = $lastMonth;
        $prevMonthRevenue = (float) Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('grand_total');
        $prevMonthProfit = (float) Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('gross_profit_amount');

        $paidPayments = Payment::where('status', 'paid')->count();
        $failedPayments = Payment::where('status', 'failed')->count();
        $totalPayments = $paidPayments + $failedPayments;
        $paymentSuccessRate = $totalPayments > 0 ? round(($paidPayments / $totalPayments) * 100, 1) : 0;

        $paidOrders = Order::where('payment_status', 'paid')->count();
        $totalSales = (float) Order::where('payment_status', 'paid')->sum('grand_total');
        $aov = $paidOrders > 0 ? round($totalSales / $paidOrders, 2) : 0;
        $grossMargin = $monthRevenue > 0 ? round(($monthProfit / $monthRevenue) * 100, 1) : 0;

        $fulfilledOrders = Order::whereNotNull('fulfilled_at')->count();
        $avgLeadTime = 0;
        if ($fulfilledOrders > 0) {
            $avgLeadTime = Order::whereNotNull('fulfilled_at')
                ->selectRaw('AVG(DATEDIFF(fulfilled_at, created_at)) as avg_days')
                ->value('avg_days');
            $avgLeadTime = round((float) ($avgLeadTime ?? 0), 1);
        }

        $totalOrders = Order::count();
        $conversionRate = $totalOrders > 0 ? round(($paidOrders / $totalOrders) * 100, 1) : 0;

        return [
            Stat::make("Today's Revenue", '$' . number_format($todayRevenue, 2))
                ->description($yesterdayRevenue > 0 ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1) . '% vs yesterday' : 'First day')
                ->descriptionIcon($todayRevenue >= $yesterdayRevenue ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayRevenue >= $yesterdayRevenue ? 'success' : 'warning'),

            Stat::make("Today's Profit", '$' . number_format($todayProfit, 2))
                ->description($yesterdayProfit !== 0.0 ? round((($todayProfit - $yesterdayProfit) / abs($yesterdayProfit)) * 100, 1) . '% vs yesterday' : 'First day')
                ->descriptionIcon($todayProfit >= $yesterdayProfit ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayProfit >= 0 ? 'success' : 'danger'),

            Stat::make("Today's Orders", (string) $todayOrders)
                ->description($yesterdayOrders > 0 ? round((($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100, 1) . '% vs yesterday' : 'First day')
                ->descriptionIcon($todayOrders >= $yesterdayOrders ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayOrders >= $yesterdayOrders ? 'success' : 'warning'),

            Stat::make('This Month Revenue', '$' . number_format($monthRevenue, 2))
                ->description($prevMonthRevenue > 0 ? round((($monthRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100, 1) . '% vs last month' : 'First month')
                ->color('info'),

            Stat::make('This Month Profit', '$' . number_format($monthProfit, 2))
                ->description($prevMonthProfit !== 0.0 ? round((($monthProfit - $prevMonthProfit) / abs($prevMonthProfit)) * 100, 1) . '% vs last month' : 'First month')
                ->color($monthProfit >= 0 ? 'success' : 'danger'),

            Stat::make('Gross Margin', $grossMargin . '%')
                ->description('This month profit / revenue')
                ->color($grossMargin >= 20 ? 'success' : ($grossMargin >= 10 ? 'warning' : 'danger')),

            Stat::make('Average Order Value', '$' . number_format($aov, 2))
                ->description('Paid revenue / paid orders')
                ->color('warning'),

            Stat::make('Payment Success Rate', $paymentSuccessRate . '%')
                ->description('Paid / (Paid + Failed)')
                ->color($paymentSuccessRate >= 95 ? 'success' : ($paymentSuccessRate >= 80 ? 'warning' : 'danger')),

            Stat::make('Avg Fulfillment Lead Time', $avgLeadTime . ' days')
                ->description('From order to fulfillment')
                ->color($avgLeadTime <= 3 ? 'success' : ($avgLeadTime <= 7 ? 'warning' : 'danger')),

            Stat::make('Conversion Rate', $conversionRate . '%')
                ->description('Paid / total orders')
                ->color($conversionRate >= 2 ? 'success' : ($conversionRate >= 1 ? 'warning' : 'danger')),
        ];
    }
}
