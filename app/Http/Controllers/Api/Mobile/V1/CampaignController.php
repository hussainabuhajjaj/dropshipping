<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\StorefrontBanner;
use App\Models\StorefrontCampaign;
use App\Models\StorefrontCollection;
use App\Services\Promotions\PromotionDisplayService;
use Illuminate\Http\JsonResponse;

class CampaignController extends ApiController
{
    public function show(string $slug): JsonResponse
    {
        $campaign = StorefrontCampaign::query()->where('slug', $slug)->first();
        $locale = app()->getLocale();

        if (! $campaign || ! $campaign->isActiveForLocale($locale)) {
            return $this->notFound('Campaign not found');
        }

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

        return $this->success([
            'campaign' => $this->transformCampaign($campaign, $locale),
            'promotions' => $promotions,
            'coupons' => $coupons,
            'banners' => $banners,
            'collections' => $collections,
        ]);
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
            'starts_at' => $campaign->starts_at?->toIso8601String(),
            'ends_at' => $campaign->ends_at?->toIso8601String(),
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

    private function transformCoupon(Coupon $coupon, ?string $locale): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'amount' => $coupon->amount,
            'min_order_total' => $coupon->min_order_total,
            'description' => $coupon->localizedValue('description', $locale) ?? $coupon->description,
            'starts_at' => $coupon->starts_at?->toIso8601String(),
            'ends_at' => $coupon->ends_at?->toIso8601String(),
        ];
    }

    private function transformBanner(StorefrontBanner $banner): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $banner->id,
            'title' => $banner->localizedValue('title', $locale),
            'description' => $banner->localizedValue('description', $locale),
            'image_path' => $this->resolveImagePath($banner->image_path),
            'badge_text' => $banner->localizedValue('badge_text', $locale),
            'cta_text' => $banner->localizedValue('cta_text', $locale),
            'cta_url' => $banner->getCtaUrl(),
            'background_color' => $banner->background_color,
            'text_color' => $banner->text_color,
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
