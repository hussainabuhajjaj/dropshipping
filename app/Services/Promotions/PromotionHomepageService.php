<?php

namespace App\Services\Promotions;

use App\Models\Promotion;
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
        $now = now();
        $promotions = Promotion::where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->whereIn('type', ['flash_sale', 'auto_discount'])
            ->orderByDesc('priority')
            ->with(['targets', 'conditions'])
            ->take(5)
            ->get();

        return $promotions->map(fn (Promotion $promo) => $this->serializePromotion($promo))->toArray();
    }

    /**
     * Get active promotions that target any of the given products/categories.
     *
     * Intended for product/category listing & detail pages (visibility + badge logic).
     */
    public function getPromotionsForTargets(array $productIds = [], array $categoryIds = [], int $limit = 200): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', Arr::wrap($productIds)))));
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', Arr::wrap($categoryIds)))));

        $now = now();

        $query = Promotion::where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->orderByDesc('priority')
            ->with(['targets', 'conditions']);

        if ($productIds !== [] || $categoryIds !== []) {
            $query->whereHas('targets', function ($targets) use ($productIds, $categoryIds) {
                $targets->where(function ($inner) use ($productIds, $categoryIds) {
                    if ($productIds !== []) {
                        $inner->orWhere(function ($q) use ($productIds) {
                            $q->where('target_type', 'product')->whereIn('target_id', $productIds);
                        });
                    }

                    if ($categoryIds !== []) {
                        $inner->orWhere(function ($q) use ($categoryIds) {
                            $q->where('target_type', 'category')->whereIn('target_id', $categoryIds);
                        });
                    }
                });
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (Promotion $promo) => $this->serializePromotion($promo))->toArray();
    }

    private function serializePromotion(Promotion $promo): array
    {
        return [
            'id' => $promo->id,
            'name' => $promo->name,
            'description' => $promo->description,
            'type' => $promo->type,
            'value_type' => $promo->value_type,
            'value' => $promo->value,
            'start_at' => $promo->start_at,
            'end_at' => $promo->end_at,
            'targets' => $promo->targets,
            'conditions' => $promo->conditions,
        ];
    }
}
