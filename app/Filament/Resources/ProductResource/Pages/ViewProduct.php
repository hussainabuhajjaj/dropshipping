<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Models\Product;
use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\ViewRecord;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    public function getTitle(): string
    {
        return 'Product Details';
    }

    public function getSubheading(): ?string
    {
        /** @var Product|null $record */
        $record = $this->getRecord();

        if (! $record) {
            return null;
        }

        $source = $this->sourceLabel($record);
        $identifier = $record->cj_pid ? "CJ PID: {$record->cj_pid}" : "ID: {$record->id}";

        return "{$source} | {$identifier}";
    }

    private function sourceLabel(Product $record): string
    {
        if ($record->cj_pid) {
            return 'CJ';
        }

        $attributes = is_array($record->attributes) ? $record->attributes : [];
        if (($attributes['ali_item_id'] ?? null) || (($attributes['supplier_code'] ?? null) === 'ae')) {
            return 'AliExpress';
        }

        return 'Local';
    }
}
