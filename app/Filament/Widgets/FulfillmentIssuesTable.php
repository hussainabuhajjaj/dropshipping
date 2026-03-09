<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Orders\Models\OrderItem;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Filament\Resources\OrderResource;

class FulfillmentIssuesTable extends BaseWidget
{
    protected static ?string $heading = 'Fulfillment Issues';
    protected int|string|array $columnSpan = 'full';

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return 'fulfillment-issue:' . ($record['id'] ?? md5(json_encode($record)));
        }

        return 'fulfillment-issue:' . ($record->getKey() ?? spl_object_id($record));
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return OrderItem::query()
            ->with(['order', 'productVariant.product', 'fulfillmentProvider'])
            ->whereIn('fulfillment_status', ['failed', 'needs_action'])
            ->latest();
    }

    protected function getTableRecordUrl($record): ?string
    {
        return $record->order_id
            ? OrderResource::getUrl('view', ['record' => $record->order_id])
            : null;
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('order.number')->label('Order #')->searchable(),
            Tables\Columns\TextColumn::make('productVariant.product.name')->label('Product')->limit(30),
            Tables\Columns\TextColumn::make('fulfillmentProvider.name')->label('Provider')->limit(20),
            Tables\Columns\TextColumn::make('fulfillment_status')->badge(),
            Tables\Columns\TextColumn::make('updated_at')->since()->label('Updated'),
        ];
    }
}
