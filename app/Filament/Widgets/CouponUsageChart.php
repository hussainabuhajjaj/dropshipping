<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\CouponRedemption;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class CouponUsageChart extends ChartWidget
{
    protected ?string $heading = 'Coupon Usage (30d)';

    protected function getData(): array
    {
        $start = Carbon::now()->subDays(29)->startOfDay();

        $rows = CouponRedemption::query()
            ->selectRaw('DATE(redeemed_at) as day, COUNT(*) as total')
            ->whereNotNull('redeemed_at')
            ->where('redeemed_at', '>=', $start)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $labels = [];
        $values = [];
        $byDay = $rows->keyBy('day');
        $cursor = $start->copy();
        while ($cursor->lte(Carbon::now())) {
            $day = $cursor->toDateString();
            $labels[] = $cursor->format('M d');
            $values[] = (int) ($byDay[$day]->total ?? 0);
            $cursor->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Redemptions',
                    'data' => $values,
                    'backgroundColor' => '#f97316',
                    'borderColor' => '#f97316',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
