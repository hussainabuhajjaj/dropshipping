<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;

class AdminPanelNotification extends Notification
{
    public function send(): static
    {
        parent::send();

        $guard = (string) config('filament.auth.guard', 'admin');
        $user = auth($guard)->user();

        if (! $user instanceof Authenticatable) {
            return $this;
        }

        $role = method_exists($user, 'getAttribute') ? (string) ($user->getAttribute('role') ?? '') : '';
        if ($role !== '' && ! in_array($role, ['admin', 'staff'], true)) {
            return $this;
        }

        $this->sendToDatabase($user);

        return $this;
    }
}
