<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tokenCan('orders:list') || $user->tokenCan('*');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->tokenCan('orders:view') || $user->tokenCan('*');
    }

    public function create(User $user): bool
    {
        return $user->tokenCan('orders:create') || $user->tokenCan('*');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->tokenCan('orders:update') || $user->tokenCan('*');
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->tokenCan('orders:delete') || $user->tokenCan('*');
    }
}
