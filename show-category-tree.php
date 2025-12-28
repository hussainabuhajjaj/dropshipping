#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Products\Models\Category;

function printCategory($category, $depth = 0) {
    $indent = str_repeat('  ', $depth);
    $cjId = $category->cj_id ? " [CJ: {$category->cj_id}]" : '';
    $prodCount = $category->products()->count();
    
    echo "{$indent}├─ {$category->name}{$cjId} (ID: {$category->id}, Products: {$prodCount})\n";
    
    if ($category->children()->count() > 0) {
        foreach ($category->children()->orderBy('name')->get() as $child) {
            printCategory($child, $depth + 1);
        }
    }
}

echo "\n=== CATEGORY TREE STRUCTURE ===\n\n";

$roots = Category::whereNull('parent_id')->orderBy('name')->get();

if ($roots->isEmpty()) {
    echo "❌ No root categories found!\n\n";
    echo "Total categories in database: " . Category::count() . "\n\n";
    
    $sample = Category::limit(5)->get();
    echo "Sample categories:\n";
    foreach ($sample as $cat) {
        echo "  ID: {$cat->id}, Name: {$cat->name}, Parent: {$cat->parent_id}\n";
    }
} else {
    echo "✅ Root Categories:\n\n";
    
    foreach ($roots as $root) {
        printCategory($root, 0);
    }
}

echo "\n=== END TREE ===\n\n";
