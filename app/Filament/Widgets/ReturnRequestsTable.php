<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ReturnRequest;
use App\Filament\Resources\ReturnRequestResource;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class ReturnRequestsTable extends BaseWidget
{
    protected static ?string $heading = 'Open Return Requests';
    protected int|string|array $columnSpan = 'full';

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return 'return-request:' . ($record['id'] ?? md5(json_encode($record)));
        }

        return 'return-request:' . ($record->getKey() ?? spl_object_id($record));
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return ReturnRequest::query()
            ->with(['order', 'orderItem', 'customer'])
            ->whereIn('status', ['requested', 'approved', 'received'])
            ->latest();
    }

    protected function getTableRecordUrl($record): ?string
    {
        return ReturnRequestResource::getUrl('edit', ['record' => $record]);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('order.number')->label('Order #')->searchable(),
            Tables\Columns\TextColumn::make('customer.email')->label('Customer')->limit(30),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('reason')->limit(40),
            Tables\Columns\TextColumn::make('created_at')->since()->label('Requested'),
        ];
    }
}
