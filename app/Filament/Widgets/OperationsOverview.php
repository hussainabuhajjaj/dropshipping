<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $pendingOrders = Order::query()->where('status', 'pending')->count();
        $fulfillmentIssues = OrderItem::query()
            ->whereIn('fulfillment_status', ['failed', 'needs_action'])
            ->count();
        $paymentIssues = Payment::query()->whereIn('status', ['failed', 'pending'])->count();

        return [
            Stat::make('Orders pending', (string) $pendingOrders)
                ->url(\App\Filament\Resources\OrderResource::getUrl()),
            Stat::make('Fulfillment issues', (string) $fulfillmentIssues)
                ->url(\App\Filament\Resources\OrderResource::getUrl()),
            Stat::make('Payment issues', (string) $paymentIssues)
                ->url(\App\Filament\Resources\PaymentResource::getUrl()),
        ];
    }
}
