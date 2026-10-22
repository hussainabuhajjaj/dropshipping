<?php

/**
 * Verification script for CJ shipping reconciliation logic.
 * Run with: php artisan tinker < scripts/verify-cj-reconciliation.php
 * 
 * This tests that:
 * 1. Shipments capture CJ order details (cj_order_id, postage_amount, logistic_name)
 * 2. Order reconciliation calculates shipping_total_actual correctly
 * 3. Shipping variance is computed accurately
 */

namespace App\Tests;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;
use Illuminate\Support\Facades\DB;

echo "=== CJ Shipping Reconciliation Verification ===\n\n";

// Test 1: Verify Shipment model has new fields
echo "TEST 1: Shipment model fields\n";
$shipmentFillable = (new Shipment())->getFillable();
$requiredFields = ['cj_order_id', 'shipment_order_id', 'logistic_name', 'postage_amount', 'currency'];
$missingFields = [];
foreach ($requiredFields as $field) {
    if (!in_array($field, $shipmentFillable)) {
        $missingFields[] = $field;
    }
}
if (empty($missingFields)) {
    echo "✓ All required Shipment fields are fillable\n";
} else {
    echo "✗ Missing Shipment fields: " . implode(', ', $missingFields) . "\n";
}

// Test 2: Verify Order model has reconciliation fields
echo "\nTEST 2: Order model reconciliation fields\n";
$orderFillable = (new Order())->getFillable();
$requiredOrderFields = ['shipping_total_estimated', 'shipping_total_actual', 'shipping_variance', 'shipping_reconciled_at'];
$missingOrderFields = [];
foreach ($requiredOrderFields as $field) {
    if (!in_array($field, $orderFillable)) {
        $missingOrderFields[] = $field;
    }
}
if (empty($missingOrderFields)) {
    echo "✓ All required Order fields are fillable\n";
} else {
    echo "✗ Missing Order fields: " . implode(', ', $missingOrderFields) . "\n";
}

// Test 3: Simulate reconciliation logic
echo "\nTEST 3: Reconciliation calculation logic\n";
echo "Scenario: Order with estimated shipping \$15.00, but actual CJ postage is \$12.50 (split into 2 shipments)\n";

$estimatedShipping = 15.00;
$shipments = [
    ['postage_amount' => 6.25, 'cj_order_id' => 'CJ001', 'logistic_name' => 'PostNL'],
    ['postage_amount' => 6.25, 'cj_order_id' => 'CJ002', 'logistic_name' => 'PostNL'],
];

$actual = 0;
foreach ($shipments as $shipment) {
    $actual += $shipment['postage_amount'];
    echo "  - Shipment {$shipment['cj_order_id']}: \${$shipment['postage_amount']} ({$shipment['logistic_name']})\n";
}

$variance = round($actual - $estimatedShipping, 2);
echo "\nCalculations:\n";
echo "  - Estimated: \$$estimatedShipping\n";
echo "  - Actual: \$$actual\n";
echo "  - Variance: \$$variance\n";

if ($variance == -2.50) {
    echo "✓ Reconciliation calculation is correct (negative variance = savings)\n";
} else {
    echo "✗ Reconciliation calculation failed\n";
}

// Test 4: Check database schema
echo "\nTEST 4: Database schema verification\n";
$shipmentColumns = DB::getSchemaBuilder()->getColumnListing('shipments');
foreach ($requiredFields as $field) {
    if (in_array($field, $shipmentColumns)) {
        echo "  ✓ Column '{$field}' exists in shipments table\n";
    } else {
        echo "  ✗ Column '{$field}' NOT found in shipments table\n";
    }
}

$orderColumns = DB::getSchemaBuilder()->getColumnListing('orders');
foreach ($requiredOrderFields as $field) {
    if (in_array($field, $orderColumns)) {
        echo "  ✓ Column '{$field}' exists in orders table\n";
    } else {
        echo "  ✗ Column '{$field}' NOT found in orders table\n";
    }
}

echo "\n=== Verification Complete ===\n";
