<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\CJCatalog;
use App\Services\Api\ApiResponse;
use Tests\TestCase;

class CJCatalogFiltersTest extends TestCase
{
    public function test_filters_are_passed_to_cj_api(): void
    {
        $fake = new class() {
            public array $lastFilters = [];

            public function listProducts(array $filters = []): ApiResponse
            {
                $this->lastFilters = $filters;
                $pageNum = $filters['pageNum'] ?? 1;
                $pageSize = $filters['pageSize'] ?? 24;
                return ApiResponse::success([
                    'productList' => [],
                    'pageNum' => $pageNum,
                    'pageSize' => $pageSize,
                    'total' => 0,
                ]);
            }
        };

        // Bind fake client to the infrastructure client used by CJCatalog
        $this->app->instance(\App\Infrastructure\Fulfillment\Clients\CJDropshippingClient::class, $fake);

        $page = new CJCatalog();
        // Set filters
        $page->pageNum = 2;
        $page->pageSize = 50;
        $page->categoryId = '100200';
        $page->productSku = 'SKU-123';
        $page->productName = 'Widget';
        $page->materialKey = 'metal';
        $page->warehouseId = 'WH1';
        $page->inStockOnly = true;
        $page->sort = '1'; // Price: Low to High

        // Execute fetch
        $page->fetch();

        // Assert mapping
        $filters = $fake->lastFilters;
        $this->assertSame(2, $filters['pageNum']);
        $this->assertSame(50, $filters['pageSize']);
        $this->assertSame('100200', $filters['categoryId']);
        $this->assertSame('SKU-123', $filters['productSku']);
        $this->assertSame('Widget', $filters['productName']);
        $this->assertSame('metal', $filters['materialKey']);
        $this->assertSame('WH1', $filters['warehouseId']);
        $this->assertSame(1, $filters['haveStock']);
        $this->assertSame('1', $filters['sort']);
    }
}
