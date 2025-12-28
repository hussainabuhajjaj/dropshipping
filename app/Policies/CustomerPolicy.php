<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tokenCan('customers:list') || $user->tokenCan('*');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->tokenCan('customers:view') || $user->tokenCan('*');
    }

    public function create(User $user): bool
    {
        return $user->tokenCan('customers:create') || $user->tokenCan('*');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->tokenCan('customers:update') || $user->tokenCan('*');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->tokenCan('customers:delete') || $user->tokenCan('*');
    }
}
