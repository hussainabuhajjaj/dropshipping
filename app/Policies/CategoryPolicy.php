<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine if the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the category.
     */
    public function view(User $user, Category $category): bool
    {
        return true;
    }

    /**
     * Determine if the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->tokenCan('categories:create') || $user->tokenCan('*');
    }

    /**
     * Determine if the user can update the category.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->tokenCan('categories:update') || $user->tokenCan('*');
    }

    /**
     * Determine if the user can delete the category.
     */
    public function delete(User $user, Category $category): bool
    {
        // Prevent deletion if category has products or children
        $hasProducts = $category->products()->exists();
        $hasChildren = $category->children()->exists();

        if ($hasProducts || $hasChildren) {
            return false;
        }

        return $user->tokenCan('categories:delete') || $user->tokenCan('*');
    }
}
