<?php

namespace App\Domain\Orders\Policies;

use App\Domain\Orders\Models\User;

class AgentPolicy
{
    public function viewLinehaulShipments(User $user): bool
    {
        return $user->hasRole('local-agent');
    }

    public function manageLastMileDeliveries(User $user): bool
    {
        return $user->hasRole('local-agent');
    }
}
