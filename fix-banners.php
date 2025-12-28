#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StorefrontBanner;
use Illuminate\Support\Facades\DB;

echo "\n=== FIXING BANNER TARGET TYPES ===\n\n";

// Find banners with problematic target_type
$problematicBanners = StorefrontBanner::whereNotNull('target_type')
    ->where('target_type', '!=', 'none')
    ->get();

if ($problematicBanners->isEmpty()) {
    echo "✅ All banners already have correct target_type!\n";
} else {
    echo "Found {$problematicBanners->count()} banners with incorrect target_type:\n\n";
    
    foreach ($problematicBanners as $banner) {
        echo "Fixing Banner #{$banner->id}: {$banner->title}\n";
        echo "  Old target_type: '{$banner->target_type}'\n";
        
        $banner->target_type = 'none';
        $banner->save();
        
        echo "  ✅ New target_type: 'none'\n\n";
    }
    
    echo "✅ Updated {$problematicBanners->count()} banners!\n";
}

// Also ensure all are active with valid dates
echo "\n--- Activating All Banners ---\n";

$inactiveBanners = StorefrontBanner::where('is_active', false)->get();
if ($inactiveBanners->count() > 0) {
    foreach ($inactiveBanners as $banner) {
        $banner->is_active = true;
        $banner->save();
        echo "✅ Activated: {$banner->title}\n";
    }
}

// Fix date issues
$futureBanners = StorefrontBanner::where('starts_at', '>', now())->get();
if ($futureBanners->count() > 0) {
    foreach ($futureBanners as $banner) {
        $banner->starts_at = now();
        $banner->save();
        echo "✅ Fixed start date: {$banner->title}\n";
    }
}

$expiredBanners = StorefrontBanner::where('ends_at', '<', now())->get();
if ($expiredBanners->count() > 0) {
    foreach ($expiredBanners as $banner) {
        $banner->ends_at = now()->addMonths(3);
        $banner->save();
        echo "✅ Extended end date: {$banner->title}\n";
    }
}

echo "\n--- Final Check ---\n";
$visible = StorefrontBanner::active()
    ->where(function ($query) {
        $query->where('target_type', 'none')
            ->orWhereNull('target_type');
    })
    ->count();

echo "✅ {$visible} banners will now show on homepage!\n";

echo "\n=== DONE! Refresh your homepage ===\n\n";
