<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    private function canManage(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff'], true) || $user->tokenCan('*');
    }

    public function viewAny(User $user): bool
    {
        return $this->canManage($user) || $user->tokenCan('customers:list');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->canManage($user) || $user->tokenCan('customers:view');
    }

    public function create(User $user): bool
    {
        return $this->canManage($user) || $user->tokenCan('customers:create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->canManage($user) || $user->tokenCan('customers:update');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->canManage($user) || $user->tokenCan('customers:delete');
    }
}
