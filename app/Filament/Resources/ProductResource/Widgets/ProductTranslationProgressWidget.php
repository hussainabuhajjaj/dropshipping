<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Widgets;

use Filament\Widgets\Widget;

class ProductTranslationProgressWidget extends Widget
{
    protected string $view = 'filament.widgets.product-translation-progress-widget';

    protected int | string | array $columnSpan = 'full';
}

