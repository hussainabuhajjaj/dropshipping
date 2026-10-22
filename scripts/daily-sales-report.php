<?php
// Run from project root: php scripts/daily-sales-report.php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$today = Carbon::today();

$orders = DB::table('orders')
    ->whereDate('placed_at', $today)
    ->whereIn('status', ['paid','fulfilling','fulfilled'])
    ->where('payment_status', 'paid')
    ->get();

$totalOrders = (int) $orders->count();
$totalRevenue = (float) $orders->sum('grand_total');

$cods = (float) DB::table('payments')
    ->join('orders', 'payments.order_id', '=', 'orders.id')
    ->whereDate('orders.placed_at', $today)
    ->where('orders.payment_status','paid')
    ->where('orders.status','paid')
    ->where('payments.provider','cod')
    ->where('payments.status','paid')
    ->sum('payments.amount');

$paystack = (float) DB::table('payments')
    ->join('orders','payments.order_id','=', 'orders.id')
    ->whereDate('orders.placed_at',$today)
    ->where('orders.payment_status','paid')
    ->where('orders.status','paid')
    ->where('payments.provider','paystack')
    ->where('payments.status','paid')
    ->sum('payments.amount');

$codCount = (int) DB::table('payments')
    ->join('orders','payments.order_id','=','orders.id')
    ->whereDate('orders.placed_at',$today)
    ->where('orders.payment_status','paid')
    ->where('orders.status','paid')
    ->where('payments.provider','cod')
    ->where('payments.status','paid')
    ->count();

$paystackCount = (int) DB::table('payments')
    ->join('orders','payments.order_id','=','orders.id')
    ->whereDate('orders.placed_at',$today)
    ->where('orders.payment_status','paid')
    ->where('orders.status','paid')
    ->where('payments.provider','paystack')
    ->where('payments.status','paid')
    ->count();

$aov = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

$out = [
    'date' => $today->format('Y-m-d'),
    'date_human' => $today->format('d F Y'),
    'total_orders' => $totalOrders,
    'total_revenue' => round($totalRevenue,2),
    'cod_total' => round($cods,2),
    'paystack_total' => round($paystack,2),
    'cod_count' => $codCount,
    'paystack_count' => $paystackCount,
    'aov' => round($aov,2),
    'currency' => 'XOF',
];
echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
