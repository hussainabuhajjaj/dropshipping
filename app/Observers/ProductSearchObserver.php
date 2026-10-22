<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Product;
use App\Services\Search\TypesenseSearchService;

class ProductSearchObserver
{
    public function saved(Product $product): void
    {
        if (! config('typesense.enabled')) {
            return;
        }

        app(TypesenseSearchService::class)->upsert($product);
    }

    public function deleted(Product $product): void
    {
        if (! config('typesense.enabled')) {
            return;
        }

        app(TypesenseSearchService::class)->delete($product->id);
    }
}
