<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$today = Carbon::today();
echo "Today: " . $today->format('Y-m-d') . "\n\n";

// Last 7 days
echo "Last 7 days order counts (by placed_at, any status):\n";
for ($i = 6; $i >= 0; $i--) {
    $d = $today->copy()->subDays($i);
    $cnt = DB::table('orders')->whereDate('placed_at', $d)->count();
    $label = $d->format('l, j M');
    $dateStr = $d->format('Y-m-d');
    echo "  $label ($dateStr): $cnt\n";
}

echo "\nLast 10 orders by placed_at:\n";
$recent = DB::table('orders')->orderBy('placed_at', 'desc')->limit(10)->get(['id', 'number', 'status', 'payment_status', 'grand_total', 'placed_at']);
if ($recent->count() === 0) {
    echo "  (none)\n";
}
foreach ($recent as $o) {
    $placed = $o->placed_at ? Carbon::parse($o->placed_at)->format('Y-m-d H:i') : 'null';
    echo "  #$o->id $o->number | status=$o->status pay=$o->payment_status | " . number_format($o->grand_total, 2) . " | placed: $placed\n";
}
echo "\nDone.\n";
