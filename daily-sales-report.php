<?php
/**
 * Simbazu Daily Sales Report
 * Run: php daily-sales-report.php
 */
require __DIR__.'/bootstrap/app.php';

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$today = Carbon::today();
$tomorrow = Carbon::tomorrow();

echo "===== SIMBAZU DAILY SALES REPORT =====\n";
echo "Date: " . $today->format('l, F j, Y') . "\n\n";

// Total orders placed today (not cancelled/refunded)
$orders = Order::whereBetween('placed_at', [$today, $tomorrow])
    ->whereNotIn('status', ['cancelled', 'refunded'])
    ->get();

$totalOrders = $orders->count();
echo "📦 Total Orders Placed Today: $totalOrders\n\n";

// Total revenue
$totalRevenue = $orders->sum('grand_total');
echo "💰 Total Revenue Collected: " . number_format($totalRevenue, 2) . " XOF\n\n";

// COD vs Paystack split via payments
$codPayments = Payment::whereBetween('paid_at', [$today, $tomorrow])
    ->where('provider', 'cod')
    ->sum('amount');
$paystackPayments = Payment::whereBetween('paid_at', [$today, $tomorrow])
    ->where('provider', 'paystack')
    ->sum('amount');

$codPercent = $totalRevenue > 0 ? round(($codPayments / $totalRevenue) * 100, 1) : 0;
$paystackPercent = $totalRevenue > 0 ? round(($paystackPayments / $totalRevenue) * 100, 1) : 0;

echo "===== PAYMENT METHOD SPLIT =====\n";
echo "🟤 COD (Cash on Delivery): " . number_format($codPayments, 2) . " XOF (" . $codPercent . "%)\n";
echo "🟢 Paystack: " . number_format($paystackPayments, 2) . " XOF (" . $paystackPercent . "%)\n\n";

// Average Order Value
$aov = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
echo "===== AVERAGE ORDER VALUE =====\n";
echo "📊 AOV: " . number_format($aov, 2) . " XOF\n\n";

// Order status breakdown
$statusCounts = $orders->groupBy('status')->map->count();
echo "===== ORDER STATUS BREAKDOWN =====\n";
foreach ($statusCounts as $status => $count) {
    echo "  • $status: $count\n";
}

// Top customers by order count today
echo "\n===== TOP ORDERS BY VALUE (Today) =====\n";
$topOrders = $orders->sortByDesc('grand_total')->take(5);
if ($topOrders->count() > 0) {
    foreach ($topOrders as $order) {
        $customer = $order->customer ? $order->customer->name : ($order->guest_name ?? 'Guest');
        echo "  • #{$order->number} — " . number_format($order->grand_total, 2) . " XOF — $customer\n";
    }
} else {
    echo "  No orders found today.\n";
}

echo "\n===== END OF REPORT =====\n";
