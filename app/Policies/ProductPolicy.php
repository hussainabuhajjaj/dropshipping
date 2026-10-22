<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    private function canManage(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff'], true) || $user->tokenCan('*');
    }

    /**
     * Determine if the user can view any products.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can list products
        return true;
    }

    /**
     * Determine if the user can view the product.
     */
    public function view(User $user, Product $product): bool
    {
        // All authenticated users can view a product
        return true;
    }

    /**
     * Determine if the user can create products.
     */
    public function create(User $user): bool
    {
        // Allow admin/staff sessions or token-based API access
        return $this->canManage($user) || $user->tokenCan('products:create');
    }

    /**
     * Determine if the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        // Allow admin/staff sessions or token-based API access
        return $this->canManage($user) || $user->tokenCan('products:update');
    }

    /**
     * Determine if the user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        // Allow admin/staff sessions or token-based API access
        if (! ($this->canManage($user) || $user->tokenCan('products:delete'))) {
            return false;
        }

        // Check if product has orders (business rule)
        $hasOrders = $product->variants()
            ->whereHas('orderItems')
            ->exists();

        return ! $hasOrders;
    }

    /**
     * Determine if the user can restore the product.
     */
    public function restore(User $user, Product $product): bool
    {
        return $this->canManage($user) || $user->tokenCan('products:restore');
    }

    /**
     * Determine if the user can permanently delete the product.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return $this->canManage($user) || $user->tokenCan('products:force-delete');
    }
}
