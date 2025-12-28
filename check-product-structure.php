<?php
require __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

// Check a product that's showing the flat category structure
$product = DB::table('products')
    ->where('name', 'like', '%Blazer%')
    ->orWhere('name', 'like', '%Blouse%')
    ->first();

if ($product) {
    echo "Product: " . $product->name . "\n\n";
    echo "Raw product data:\n";
    var_dump($product);
} else {
    echo "No product found\n";
}

// Check first 5 products to see their structure
echo "\n\n=== FIRST 5 PRODUCTS ===\n\n";
$products = DB::table('products')->limit(5)->get();
foreach ($products as $p) {
    echo "ID: {$p->id}, Name: {$p->name}, CJ PID: {$p->cj_pid}\n";
}
