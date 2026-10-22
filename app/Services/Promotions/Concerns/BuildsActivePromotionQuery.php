<?php

declare(strict_types=1);

namespace App\Services\Promotions\Concerns;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Builder;

trait BuildsActivePromotionQuery
{
    protected function activePromotionsQuery(): Builder
    {
        $now = now();

        return Promotion::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            });
    }
}
