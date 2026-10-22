<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$today = Carbon::today();
echo "Today: " . $today->format('Y-m-d') . "\n\n";

// All orders placed today regardless of status
$all = DB::table('orders')
    ->whereDate('placed_at', $today)
    ->get();
echo "Total orders placed today (any status): " . $all->count() . "\n";

// Orders by status
$byStatus = DB::table('orders')
    ->whereDate('placed_at', $today)
    ->select('status', DB::raw('count(*) as cnt'))
    ->groupBy('status')
    ->get();
echo "\nBy status:\n";
foreach ($byStatus as $s) {
    echo "  {$s->status}: {$s->cnt}\n";
}

// Orders by payment_status
$byPayStatus = DB::table('orders')
    ->whereDate('placed_at', $today)
    ->select('payment_status', DB::raw('count(*) as cnt'))
    ->groupBy('payment_status')
    ->get();
echo "\nBy payment_status:\n";
foreach ($byPayStatus as $s) {
    echo "  {$s->payment_status}: {$s->cnt}\n";
}

// Last 5 orders by placed_at
echo "\nLast 5 orders:\n";
$recent = DB::table('orders')
    ->orderBy('placed_at', 'desc')
    ->limit(5)
    ->get(['id','number','status','payment_status','grand_total','placed_at','currency']);
foreach ($recent as $o) {
    echo "  #{$o->id} {$o->number} | status={$o->status} pay={$o->payment_status} | {$o->grand_total} {$o->currency} | placed: " . ($o->placed_at ? $o->placed_at->format('Y-m-d H:i') : 'null') . "\n";
}
