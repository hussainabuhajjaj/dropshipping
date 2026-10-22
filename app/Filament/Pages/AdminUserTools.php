<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use UnitEnum;

class AdminUserTools extends BasePage
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-plus';
    protected static UnitEnum|string|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 60;
    protected static bool $adminOnly = true;

    protected string $view = 'filament.pages.admin-user-tools';

    public string $inviteName = '';
    public string $inviteEmail = '';
    public string $inviteRole = 'staff';

    public string $resetEmail = '';

    public function inviteUser(): void
    {
        $this->validate([
            'inviteName' => ['required', 'string', 'max:120'],
            'inviteEmail' => ['required', 'email', 'max:255'],
            'inviteRole' => ['required', 'in:admin,staff'],
        ]);

        $user = User::query()->firstOrCreate(
            ['email' => strtolower($this->inviteEmail)],
            [
                'name' => $this->inviteName,
                'role' => $this->inviteRole,
                'password' => Str::random(16),
            ]
        );

        if (! $user->wasRecentlyCreated) {
            $user->update([
                'name' => $this->inviteName,
                'role' => $this->inviteRole,
            ]);
        }

        $status = Password::broker('users')->sendResetLink(['email' => $user->email]);

        $this->reset(['inviteName', 'inviteEmail']);

        if ($status === Password::RESET_LINK_SENT) {
            Notification::make()
                ->title('Invite sent')
                ->body('A password setup link was emailed to the admin user.')
                ->success()
                ->send();
            return;
        }

        Notification::make()
            ->title('Invite failed')
            ->body(__($status))
            ->danger()
            ->send();
    }

    public function sendResetLink(): void
    {
        $this->validate([
            'resetEmail' => ['required', 'email', 'max:255'],
        ]);

        $user = User::query()->where('email', strtolower($this->resetEmail))->first();
        if (! $user) {
            Notification::make()
                ->title('User not found')
                ->danger()
                ->send();
            return;
        }

        $status = Password::broker('users')->sendResetLink(['email' => $user->email]);

        $this->reset('resetEmail');

        if ($status === Password::RESET_LINK_SENT) {
            Notification::make()
                ->title('Reset link sent')
                ->success()
                ->send();
            return;
        }

        Notification::make()
            ->title('Reset failed')
            ->body(__($status))
            ->danger()
            ->send();
    }
}
