<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
// use Filament\Resources\Pages\ListRecords\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),
            'fulfilling' => Tab::make('Fulfilling')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'fulfilling')),
            'failed' => Tab::make('Failed')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->whereIn('status', ['cancelled', 'refunded'])
                        ->orWhereHas('orderItems', function (Builder $sub) {
                            $sub->where('fulfillment_status', 'failed');
                        });
                }),
        ];
    }
}
