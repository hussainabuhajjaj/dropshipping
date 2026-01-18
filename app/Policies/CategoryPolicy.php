<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    private function canManage(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff'], true) || $user->tokenCan('*');
    }

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
        return $this->canManage($user) || $user->tokenCan('categories:create');
    }

    /**
     * Determine if the user can update the category.
     */
    public function update(User $user, Category $category): bool
    {
        return $this->canManage($user) || $user->tokenCan('categories:update');
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

        return $this->canManage($user) || $user->tokenCan('categories:delete');
    }
}
