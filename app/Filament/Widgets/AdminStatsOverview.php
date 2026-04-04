<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\ProductReview;
use App\Models\ReturnRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $orders = Order::query();
        $gross = (float) Order::query()
            ->where('payment_status', 'paid')
            ->sum('grand_total');
        $pendingReviews = ProductReview::query()->where('status', 'pending')->count();
        $openReturns = ReturnRequest::query()->whereIn('status', ['requested', 'approved', 'received'])->count();

        return [
            Stat::make('Orders', (string) $orders->count())
                ->description('Total orders')
                ->color('primary')
                ->url(\App\Filament\Resources\OrderResource::getUrl()),
            Stat::make('Revenue', '$' . number_format($gross, 2))
                ->description('Paid orders')
                ->color('success')
                ->url(\App\Filament\Resources\OrderResource::getUrl()),
            Stat::make('Pending reviews', (string) $pendingReviews)
                ->description('Awaiting approval')
                ->color('warning')
                ->url(\App\Filament\Resources\ProductReviewResource::getUrl()),
            Stat::make('Open returns', (string) $openReturns)
                ->description('Need action')
                ->color('danger')
                ->url(\App\Filament\Resources\ReturnRequestResource::getUrl()),
        ];
    }
}
