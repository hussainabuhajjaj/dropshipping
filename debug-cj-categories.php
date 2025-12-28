#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;

echo "\n=== CJ CATEGORIES API RESPONSE ===\n\n";

$client = app(CJDropshippingClient::class);

try {
    $response = $client->listCategories();
    
    if (!$response->success) {
        echo "❌ API Error: " . ($response->error ?? 'Unknown error') . "\n\n";
        exit(1);
    }
    
    $data = $response->data;
    
    echo "Response Type: " . gettype($data) . "\n";
    echo "Total Categories: " . (is_array($data) ? count($data) : 'N/A') . "\n\n";
    
    echo "FIRST 5 CATEGORIES:\n";
    echo json_encode(array_slice((array)$data, 0, 5), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    
    // Analyze structure
    if (is_array($data) && !empty($data)) {
        $first = $data[0];
        echo "FIRST CATEGORY STRUCTURE:\n";
        echo "Keys: " . implode(', ', array_keys((array)$first)) . "\n\n";
        
        foreach ($first as $key => $value) {
            echo "  $key: " . gettype($value) . " = ";
            if (is_array($value) || is_object($value)) {
                echo "Array/Object with " . count((array)$value) . " items\n";
                echo "    First item: " . json_encode(array_slice((array)$value, 0, 1)) . "\n";
            } else {
                echo json_encode($value) . "\n";
            }
        }
    }
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== END ===\n\n";
