<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Contracts;

use App\Domain\WooCommerce\DTOs\WooCommerceCustomerData;
use App\Domain\WooCommerce\DTOs\WooCommerceOrderData;
use App\Domain\WooCommerce\DTOs\WooCommerceProductData;

interface WooCommerceClientContract
{
    public function getProduct(int $productId): WooCommerceProductData;

    public function getProducts(array $filters = []): array;

    public function getProductsPage(int $page = 1, int $perPage = 20, ?string $search = null): array;

    public function getProductBySku(string $sku): ?WooCommerceProductData;

    public function createProduct(array $data): array;

    public function updateProduct(int $productId, array $data): array;

    public function deleteProduct(int $productId): bool;

    public function getVariation(int $productId, int $variationId): WooCommerceProductData;

    public function getVariations(int $productId): array;

    public function createVariation(int $productId, array $data): array;

    public function updateVariation(int $productId, int $variationId, array $data): array;

    public function getCategories(array $filters = []): array;

    public function createCategory(array $data): array;

    public function updateCategory(int $categoryId, array $data): array;

    public function getCustomer(int $customerId): WooCommerceCustomerData;

    public function getCustomers(array $filters = []): array;

    public function getCustomerByEmail(string $email): ?WooCommerceCustomerData;

    public function createCustomer(array $data): array;

    public function updateCustomer(int $customerId, array $data): array;

    public function getOrder(int $orderId): WooCommerceOrderData;

    public function getOrders(array $filters = []): array;

    public function createOrder(array $data): array;

    public function updateOrder(int $orderId, array $data): array;

    public function getOrderNotes(int $orderId): array;

    public function addOrderNote(int $orderId, string $note, bool $customerNote = false): array;

    public function updateStock(int $productId, int $quantity, ?int $variationId = null): array;

    public function getShipmentTrackings(int $orderId): array;

    public function addShipmentTracking(int $orderId, array $data): array;

    public function getWebhooks(array $filters = []): array;

    public function createWebhook(array $data): array;

    public function deleteWebhook(int $webhookId): bool;

    public function testConnection(): bool;
}
