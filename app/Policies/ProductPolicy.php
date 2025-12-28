<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
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
        // Check if user's token has products:create ability
        return $user->tokenCan('products:create') || $user->tokenCan('*');
    }

    /**
     * Determine if the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        // Check if user's token has products:update ability
        return $user->tokenCan('products:update') || $user->tokenCan('*');
    }

    /**
     * Determine if the user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        // Check if user's token has products:delete ability
        if (!($user->tokenCan('products:delete') || $user->tokenCan('*'))) {
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
        return $user->tokenCan('products:restore') || $user->tokenCan('*');
    }

    /**
     * Determine if the user can permanently delete the product.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return $user->tokenCan('products:force-delete') || $user->tokenCan('*');
    }
}
