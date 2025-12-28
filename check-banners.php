<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StorefrontBanner;

echo "=== BANNER DEBUG INFO ===\n\n";

$totalBanners = StorefrontBanner::count();
echo "Total banners in database: {$totalBanners}\n";

$activeBanners = StorefrontBanner::active()->count();
echo "Active banners: {$activeBanners}\n\n";

if ($totalBanners === 0) {
    echo "❌ No banners found in database!\n";
    echo "\nYou need to create banners in the admin panel.\n";
    echo "Go to: Admin Panel -> Storefront -> Banners\n";
} else {
    echo "--- Banner Breakdown by Display Type ---\n";
    $heroBanners = StorefrontBanner::active()->byDisplayType('hero')->count();
    echo "Hero banners: {$heroBanners}\n";
    
    $carouselBanners = StorefrontBanner::active()->byDisplayType('carousel')->count();
    echo "Carousel banners: {$carouselBanners}\n";
    
    $stripBanners = StorefrontBanner::active()->byDisplayType('strip')->count();
    echo "Strip banners: {$stripBanners}\n";
    
    $sidebarBanners = StorefrontBanner::active()->byDisplayType('sidebar')->count();
    echo "Sidebar banners: {$sidebarBanners}\n\n";
    
    if ($activeBanners === 0) {
        echo "⚠️  You have banners, but none are active or within valid date range!\n\n";
        
        $allBanners = StorefrontBanner::all();
        foreach ($allBanners as $banner) {
            echo "Banner #{$banner->id}: {$banner->title}\n";
            echo "  - Active: " . ($banner->is_active ? 'YES' : 'NO') . "\n";
            echo "  - Display Type: {$banner->display_type}\n";
            echo "  - Starts: " . ($banner->starts_at ? $banner->starts_at->format('Y-m-d H:i') : 'N/A') . "\n";
            echo "  - Ends: " . ($banner->ends_at ? $banner->ends_at->format('Y-m-d H:i') : 'N/A') . "\n";
            echo "  - Target Type: " . ($banner->target_type ?? 'none') . "\n";
            echo "\n";
        }
    } else {
        echo "✅ Active banners found!\n\n";
        
        $banners = StorefrontBanner::active()->get();
        foreach ($banners as $banner) {
            echo "✓ Banner #{$banner->id}: {$banner->title}\n";
            echo "  - Display Type: {$banner->display_type}\n";
            echo "  - Target Type: " . ($banner->target_type ?? 'none') . "\n";
            echo "  - Image: " . ($banner->image_path ? 'YES' : 'NO') . "\n";
            echo "\n";
        }
    }
}

echo "\n=== END DEBUG INFO ===\n";
