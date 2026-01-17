<?php

declare(strict_types=1);

namespace App\Infrastructure\Fulfillment\Clients\CJ;

use App\Services\Api\ApiResponse;

class CjProductApi extends CjBaseApi
{
    public function listCategories(): ApiResponse
    {
        return $this->client()->get('/v1/product/getCategory');
    }

    public function listProductsV2(array $filters = []): ApiResponse
    {
        $params = array_filter([
            'pageNum' => $filters['pageNum'] ?? null,
            'pageSize' => $filters['pageSize'] ?? null,
            'categoryId' => $filters['categoryId'] ?? null,
            'productSku' => $filters['productSku'] ?? null,
            'productName' => $filters['productName'] ?? null,
            'materialKey' => $filters['materialKey'] ?? null,
            'storeProductId' => $filters['storeProductId'] ?? null,
            'warehouseId' => $filters['warehouseId'] ?? null,
            'haveStock' => $filters['haveStock'] ?? null,
            'sort' => $filters['sort'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->client()->get('/v1/product/listV2', $params);
    }

    public function listGlobalWarehouses(): ApiResponse
    {
        return $this->client()->get('/v1/product/globalWarehouse/list');
    }

    public function getWarehouseDetail(string $id): ApiResponse
    {
        return $this->client()->get('/v1/warehouse/detail', ['id' => $id]);
    }

    public function listProducts(array $filters = []): ApiResponse
    {
        $params = array_filter([
            'pageNum' => $filters['pageNum'] ?? null,
            'pageSize' => $filters['pageSize'] ?? null,
            'categoryId' => $filters['categoryId'] ?? null,
            'productSku' => $filters['productSku'] ?? null,
            'productName' => $filters['productName'] ?? null,
            'materialKey' => $filters['materialKey'] ?? null,
            'warehouseId' => $filters['warehouseId'] ?? null,
            'haveStock' => $filters['haveStock'] ?? null,
            'sort' => $filters['sort'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->client()->get('/v1/product/list', $params);
    }

    public function getProduct(string $pid): ApiResponse
    {
        return $this->getProductBy(['pid' => $pid]);
    }

    public function getProductBy(array $criteria): ApiResponse
    {
        $params = array_filter([
            'pid' => $criteria['pid'] ?? null,
            'productSku' => $criteria['productSku'] ?? null,
            'variantSku' => $criteria['variantSku'] ?? null,
            'featured' => ['enable_description', 'enable_category','enable_inventories','enable_combine','enable_video'],
        ], fn ($v) => $v !== null && $v !== '');

        return $this->client()->get('/v1/product/query', $params);
    }

    /**
     * Search products (POST /v1/product/search)
     */
    public function searchProducts(array $filters = []): ApiResponse
    {
        $payload = array_filter([
            'keyword' => $filters['keyword'] ?? null,
            'pageNum' => $filters['pageNum'] ?? null,
            'pageSize' => $filters['pageSize'] ?? null,
            'categoryId' => $filters['categoryId'] ?? null,
            'minPrice' => $filters['minPrice'] ?? null,
            'maxPrice' => $filters['maxPrice'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->client()->post('/v1/product/search', $payload);
    }

    public function productDetail(string $pid): ApiResponse
    {
        return $this->client()->post('/v1/product/detail', ['pid' => $pid]);
    }

    /**
     * Get price information by product id (pid).
     */
    public function getPriceByPid(string $pid): ApiResponse
    {
        return $this->client()->get('/v1/product/price/queryByPid', ['pid' => $pid]);
    }

    /**
     * Get price information by SKU.
     */
    public function getPriceBySku(string $sku): ApiResponse
    {
        return $this->client()->get('/v1/product/price/queryBySku', ['sku' => $sku]);
    }

    /**
     * Get price information by variant id (vid).
     */
    public function getPriceByVid(string $vid): ApiResponse
    {
        return $this->client()->get('/v1/product/price/queryByVid', ['vid' => $vid]);
    }

    /**
     * Search variants with filters (POST /v1/product/variant/search)
     */
    public function searchVariants(array $filters = []): ApiResponse
    {
        $payload = array_filter([
            'pid' => $filters['pid'] ?? null,
            'keyword' => $filters['keyword'] ?? null,
            'pageNum' => $filters['pageNum'] ?? null,
            'pageSize' => $filters['pageSize'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->client()->post('/v1/product/variant/search', $payload);
    }

    public function addToMyProducts(string $pid): ApiResponse
    {
        return $this->client()->post('/v1/product/addMyProduct', ['pid' => $pid]);
    }

    public function listMyProducts(array $filters = []): ApiResponse
    {
        $params = array_filter([
            'pageNum' => $filters['pageNum'] ?? null,
            'pageSize' => $filters['pageSize'] ?? null,
            'productSku' => $filters['productSku'] ?? null,
            'productName' => $filters['productName'] ?? null,
            'storeProductId' => $filters['storeProductId'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        // Use the documented My Product endpoint per CJ docs (myProduct/query)
        // See: https://developers.cjdropshipping.com/api2.0/v1/product/myProduct/query
        return $this->client()->get('/v1/product/myProduct/query', $params);
    }

    public function getVariantsByPid(string $pid): ApiResponse
    {
        return $this->client()->get('/v1/product/variant/query', ['pid' => $pid]);
    }

    public function getVariantByVid(string $vid): ApiResponse
    {
        return $this->client()->get('/v1/product/variant/queryByVid', ['vid' => $vid]);
    }

    public function getStockByVid(string $vid): ApiResponse
    {
        return $this->client()->get('/v1/product/stock/queryByVid', ['vid' => $vid]);
    }

    public function getStockBySku(string $sku): ApiResponse
    {
        return $this->client()->get('/v1/product/stock/queryBySku', ['sku' => $sku]);
    }

    public function getStockByPid(string $pid): ApiResponse
    {
        return $this->client()->get('/v1/product/stock/queryByPid', ['pid' => $pid]);
    }

    public function getProductReviews(string $pid, int $pageNum = 1, int $pageSize = 20, ?int $score = null): ApiResponse
    {
        $params = array_filter([
            'pid' => $pid,
            'score' => $score,
            'pageNum' => $pageNum,
            'pageSize' => $pageSize,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->client()->get('/v1/product/productComments', $params);
    }

    public function createSourcing(string $productUrl, ?string $note = null, ?string $sourceId = null): ApiResponse
    {
        $payload = array_filter([
            'productUrl' => $productUrl,
            'note' => $note,
            'sourceId' => $sourceId,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->client()->post('/v1/product/sourcing/create', $payload);
    }

    public function querySourcing(?string $sourcingId = null, int $pageNum = 1, int $pageSize = 20): ApiResponse
    {
        $payload = array_filter([
            'sourcingId' => $sourcingId,
            'pageNum' => $pageNum,
            'pageSize' => $pageSize,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->client()->post('/v1/product/sourcing/query', $payload);
    }
}
