<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\Product;
use Filament\Widgets\Widget;

class ProductCountWidget extends Widget
{
    protected string $view = 'filament.widgets.product-count-widget';

    public function getViewData(): array
    {
        return [
            'count' => Product::count(),
        ];
    }
}
