<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function delete(User $user, Supplier $supplier): bool
    {
        // Keep existing orders intact; prevent deletion
        return false;
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return true;
    }
}
