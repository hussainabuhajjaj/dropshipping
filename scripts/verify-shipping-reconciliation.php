<?php
// Simple verification script: compute shipping reconciliation for a given order ID.
// Usage: php scripts/verify-shipping-reconciliation.php <orderId>

use App\Domain\Orders\Models\Order;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$id = (int) ($argv[1] ?? 0);
if ($id <= 0) {
    fwrite(STDERR, "Provide a valid order ID.\n");
    exit(1);
}

$order = Order::with('shipments')->find($id);
if (! $order) {
    fwrite(STDERR, "Order not found: {$id}\n");
    exit(1);
}

$actual = (float) ($order->shipments->sum('postage_amount') ?? 0);
$estimated = (float) ($order->shipping_total_estimated ?? $order->shipping_total ?? 0);
$variance = round($actual - $estimated, 2);

printf("Order %s\nEstimated: %.2f\nActual: %.2f\nVariance: %.2f\n", $order->number, $estimated, $actual, $variance);
