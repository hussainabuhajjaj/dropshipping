<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ConversionFunnelWidget extends ChartWidget
{
    protected ?string $heading = 'Conversion Funnel (Last 30 Days)';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        // Product views - using a placeholder, you'd track this separately
        $productViews = Product::query()
            ->where('is_active', true)
            ->count() * 100; // Placeholder

        // Cart additions - count orders (proxy for add-to-cart)
        $cartAdditions = Order::query()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        // Checkout initiated
        $checkoutInitiated = Order::query()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->whereNotNull('placed_at')
            ->count();

        // Completed purchases
        $completedPurchases = Order::query()
            ->where('placed_at', '>=', $thirtyDaysAgo)
            ->where('payment_status', 'paid')
            ->count();

        $stages = [
            'Product Views' => $productViews,
            'Add to Cart' => $cartAdditions,
            'Checkout' => $checkoutInitiated,
            'Purchase' => $completedPurchases,
        ];

        $conversionRates = [];
        $previousValue = $productViews;
        foreach ($stages as $stage => $value) {
            if ($previousValue > 0) {
                $rate = round(($value / $previousValue) * 100, 1);
                $conversionRates[$stage] = "{$value} ({$rate}%)";
            } else {
                $conversionRates[$stage] = "{$value}";
            }
            $previousValue = $value;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Visitors',
                    'data' => array_values($stages),
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.5)',
                        'rgba(16, 185, 129, 0.5)',
                        'rgba(251, 191, 36, 0.5)',
                        'rgba(34, 197, 94, 0.7)',
                    ],
                ],
            ],
            'labels' => array_keys($stages),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
