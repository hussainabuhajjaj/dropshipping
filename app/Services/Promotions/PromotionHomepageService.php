<?php

namespace App\Services\Promotions;

use Illuminate\Support\Arr;

class PromotionHomepageService
{
    /**
     * Get featured/flash promotions for homepage display.
     *
     * @return array
     */
    public function getHomepagePromotions(): array
    {
        $limit = (int) (config('promotions.display_limits.home') ?? 5);
        return app(PromotionDisplayService::class)->getForPlacement('home', [], [], $limit);
    }

    /**
     * Get active promotions that target any of the given products/categories.
     *
     * Intended for product/category listing & detail pages (visibility + badge logic).
     * NOTE: Currently unused. Keep in sync with PromotionDisplayService::getForPlacement()
     * if reintroduced to avoid drift in display logic.
     */
    public function getPromotionsForTargets(array $productIds = [], array $categoryIds = [], int $limit = 200): array
    {
        $limit = $limit > 0 ? $limit : 200;
        return app(PromotionDisplayService::class)->getForPlacement('product', Arr::wrap($productIds), Arr::wrap($categoryIds), $limit);
    }

    public function getPromotionsForPlacement(string $placement, array $productIds = [], array $categoryIds = [], ?int $limit = null): array
    {
        $limits = config('promotions.display_limits', []);
        $resolvedLimit = $limit ?? (int) ($limits[$placement] ?? 5);

        return app(PromotionDisplayService::class)->getForPlacement($placement, Arr::wrap($productIds), Arr::wrap($categoryIds), $resolvedLimit);
    }
}
