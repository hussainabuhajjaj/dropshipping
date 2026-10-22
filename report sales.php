<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Simbazu Daily Sales Report ===\n";
echo "Date: " . date('Y-m-d') . "\n\n";

// Today's date in UTC (adjust if needed)
$today = date('Y-m-d');
echo "Querying for: $today\n\n";

// Get all orders from today
$orders = DB::table('orders')
    ->whereDate('created_at', $today)
    ->get();

$totalOrders = $orders->count();
$totalRevenue = $orders->sum('total');

echo "Total Orders Today: $totalOrders\n";
echo "Total Revenue: $" . number_format($totalRevenue, 2) . "\n\n";

// COD vs Paystack split
$codOrders = $orders->where('payment_method', 'cod')->count();
$paystackOrders = $orders->where('payment_method', 'paystack')->count();
$codRevenue = $orders->where('payment_method', 'cod')->sum('total');
$paystackRevenue = $orders->where('payment_method', 'paystack')->sum('total');

echo "Payment Method Split:\n";
echo "  COD: $codOrders orders | $" . number_format($codRevenue, 2) . "\n";
echo "  Paystack: $paystackOrders orders | $" . number_format($paystackRevenue, 2) . "\n\n";

// Average order value
$aov = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
echo "Average Order Value: $" . number_format($aov, 2) . "\n";

// Order statuses
$statuses = $orders->groupBy('status');
echo "\nOrder Statuses:\n";
foreach ($statuses as $status => $statusOrders) {
    echo "  $status: " . $statusOrders->count() . " orders\n";
}

echo "\n=== End of Report ===\n";
