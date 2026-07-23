<?php

declare(strict_types=1);

namespace App\Contracts\Orders;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryContract
{
    /** List a customer's orders, newest first. */
    public function findByCustomer(int $customerId, int $perPage = 20): LengthAwarePaginator;

    /** Find a single order by ID, scoped to a customer. */
    public function findById(int $id, ?int $customerId = null): ?object;

    /** Find an order by its order number. */
    public function findByNumber(string $number): ?object;

    /** Get tracking information for an order. */
    public function getTracking(string $orderNumber): array;
}
