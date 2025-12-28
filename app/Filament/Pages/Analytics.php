<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Filament\Pages\Dashboard;

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

        // Today
        $todayOrders = Order::whereDate('created_at', $today)->count();
        $todayRevenue = Payment::where('status', 'paid')
            ->whereDate('paid_at', $today)
            ->sum('amount');

        // Yesterday
        $yesterdayOrders = Order::whereDate('created_at', $yesterday)->count();
        $yesterdayRevenue = Payment::where('status', 'paid')
            ->whereDate('paid_at', $yesterday)
            ->sum('amount');

        // This month
        $monthOrders = Order::whereBetween('created_at', [$thisMonth, now()])->count();
        $monthRevenue = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$thisMonth, now()])
            ->sum('amount');

        // Last month
        $lastMonthOrders = Order::whereBetween('created_at', [$lastMonth, $lastMonth->clone()->endOfMonth()])->count();
        $lastMonthRevenue = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$lastMonth, $lastMonth->clone()->endOfMonth()])
            ->sum('amount');

        // Payment success rate
        $totalPayments = Payment::count();
        $paidPayments = Payment::where('status', 'paid')->count();
        $failedPayments = Payment::where('status', 'failed')->count();
        $paymentSuccessRate = $totalPayments > 0 ? round(($paidPayments / $totalPayments) * 100, 1) : 0;

        // Average order value
        $aov = $monthOrders > 0 ? round($monthRevenue / $monthOrders, 2) : 0;

        // Fulfillment lead time (average days between order and shipment)
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
            'today_order_change' => $yesterdayOrders > 0 ? round((($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100, 1) : 0,
            'today_revenue_change' => $yesterdayRevenue > 0 ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1) : 0,
            'month_orders' => $monthOrders,
            'month_revenue' => $monthRevenue,
            'month_aov' => $aov,
            'payment_success_rate' => $paymentSuccessRate,
            'payment_failed' => $failedPayments,
            'fulfillment_lead_time_days' => $avgLeadTime,
        ];
    }
}
