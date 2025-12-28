#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Products\Models\Category;
use App\Domain\Products\Models\Product;
use Illuminate\Support\Facades\DB;

echo "\n=== CATEGORY SYSTEM HEALTH CHECK ===\n\n";

// Check 1: Total categories
$totalCats = Category::count();
echo "✓ Total categories: {$totalCats}\n";

// Check 2: Root categories
$roots = Category::whereNull('parent_id')->count();
echo "✓ Root categories: {$roots}\n";

// Check 3: Categories with cj_id
$withCjId = Category::whereNotNull('cj_id')->count();
echo "✓ Categories with CJ ID: {$withCjId}\n";

// Check 4: Check for duplicates
$dupes = Category::select('name', 'parent_id', DB::raw('COUNT(*) as cnt'))
    ->groupBy('name', 'parent_id')
    ->having('cnt', '>', 1)
    ->count();

if ($dupes > 0) {
    echo "❌ Duplicate categories found: {$dupes}\n";
} else {
    echo "✓ No duplicate categories\n";
}

// Check 5: Products without category
$noCat = Product::whereNull('category_id')->count();
if ($noCat > 0) {
    echo "⚠️  Products without category: {$noCat}\n";
} else {
    echo "✓ All products have categories\n";
}

// Check 6: Average depth
$maxDepth = 0;
function getDepth($parent, &$max) {
    global $maxDepth;
    $children = \App\Domain\Products\Models\Category::where('parent_id', $parent?->id)->get();
    foreach ($children as $child) {
        $depth = ($parent ? getDepth($parent, $max) + 1 : 1);
        if ($depth > $maxDepth) {
            $maxDepth = $depth;
        }
    }
}

$roots = Category::whereNull('parent_id')->get();
foreach ($roots as $root) {
    $maxDepth = 1;
    getDepth($root, $maxDepth);
}

echo "✓ Max category depth: {$maxDepth} levels\n";

// Check 7: Sample hierarchy
echo "\n--- Sample Category Tree ---\n";
$sample = Category::whereNull('parent_id')->orderBy('name')->first();
if ($sample) {
    echo "\n{$sample->name}\n";
    $children = Category::where('parent_id', $sample->id)->get();
    foreach ($children as $child) {
        echo "  └─ {$child->name}\n";
        $grandchildren = Category::where('parent_id', $child->id)->limit(2)->get();
        foreach ($grandchildren as $grandchild) {
            echo "     └─ {$grandchild->name}\n";
        }
        if ($children->count() > 2) {
            echo "     └─ ...\n";
            break;
        }
    }
}

echo "\n=== END HEALTH CHECK ===\n\n";

// Summary
echo "📊 Summary:\n";
echo "  • Total: {$totalCats} categories\n";
echo "  • Roots: {$roots} root categories\n";
echo "  • With CJ IDs: {$withCjId}\n";
echo "  • Duplicates: " . ($dupes > 0 ? "❌ {$dupes}" : "✓ 0") . "\n";
echo "  • Products assigned: " . (Product::count() - $noCat) . " / " . Product::count() . "\n";
echo "\n";

if ($dupes === 0 && $withCjId > 0 && $roots > 0) {
    echo "✅ Category system looks healthy!\n";
} else {
    echo "⚠️  Run: php artisan categories:fix\n";
}

echo "\n";
