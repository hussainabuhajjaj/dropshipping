<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\GenerateProductSeoJob;
use App\Models\Product;

class ProductSeoObserver
{
    public function saved(Product $product): void
    {
        if (! $product->wasChanged(['name', 'description'])) {
            return;
        }

        GenerateProductSeoJob::dispatch((int) $product->id, 'en', false);
    }
}
