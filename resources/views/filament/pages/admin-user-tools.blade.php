<x-filament-panels::page>
    <div class="space-y-8">
        <x-filament::section
            heading="Invite admin user"
            description="Create a new admin/staff user and send a password setup link."
            icon="heroicon-o-user-plus"
        >
            <form wire:submit.prevent="inviteUser" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Name</label>
                        <x-filament::input wire:model.defer="inviteName" type="text" class="w-full" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Email</label>
                        <x-filament::input wire:model.defer="inviteEmail" type="email" class="w-full" />
                    </div>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Role</label>
                    <x-filament::input.select wire:model.defer="inviteRole" class="w-full">
                        <option value="admin">Admin</option>
                        <option value="staff">Staff</option>
                    </x-filament::input.select>
                </div>
                <x-filament::actions>
                    <x-filament::button type="submit">Send invite</x-filament::button>
                </x-filament::actions>
            </form>
        </x-filament::section>

        <x-filament::section
            heading="Reset admin password"
            description="Send a password reset link to an existing admin user."
            icon="heroicon-o-key"
        >
            <form wire:submit.prevent="sendResetLink" class="space-y-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Admin email</label>
                    <x-filament::input wire:model.defer="resetEmail" type="email" class="w-full" />
                </div>
                <x-filament::actions>
                    <x-filament::button type="submit">Send reset link</x-filament::button>
                </x-filament::actions>
            </form>
        </x-filament::section>
    </div>
</x-filament-panels::page>
