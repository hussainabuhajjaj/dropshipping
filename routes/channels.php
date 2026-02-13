<?php

declare(strict_types=1);

use App\Domain\Support\Models\SupportConversation;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('support.customer.{conversationUuid}', function ($user, string $conversationUuid): bool {
    if (! $user instanceof Customer) {
        return false;
    }

    return SupportConversation::query()
        ->where('uuid', $conversationUuid)
        ->where('customer_id', $user->id)
        ->exists();
});

Broadcast::channel('support.admin', function ($user): bool {
    if (! $user instanceof User) {
        return false;
    }

    if (method_exists($user, 'isSupportAgent')) {
        return (bool) $user->isSupportAgent();
    }

    return in_array((string) $user->role, ['admin', 'staff'], true);
});
