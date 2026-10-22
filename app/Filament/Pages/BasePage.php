<?php
declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;

class BasePage extends Page
{
    protected static bool $adminOnly = false;

    /**
     * @return array<int, string>
     */
    protected static function allowedRoles(): array
    {
        return ['admin', 'staff'];
    }

    public static function canAccess(): bool
    {
        $user = auth(config('filament.auth.guard', 'admin'))->user();

        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if (static::$adminOnly) {
            return false;
        }

        return in_array($user->role, static::allowedRoles(), true);
    }
}
