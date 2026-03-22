<?php

/**
 * Margin Discrepancy Analysis Script
 * 
 * This script analyzes the discrepancy between margin status column counts
 * and margin filter results in the ProductResource.
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Product;
use App\Domain\Products\Services\PricingService;
use App\Filament\Resources\ProductResource;

echo "=== MARGIN DISCREPANCY ANALYSIS ===\n\n";

// 1. Get total products count
$totalProducts = Product::count();
echo "Total Products: {$totalProducts}\n\n";

// 2. Analyze margin status counts (what the column shows)
echo "=== MARGIN STATUS COLUMN COUNTS ===\n";
$marginStatusCounts = Product::all()->groupBy(function ($product) {
    $cost = ProductResource::normalizeAmount($product->cost_price);
    $selling = ProductResource::normalizeAmount($product->selling_price);
    
    if ($cost === null || $selling === null) {
        return 'Missing';
    }
    
    $pricing = PricingService::makeFromConfig();
    $min = $pricing->minSellingPrice($cost);
    
    if ($selling < $min) {
        return 'Below Required';
    }
    
    return 'OK';
})->map->count();

foreach ($marginStatusCounts as $status => $count) {
    echo "{$status}: {$count}\n";
}

$problematicFromColumn = $marginStatusCounts['Missing'] + $marginStatusCounts['Below Required'];
echo "Total Problematic (Column View): {$problematicFromColumn}\n\n";

// 3. Analyze filter counts (what the filter returns)
echo "=== MARGIN FILTER COUNTS ===\n";
$filterQuery = Product::where(function ($query) {
    $query->whereNull('cost_price')
        ->orWhereNull('selling_price')
        ->orWhere('selling_price', '<', 0)
        ->orWhere('cost_price', '<', 0)
        ->orWhere(function ($marginQuery) {
            $marginQuery->whereNotNull('cost_price')
                ->whereNotNull('selling_price')
                ->where('cost_price', '>', 0)
                ->where('selling_price', '>', 0)
                ->whereRaw('selling_price < (
                    CASE 
                        WHEN cost_price <= 5 THEN cost_price * 2.5
                        WHEN cost_price <= 10 THEN cost_price * 2.0
                        WHEN cost_price <= 20 THEN cost_price * 1.8
                        WHEN cost_price <= 50 THEN cost_price * 1.6
                        WHEN cost_price <= 100 THEN cost_price * 1.5
                        WHEN cost_price <= 200 THEN cost_price * 1.4
                        WHEN cost_price <= 500 THEN cost_price * 1.3
                        ELSE cost_price * 1.25
                    END
                )');
        });
});

$filterCount = $filterQuery->count();
echo "Margin Not Set (Filter): {$filterCount}\n\n";

// 4. Find discrepancies
echo "=== DISCREPANCY ANALYSIS ===\n";
echo "Expected (Column): {$problematicFromColumn}\n";
echo "Actual (Filter): {$filterCount}\n";
echo "Difference: " . ($problematicFromColumn - $filterCount) . "\n\n";

// 5. Analyze specific discrepancy cases
echo "=== DETAILED DISCREPANCY ANALYSIS ===\n";
$discrepancies = Product::all()->filter(function ($product) use ($problematicFromColumn, $filterCount) {
    // Margin status logic
    $cost = ProductResource::normalizeAmount($product->cost_price);
    $selling = ProductResource::normalizeAmount($product->selling_price);
    
    $marginStatus = 'OK';
    if ($cost === null || $selling === null) {
        $marginStatus = 'Missing';
    } else {
        $pricing = PricingService::makeFromConfig();
        $min = $pricing->minSellingPrice($cost);
        if ($selling < $min) {
            $marginStatus = 'Below Required';
        }
    }
    
    // Filter logic
    $matchesFilter = (
        is_null($product->cost_price) ||
        is_null($product->selling_price) ||
        $product->selling_price < 0 ||
        $product->cost_price < 0 ||
        ($product->cost_price > 0 && $product->selling_price > 0 && isBelowRequiredMargin($product->cost_price, $product->selling_price))
    );
    
    // Discrepancy: margin status shows problem but filter doesn't match
    return in_array($marginStatus, ['Missing', 'Below Required']) && !$matchesFilter;
});

echo "Discrepancies Found: " . $discrepancies->count() . "\n\n";

// Show first 10 discrepancies
$discrepancies->take(10)->each(function ($product, $index) {
    $cost = ProductResource::normalizeAmount($product->cost_price);
    $selling = ProductResource::normalizeAmount($product->selling_price);
    
    $marginStatus = 'OK';
    if ($cost === null || $selling === null) {
        $marginStatus = 'Missing';
    } else {
        $pricing = PricingService::makeFromConfig();
        $min = $pricing->minSellingPrice($cost);
        if ($selling < $min) {
            $marginStatus = 'Below Required';
        }
    }
    
    echo "Discrepancy #" . ($index + 1) . ":\n";
    echo "  ID: {$product->id}\n";
    echo "  Name: " . substr($product->name, 0, 50) . "...\n";
    echo "  Cost Price: " . ($product->cost_price ?? 'NULL') . "\n";
    echo "  Selling Price: " . ($product->selling_price ?? 'NULL') . "\n";
    echo "  Margin Status: {$marginStatus}\n";
    echo "  Matches Filter: " . (matchesMarginFilter($product) ? 'Yes' : 'No') . "\n";
    echo "  Details: " . getDiscrepancyDetails($product) . "\n\n";
});

function isBelowRequiredMargin(float $cost, float $selling): bool
{
    return $selling < (
        match (true) {
            $cost <= 5 => $cost * 2.5,
            $cost <= 10 => $cost * 2.0,
            $cost <= 20 => $cost * 1.8,
            $cost <= 50 => $cost * 1.6,
            $cost <= 100 => $cost * 1.5,
            $cost <= 200 => $cost * 1.4,
            $cost <= 500 => $cost * 1.3,
            default => $cost * 1.25,
        }
    );
}

function matchesMarginFilter($product): bool
{
    return (
        is_null($product->cost_price) ||
        is_null($product->selling_price) ||
        $product->selling_price < 0 ||
        $product->cost_price < 0 ||
        ($product->cost_price > 0 && $product->selling_price > 0 && isBelowRequiredMargin($product->cost_price, $product->selling_price))
    );
}

function getDiscrepancyDetails($product): string
{
    $cost = ProductResource::normalizeAmount($product->cost_price);
    $selling = ProductResource::normalizeAmount($product->selling_price);
    
    if ($cost === null || $selling === null) {
        return "Missing cost or selling price";
    }
    
    $pricing = PricingService::makeFromConfig();
    $min = $pricing->minSellingPrice($cost);
    
    if ($selling < $min) {
        $requiredMargin = (($min - $cost) / $cost) * 100;
        $actualMargin = (($selling - $cost) / $cost) * 100;
        return "Below required margin. Required: {$requiredMargin}%, Actual: {$actualMargin}%, Min Price: {$min}";
    }
    
    return "Unknown discrepancy";
}

echo "\n=== ANALYSIS COMPLETE ===\n";
