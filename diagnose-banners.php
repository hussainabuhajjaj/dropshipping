#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StorefrontBanner;
use Illuminate\Support\Facades\DB;

echo "\n=== BANNER DIAGNOSIS ===\n\n";

// Step 1: Total count
$total = StorefrontBanner::count();
echo "📊 Total banners: {$total}\n";

if ($total === 0) {
    echo "\n❌ NO BANNERS FOUND!\n";
    echo "Run: php artisan db:seed --class=StorefrontBannerSeeder\n";
    exit(1);
}

// Step 2: Active count
$active = StorefrontBanner::active()->count();
echo "✅ Active banners: {$active}\n\n";

// Step 3: Detailed breakdown
echo "--- Breakdown by Display Type (ALL) ---\n";
$byType = DB::table('storefront_banners')
    ->select('display_type', DB::raw('count(*) as count'))
    ->groupBy('display_type')
    ->get();

foreach ($byType as $row) {
    echo "  {$row->display_type}: {$row->count}\n";
}

echo "\n--- Target Type Breakdown ---\n";
$byTarget = DB::table('storefront_banners')
    ->select(DB::raw('COALESCE(target_type, "NULL") as target_type'), DB::raw('count(*) as count'))
    ->groupBy('target_type')
    ->get();

foreach ($byTarget as $row) {
    echo "  {$row->target_type}: {$row->count}\n";
}

// Step 4: Check what HomeController would see
echo "\n--- What HomeController Sees (target_type='none' OR NULL) ---\n";

$heroCount = StorefrontBanner::active()
    ->byDisplayType('hero')
    ->where(function ($query) {
        $query->where('target_type', 'none')
            ->orWhereNull('target_type');
    })
    ->count();
echo "Hero banners: {$heroCount}\n";

$carouselCount = StorefrontBanner::active()
    ->byDisplayType('carousel')
    ->where(function ($query) {
        $query->where('target_type', 'none')
            ->orWhereNull('target_type');
    })
    ->count();
echo "Carousel banners: {$carouselCount}\n";

$stripCount = StorefrontBanner::active()
    ->byDisplayType('strip')
    ->where(function ($query) {
        $query->where('target_type', 'none')
            ->orWhereNull('target_type');
    })
    ->count();
echo "Strip banners: {$stripCount}\n";

// Step 5: Show problem banners
echo "\n--- Problem Banners (Won't Show on Homepage) ---\n";
$problems = StorefrontBanner::where(function ($query) {
    $query->where('is_active', false)
        ->orWhere('starts_at', '>', now())
        ->orWhere('ends_at', '<', now())
        ->orWhere(function ($q) {
            $q->whereNotNull('target_type')
                ->where('target_type', '!=', 'none');
        });
})->get();

if ($problems->isEmpty()) {
    echo "✅ No problem banners found!\n";
} else {
    foreach ($problems as $banner) {
        echo "\n🚨 Banner #{$banner->id}: {$banner->title}\n";
        echo "   Display Type: {$banner->display_type}\n";
        
        if (!$banner->is_active) {
            echo "   ❌ NOT ACTIVE (is_active=false)\n";
        }
        if ($banner->starts_at && $banner->starts_at > now()) {
            echo "   ❌ STARTS IN FUTURE ({$banner->starts_at})\n";
        }
        if ($banner->ends_at && $banner->ends_at < now()) {
            echo "   ❌ ALREADY ENDED ({$banner->ends_at})\n";
        }
        if ($banner->target_type && $banner->target_type !== 'none') {
            echo "   ❌ HAS TARGET_TYPE ('{$banner->target_type}' - should be 'none' or NULL for homepage)\n";
        }
    }
}

echo "\n--- SOLUTION ---\n";
if ($heroCount === 0 && $carouselCount === 0 && $stripCount === 0) {
    echo "❌ NO BANNERS WILL SHOW ON HOMEPAGE!\n\n";
    echo "Your banners likely have target_type set to something other than 'none' or NULL.\n";
    echo "\nTo fix, run ONE of these:\n";
    echo "1. .\seed-banners.bat  (clear all and reseed)\n";
    echo "2. php fix-banners.php  (update existing banners)\n";
} else {
    echo "✅ Banners should be showing! Check browser console and network tab.\n";
}

echo "\n=== END DIAGNOSIS ===\n\n";
