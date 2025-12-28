<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use App\Filament\Pages\BasePage;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;

class AnalyticsDashboard extends BasePage
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static UnitEnum|string|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 10;
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.analytics-dashboard';

    public function getKPIs(): array
    {
        $today = today();
        $yesterday = today()->subDay();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Today's metrics
        $todayOrders = Order::whereDate('created_at', $today)->count();
        $todayRevenue = Payment::where('status', 'paid')
            ->whereDate('paid_at', $today)
            ->sum('amount');

        // Yesterday comparison
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
        $lastMonthEnd = $thisMonth->copy()->subDay();
        $lastMonthStart = $lastMonth;
        $prevMonthOrders = Order::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $prevMonthRevenue = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        // Payment success/failure
        $paidPayments = Payment::where('status', 'paid')->count();
        $failedPayments = Payment::where('status', 'failed')->count();
        $totalPayments = $paidPayments + $failedPayments;
        $paymentSuccessRate = $totalPayments > 0 ? round(($paidPayments / $totalPayments) * 100, 1) : 0;

        // AOV (Average Order Value)
        $totalSales = Payment::where('status', 'paid')->sum('amount');
        $totalOrders = Order::count();
        $aov = $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0;

        // Fulfillment lead time
        $fulfilledOrders = Order::whereNotNull('fulfilled_at')->count();
        $avgLeadTime = 0;
        if ($fulfilledOrders > 0) {
            $avgLeadTime = Order::whereNotNull('fulfilled_at')
                ->selectRaw('AVG(DATEDIFF(fulfilled_at, created_at)) as avg_days')
                ->value('avg_days');
            $avgLeadTime = round($avgLeadTime ?? 0, 1);
        }

        // Refund rate
        $refundedAmount = Payment::where('status', 'refunded')->sum('amount');
        $refundRate = $totalSales > 0 ? round(($refundedAmount / $totalSales) * 100, 1) : 0;

        // Conversion rate (estimate: paid orders / total orders)
        $conversionRate = $totalOrders > 0 ? round(($paidPayments / $totalOrders) * 100, 1) : 0;

        return [
            [
                'label' => 'Today\'s Revenue',
                'value' => '$' . number_format($todayRevenue, 2),
                'change' => $yesterdayRevenue > 0 ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1) : 0,
                'icon' => 'heroicon-o-currency-dollar',
                'color' => 'primary',
            ],
            [
                'label' => 'Today\'s Orders',
                'value' => $todayOrders,
                'change' => $yesterdayOrders > 0 ? round((($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100, 1) : 0,
                'icon' => 'heroicon-o-shopping-cart',
                'color' => 'success',
            ],
            [
                'label' => 'This Month Revenue',
                'value' => '$' . number_format($monthRevenue, 2),
                'change' => $prevMonthRevenue > 0 ? round((($monthRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100, 1) : 0,
                'icon' => 'heroicon-o-chart-line',
                'color' => 'info',
            ],
            [
                'label' => 'Average Order Value',
                'value' => '$' . number_format($aov, 2),
                'change' => 0,
                'icon' => 'heroicon-o-banknotes',
                'color' => 'warning',
            ],
            [
                'label' => 'Payment Success Rate',
                'value' => $paymentSuccessRate . '%',
                'change' => 0,
                'icon' => 'heroicon-o-check-circle',
                'color' => $paymentSuccessRate >= 95 ? 'success' : ($paymentSuccessRate >= 80 ? 'warning' : 'danger'),
            ],
            [
                'label' => 'Conversion Rate',
                'value' => $conversionRate . '%',
                'change' => 0,
                'icon' => 'heroicon-o-arrow-trending-up',
                'color' => 'success',
            ],
            [
                'label' => 'Avg Fulfillment Lead Time',
                'value' => $avgLeadTime . ' days',
                'change' => 0,
                'icon' => 'heroicon-o-clock',
                'color' => $avgLeadTime <= 3 ? 'success' : ($avgLeadTime <= 7 ? 'warning' : 'danger'),
            ],
            [
                'label' => 'Refund Rate',
                'value' => $refundRate . '%',
                'change' => 0,
                'icon' => 'heroicon-o-arrow-uturn-left',
                'color' => $refundRate < 2 ? 'success' : ($refundRate < 5 ? 'warning' : 'danger'),
            ],
        ];
    }

    public function getOrderTrend(): array
    {
        $days = 14;
        $dates = collect(range(0, $days - 1))
            ->map(fn($i) => today()->subDays($days - 1 - $i))
            ->toArray();

        return [
            'labels' => array_map(fn($date) => $date->format('M d'), $dates),
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => array_map(fn($date) => Order::whereDate('created_at', $date)->count(), $dates),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
                [
                    'label' => 'Revenue',
                    'data' => array_map(fn($date) => Payment::where('status', 'paid')
                        ->whereDate('paid_at', $date)
                        ->sum('amount'), $dates),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
            ],
        ];
    }

    public function getOrderStatus(): array
    {
        return [
            'pending' => Order::where('status', 'pending')->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'fulfilled' => Order::where('status', 'fulfilled')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
        ];
    }

    public function getTopProducts(): array
    {
        return collect(\DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.name', \DB::raw('SUM(order_items.quantity) as total_qty'), \DB::raw('SUM(order_items.total) as total_revenue'))
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_qty', 'desc')
            ->limit(5)
            ->get()
            ->toArray())
            ->map(fn($item) => [
                'name' => $item->name,
                'qty' => $item->total_qty,
                'revenue' => '$' . number_format($item->total_revenue, 2),
            ])
            ->toArray();
    }
}
