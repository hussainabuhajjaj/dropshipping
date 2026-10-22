<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use Filament\Resources\Resource;

class BaseResource extends Resource
{
    protected static bool $adminOnly = false;
    protected static bool $staffReadOnly = false;

    /**
     * @return array<int, string>
     */
    protected static function allowedRoles(): array
    {
        return ['admin', 'staff'];
    }

    protected static function canAccessResource(): bool
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

    protected static function canManageResource(): bool
    {
        $user = auth(config('filament.auth.guard', 'admin'))->user();

        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if (static::$adminOnly || static::$staffReadOnly) {
            return false;
        }

        return in_array($user->role, static::allowedRoles(), true);
    }

    public static function canViewAny(): bool
    {
        return static::canAccessResource();
    }

    public static function canCreate(): bool
    {
        return static::canManageResource();
    }

    public static function canEdit($record): bool
    {
        return static::canManageResource();
    }

    public static function canDelete($record): bool
    {
        return static::canManageResource();
    }

    public static function canDeleteAny(): bool
    {
        return static::canManageResource();
    }
}

