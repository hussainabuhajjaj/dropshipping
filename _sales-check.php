<?php
// daily-sales-check.php
require __DIR__.'/bootstrap/app.php';

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use Carbon\Carbon;

$today = Carbon::today();
$tomorrow = Carbon::tomorrow();

echo "=== SIMBAZU DAILY SALES CHECK ===\n";
echo "Today: {$today->toDateString()}\n";
echo "Now: " . Carbon::now()->toDateTimeString() . "\n\n";

// Orders placed today (not cancelled/refunded)
$orders = Order::whereBetween('placed_at', [$today, $tomorrow])
    ->whereNotIn('status', ['cancelled', 'refunded'])
    ->get();

echo "ORDERS TODAY (active):\n";
echo "  Count: " . $orders->count() . "\n";
echo "  Revenue: " . number_format($orders->sum('grand_total'), 2) . " XOF\n";
echo "  Status breakdown:\n";
foreach ($orders->groupBy('status')->map->count() as $s => $c) {
    echo "    [{$s}] => {$c}\n";
}

// All orders today including cancelled
$allOrders = Order::whereBetween('placed_at', [$today, $tomorrow])->get();
echo "\nALL ORDERS TODAY (any status):\n";
echo "  Count: " . $allOrders->count() . "\n";
foreach ($allOrders->groupBy('status')->map->count() as $s => $c) {
    echo "    [{$s}] => {$c}\n";
}

// Payments
$cod = Payment::whereBetween('paid_at', [$today, $tomorrow])->where('provider', 'cod')->get();
$paystack = Payment::whereBetween('paid_at', [$today, $tomorrow])->where('provider', 'paystack')->get();
$other = Payment::whereBetween('paid_at', [$today, $tomorrow])->whereNotIn('provider', ['cod', 'paystack'])->get();

echo "\nPAYMENTS TODAY:\n";
echo "  COD:     {$cod->count()} txns, " . number_format($cod->sum('amount'), 2) . " XOF\n";
echo "  Paystack: {$paystack->count()} txns, " . number_format($paystack->sum('amount'), 2) . " XOF\n";
echo "  Other:   {$other->count()} txns, " . number_format($other->sum('amount'), 2) . " XOF\n";
$totalPay = $cod->sum('amount') + $paystack->sum('amount') + $other->sum('amount');
echo "  TOTAL:   " . number_format($totalPay, 2) . " XOF\n";

// AOV
$aov = $orders->count() > 0 ? $orders->sum('grand_total') / $orders->count() : 0;
echo "\nAOV: " . number_format($aov, 2) . " XOF\n";

// Last order
$last = Order::whereNotIn('status', ['cancelled', 'refunded'])->latest('placed_at')->first();
echo "\nLAST ACTIVE ORDER:\n";
if ($last) {
    echo "  #{$last->number} — {$last->placed_at->format('l, F j, Y')} — " . number_format($last->grand_total, 2) . " XOF\n";
} else {
    echo "  (none)\n";
}

echo "\n=== END ===\n";
