<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\Coupon;
use App\Models\GiftCard;
use App\Models\Promotion;
use App\Models\StorefrontBanner;
use App\Models\StorefrontCampaign;
use App\Models\StorefrontCollection;
use App\Services\Promotions\PromotionDisplayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends ApiController
{
    public function show(Request $request, string $slug): JsonResponse
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

        $payload = [
            'campaign' => $this->transformCampaign($campaign, $locale),
            'promotions' => $promotions,
            'coupons' => $coupons,
            'banners' => $banners,
            'collections' => $collections,
        ];

        if ($campaign->isLuckyDraw()) {
            $payload['lucky_draw'] = $campaign->luckyDrawPayload($locale, $request->user()?->id);
        }

        return $this->success($payload);
    }

    public function myEntry(Request $request, string $slug): JsonResponse
    {
        $campaign = StorefrontCampaign::query()->where('slug', $slug)->first();
        $customer = $request->user();

        if (! $campaign || ! $campaign->isLuckyDraw()) {
            return $this->notFound('Campaign not found');
        }

        if (! $customer) {
            return $this->unauthorized();
        }

        $participation = $campaign->participations()
            ->where('customer_id', $customer->id)
            ->with(['winner'])
            ->first();

        if (! $participation) {
            return $this->success(['entry' => null, 'eligible' => false]);
        }

        return $this->success([
            'eligible' => true,
            'entry' => $this->transformEntry($participation),
        ]);
    }

    public function winners(Request $request, string $slug): JsonResponse
    {
        $campaign = StorefrontCampaign::query()->where('slug', $slug)->first();

        if (! $campaign || ! $campaign->isLuckyDraw()) {
            return $this->notFound('Campaign not found');
        }

        $winners = $campaign->winners()
            ->with('customer:id,first_name,last_name')
            ->orderByRaw("FIELD(prize_type, 'grand', 'runner_up', 'guaranteed')")
            ->latest('announced_at')
            ->limit(100)
            ->get();

        return $this->success([
            'winners' => $winners->map(function ($winner) {
                return [
                    'id' => $winner->id,
                    'prize_type' => $winner->prize_type,
                    'prize_label' => $winner->prize_label,
                    'name' => $winner->customer ? trim($winner->customer->first_name . ' ' . $winner->customer->last_name) : null,
                    'status' => $winner->status,
                    'announced_at' => $winner->announced_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function myRewards(Request $request, string $slug): JsonResponse
    {
        $campaign = StorefrontCampaign::query()->where('slug', $slug)->first();
        $customer = $request->user();

        if (! $campaign || ! $campaign->isLuckyDraw()) {
            return $this->notFound('Campaign not found');
        }

        if (! $customer) {
            return $this->unauthorized();
        }

        $participations = $campaign->participations()
            ->where('customer_id', $customer->id)
            ->whereNotNull('reward_code')
            ->orderByDesc('reward_issued_at')
            ->get();

        $codes = $participations->pluck('reward_code')->filter()->values()->all();

        $coupons = Coupon::query()
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->get()
            ->map(fn (Coupon $coupon) => $this->transformRewardCoupon($coupon))
            ->keyBy('code');

        $giftCards = GiftCard::query()
            ->whereIn('code', $codes)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()
            ->map(fn (GiftCard $card) => [
                'code' => $card->code,
                'type' => 'store_credit',
                'value' => (float) $card->balance,
                'currency' => $card->currency,
                'expires_at' => $card->expires_at?->toIso8601String(),
            ])
            ->keyBy('code');

        return $this->success([
            'rewards' => $participations->map(function ($participation) use ($coupons, $giftCards) {
                $code = $participation->reward_code;

                return [
                    'participation_id' => $participation->id,
                    'spot_number' => $participation->spot_number,
                    'reward_code' => $code,
                    'reward_issued_at' => $participation->reward_issued_at?->toIso8601String(),
                    'reward' => $coupons->get($code) ?? $giftCards->get($code) ?? null,
                ];
            })->values(),
        ]);
    }

    private function transformEntry($participation): array
    {
        $winner = $participation->winner;

        return [
            'id' => $participation->id,
            'spot_number' => $participation->spot_number,
            'state' => $participation->state,
            'qualified_at' => $participation->qualified_at?->toIso8601String(),
            'reward_code' => $participation->reward_code,
            'reward_issued_at' => $participation->reward_issued_at?->toIso8601String(),
            'is_winner' => $winner !== null,
            'prize_type' => $winner?->prize_type,
            'prize_label' => $winner?->prize_label,
            'prize_status' => $winner?->status,
        ];
    }

    private function transformRewardCoupon(Coupon $coupon): array
    {
        return [
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => (float) $coupon->amount,
            'min_order_total' => $coupon->min_order_total,
            'expires_at' => $coupon->ends_at?->toIso8601String(),
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
