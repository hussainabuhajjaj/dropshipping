@props(['categoryId' => null, 'productId' => null, 'displayType' => null])

@php
use App\Models\StorefrontBanner;

$query = StorefrontBanner::active();

if ($displayType) {
    $query->byDisplayType($displayType);
}

// Handle targeting logic
if ($categoryId) {
    $query->forCategory($categoryId);
} elseif ($productId) {
    $query->forProduct($productId);
} else {
    // Show banners with target_type 'none' or NULL for general display
    $query->where(function($q) {
        $q->where('target_type', 'none')
          ->orWhereNull('target_type');
    });
}

$banners = $query->orderBy('display_order')->get();
@endphp

@if($banners->isNotEmpty())
    @foreach($banners as $banner)
        @if($banner->display_type === 'hero')
            <x-storefront.banner-hero :banner="$banner" />
        @elseif($banner->display_type === 'sidebar')
            <x-storefront.banner-sidebar :banner="$banner" />
        @elseif($banner->display_type === 'strip')
            <x-storefront.banner-strip :banner="$banner" />
        @endif
    @endforeach
    
    @if($displayType === 'carousel')
        <x-storefront.banner-carousel :banners="$banners" />
    @endif
@endif
