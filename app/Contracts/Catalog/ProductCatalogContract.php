<?php

declare(strict_types=1);

namespace App\Contracts\Catalog;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductCatalogContract
{
    /** List active products with optional category, price, search, sort, and attribute filters. */
    public function listProducts(array $filters, int $perPage = 18): LengthAwarePaginator;

    /** Find a single product by ID with relationships loaded. */
    public function findById(int $id): ?object;

    /** Find a single product by slug with relationships loaded. */
    public function findBySlug(string $slug): ?object;

    /** Get category descendants for a given category ID. */
    public function getCategoryDescendantIds(int $categoryId): array;
}
