<?php

namespace App\Services\Promotions;

use App\Models\Promotion;

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

        return $promotions->map(function ($promo) {
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
        })->toArray();
    }
}
