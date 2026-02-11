<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\StorefrontBanner;
use App\Models\StorefrontCampaign;
use App\Models\StorefrontCollection;
use App\Services\Promotions\PromotionDisplayService;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    use TransformsProducts;

    public function show(StorefrontCampaign $campaign): Response
    {
        $locale = app()->getLocale();
        abort_if(! $campaign->isActiveForLocale($locale), 404);

        $promotionDisplay = app(PromotionDisplayService::class);
        $promotions = Promotion::query()
            ->whereIn('id', $campaign->promotionIds())
            ->with(['targets', 'conditions'])
            ->orderBy('priority', 'desc')
            ->get()
            ->map(fn (Promotion $promo) => $promotionDisplay->serializePromotion($promo))
            ->values();

        $coupons = Coupon::query()
            ->whereIn('id', $campaign->couponIds())
            ->get()
            ->map(fn (Coupon $coupon) => $this->transformCoupon($coupon, $locale))
            ->values();

        $banners = StorefrontBanner::query()
            ->whereIn('id', $campaign->bannerIds())
            ->get()
            ->map(fn (StorefrontBanner $banner) => $this->transformBanner($banner))
            ->values();

        $collections = StorefrontCollection::query()
            ->whereIn('id', $campaign->collectionIds())
            ->get()
            ->map(fn (StorefrontCollection $collection) => $this->transformCollectionSummary($collection, $locale))
            ->values();

        return Inertia::render('Campaigns/Show', [
            'campaign' => $this->transformCampaign($campaign, $locale),
            'promotions' => $promotions,
            'coupons' => $coupons,
            'banners' => $banners,
            'collections' => $collections,
        ]);
    }

    private function transformCoupon(Coupon $coupon, ?string $locale): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'amount' => $coupon->amount,
            'min_order_total' => $coupon->min_order_total,
            'description' => $coupon->localizedValue('description', $locale) ?? $coupon->description,
            'starts_at' => $coupon->starts_at,
            'ends_at' => $coupon->ends_at,
        ];
    }

    private function transformCampaign(StorefrontCampaign $campaign, ?string $locale): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->localizedValue('name', $locale),
            'slug' => $campaign->slug,
            'type' => $campaign->type,
            'status' => $campaign->status,
            'stacking_mode' => $campaign->stacking_mode,
            'exclusive_group' => $campaign->exclusive_group,
            'hero_kicker' => $campaign->localizedValue('hero_kicker', $locale),
            'hero_subtitle' => $campaign->localizedValue('hero_subtitle', $locale),
            'hero_image' => $campaign->hero_image ? $this->resolveImagePath($campaign->hero_image) : null,
            'theme' => $campaign->theme ?? [],
            'placements' => $campaign->placements ?? [],
            'content' => $campaign->localizedValue('content', $locale),
            'starts_at' => $campaign->starts_at,
            'ends_at' => $campaign->ends_at,
        ];
    }

    private function transformCollectionSummary(StorefrontCollection $collection, ?string $locale): array
    {
        return [
            'id' => $collection->id,
            'title' => $collection->localizedValue('title', $locale),
            'slug' => $collection->slug,
            'type' => $collection->type,
            'description' => $collection->localizedValue('description', $locale),
            'hero_kicker' => $collection->localizedValue('hero_kicker', $locale),
            'hero_subtitle' => $collection->localizedValue('hero_subtitle', $locale),
            'hero_image' => $collection->hero_image ? $this->resolveImagePath($collection->hero_image) : null,
        ];
    }

    private function transformBanner(StorefrontBanner $banner): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $banner->id,
            'title' => $banner->localizedValue('title', $locale),
            'description' => $banner->localizedValue('description', $locale),
            'imagePath' => $this->resolveImagePath($banner->image_path),
            'badgeText' => $banner->localizedValue('badge_text', $locale),
            'ctaText' => $banner->localizedValue('cta_text', $locale),
            'ctaUrl' => $banner->getCtaUrl(),
            'backgroundColor' => $banner->background_color,
            'textColor' => $banner->text_color,
        ];
    }

    private function resolveImagePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url(\Storage::url($path));
    }
}
