<?php

require __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

// Get products with their categories
$products = DB::table('products')
    ->join('categories', 'products.category_id', '=', 'categories.id')
    ->select(
        'products.name as product_name',
        'categories.id as cat_id',
        'categories.name as cat_name',
        'categories.cj_id',
        'categories.parent_id',
        'products.category_id'
    )
    ->limit(20)
    ->get();

echo "\n=== PRODUCTS WITH CATEGORIES (V2 API STRUCTURE) ===\n\n";

foreach ($products as $product) {
    // Get full category path
    $catId = $product->cat_id;
    $path = [];
    
    do {
        $cat = DB::table('categories')->find($catId);
        if (!$cat) break;
        array_unshift($path, $cat->name . ($cat->cj_id ? " [CJ:" . substr($cat->cj_id, 0, 8) . "]" : ""));
        $catId = $cat->parent_id;
    } while ($catId);
    
    echo "Product: " . $product->product_name . "\n";
    echo "Path: " . implode(" > ", $path) . "\n";
    echo "\n";
}

// Show category statistics
echo "\n=== CATEGORY HIERARCHY STRUCTURE ===\n\n";

$roots = DB::table('categories')
    ->where('parent_id', null)
    ->orderBy('name')
    ->get();

foreach ($roots as $root) {
    echo $root->name . ($root->cj_id ? " [CJ:" . substr($root->cj_id, 0, 8) . "...]" : " [NO CJ ID]") . "\n";
    
    $level2 = DB::table('categories')
        ->where('parent_id', $root->id)
        ->orderBy('name')
        ->get();
    
    foreach ($level2 as $cat2) {
        echo "  └─ " . $cat2->name . ($cat2->cj_id ? " [CJ:" . substr($cat2->cj_id, 0, 8) . "...]" : " [NO CJ ID]") . "\n";
        
        $level3 = DB::table('categories')
            ->where('parent_id', $cat2->id)
            ->orderBy('name')
            ->get();
        
        foreach ($level3 as $cat3) {
            echo "     └─ " . $cat3->name . ($cat3->cj_id ? " [CJ:" . substr($cat3->cj_id, 0, 8) . "...]" : " [NO CJ ID]") . "\n";
        }
    }
}

echo "\n";
