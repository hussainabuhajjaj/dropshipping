<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\Payment;
use BackedEnum;
use Filament\Pages\Dashboard;
use UnitEnum;

class Analytics extends Dashboard
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static UnitEnum|string|null $navigationGroup = 'Analytics';
    protected static ?int $navigationSort = 5;
    protected static ?string $title = 'Analytics';
    protected static bool $shouldRegisterNavigation = false;

    public function getHeaderWidgets(): array
    {
        return [];
    }

    public function getFooterWidgets(): array
    {
        return [];
    }

    /**
     * Get analytics summary for dashboard display.
     */
    public function getAnalyticsSummary(): array
    {
        $today = today();
        $yesterday = today()->subDay();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

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

        $monthOrders = Order::whereBetween('created_at', [$thisMonth, now()])->count();
        $monthRevenue = (float) Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$thisMonth, now()])
            ->sum('grand_total');
        $monthProfit = (float) Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$thisMonth, now()])
            ->sum('gross_profit_amount');

        $lastMonthOrders = Order::whereBetween('created_at', [$lastMonth, $lastMonth->clone()->endOfMonth()])->count();
        $lastMonthRevenue = (float) Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastMonth, $lastMonth->clone()->endOfMonth()])
            ->sum('grand_total');
        $lastMonthProfit = (float) Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastMonth, $lastMonth->clone()->endOfMonth()])
            ->sum('gross_profit_amount');

        $totalPayments = Payment::count();
        $paidPayments = Payment::where('status', 'paid')->count();
        $failedPayments = Payment::where('status', 'failed')->count();
        $paymentSuccessRate = $totalPayments > 0 ? round(($paidPayments / $totalPayments) * 100, 1) : 0;

        $paidMonthOrders = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$thisMonth, now()])
            ->count();
        $aov = $paidMonthOrders > 0 ? round($monthRevenue / $paidMonthOrders, 2) : 0;
        $monthMargin = $monthRevenue > 0 ? round(($monthProfit / $monthRevenue) * 100, 1) : 0;

        $ordersWithTracking = Order::whereNotNull('shipped_at')->count();
        $avgLeadTime = 0;
        if ($ordersWithTracking > 0) {
            $totalDays = Order::whereNotNull('shipped_at')
                ->selectRaw('SUM(DATEDIFF(shipped_at, created_at)) as total_days')
                ->value('total_days') ?? 0;
            $avgLeadTime = round($totalDays / $ordersWithTracking, 1);
        }

        return [
            'today_orders' => $todayOrders,
            'today_revenue' => $todayRevenue,
            'today_profit' => $todayProfit,
            'today_order_change' => $yesterdayOrders > 0 ? round((($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100, 1) : 0,
            'today_revenue_change' => $yesterdayRevenue > 0 ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1) : 0,
            'today_profit_change' => $yesterdayProfit !== 0.0 ? round((($todayProfit - $yesterdayProfit) / abs($yesterdayProfit)) * 100, 1) : 0,
            'month_orders' => $monthOrders,
            'month_revenue' => $monthRevenue,
            'month_profit' => $monthProfit,
            'month_revenue_change' => $lastMonthRevenue > 0 ? round((($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1) : 0,
            'month_profit_change' => $lastMonthProfit !== 0.0 ? round((($monthProfit - $lastMonthProfit) / abs($lastMonthProfit)) * 100, 1) : 0,
            'last_month_orders' => $lastMonthOrders,
            'month_aov' => $aov,
            'month_gross_margin' => $monthMargin,
            'payment_success_rate' => $paymentSuccessRate,
            'payment_failed' => $failedPayments,
            'fulfillment_lead_time_days' => $avgLeadTime,
        ];
    }
}
