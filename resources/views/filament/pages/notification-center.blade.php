<x-filament-panels::page>
    <form wire:submit.prevent="send" class="space-y-6">
        <x-filament::card bordered>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">Compose message</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Write the notification content and pick the audience.</p>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2 mt-6">
                <div class="space-y-2">
                    <x-filament::input
                        label="Title"
                        helper-text="Keep it short and actionable."
                        wire:model.defer="notificationTitle"
                        required
                    />
                </div>
                <div class="space-y-2">
                    <x-filament::input.select
                        label="Audience"
                        helper-text="Newsletter chooses email channel automatically."
                        wire:model.defer="audience"
                    >
                        <option value="customers">Customers</option>
                        <option value="newsletter">Newsletter subscribers</option>
                        <option value="admins">Admins & staff</option>
                        <option value="both">Customers + admins</option>
                    </x-filament::input.select>
                </div>
            </div>

            <div class="space-y-1 mt-4">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Message</label>
                <textarea
                    wire:model.defer="body"
                    rows="4"
                    class="fi-input w-full"
                    required
                ></textarea>
                <p class="text-xs text-gray-500 dark:text-gray-400">Use complete sentences and a clear call-to-action.</p>
            </div>
        </x-filament::card>

        <x-filament::card bordered>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">Links & targeting</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Optionally provide URLs, audiences, and imagery.</p>
                </div>
            </div>

            <div class="grid gap-6 mt-4 md:grid-cols-2">
                <x-filament::input
                    label="Action URL (optional)"
                    helper-text="URL opened when the user taps the notification."
                    wire:model.defer="actionUrl"
                    type="url"
                />
                <x-filament::input
                    label="Action label (optional)"
                    helper-text="Override the button copy."
                    wire:model.defer="actionLabel"
                />
            </div>

            <x-filament::input
                label="Recipient emails (optional)"
                helper-text="Comma or space separated. Leave blank to target the entire audience."
                wire:model.defer="recipientEmails"
                class="mt-4"
            />

            <div class="grid gap-6 md:grid-cols-3 mt-4">
                <x-filament::input.select label="Target type" wire:model.defer="targetType">
                    <option value="custom">Custom URL</option>
                    <option value="product">Product</option>
                    <option value="promotion">Promotion</option>
                    <option value="category">Category</option>
                </x-filament::input.select>

                <x-filament::input
                    label="{{ $this->targetIdentifierLabel() }}"
                    helper-text="{{ $this->targetIdentifierHint() }}"
                    wire:model.defer="targetIdentifier"
                    placeholder="{{ $this->targetIdentifierPlaceholder() }}"
                />

                <x-filament::input
                    label="Header image (optional)"
                    helper-text="Used in push/in-app cards."
                    wire:model.defer="imageUrl"
                    type="url"
                />
            </div>
        </x-filament::card>

        <x-filament::card bordered>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">Delivery channels</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Choose how recipients receive this notification.</p>
                </div>
            </div>

            <div class="grid gap-4 mt-6 md:grid-cols-2">
                <div class="flex items-center gap-3">
                    <x-filament::input.checkbox wire:model.defer="sendToAll" />
                    <span class="text-sm text-gray-700 dark:text-gray-200">Send to all (when no emails provided)</span>
                </div>
                <div class="flex items-center gap-3">
                    <x-filament::input.checkbox wire:model.defer="sendDatabase" />
                    <span class="text-sm text-gray-700 dark:text-gray-200">In-app (database)</span>
                </div>
                <div class="flex items-center gap-3">
                    <x-filament::input.checkbox wire:model.defer="sendPush" />
                    <span class="text-sm text-gray-700 dark:text-gray-200">Push (Expo)</span>
                </div>
                <div class="flex items-center gap-3">
                    <x-filament::input.checkbox wire:model.defer="sendMail" />
                    <span class="text-sm text-gray-700 dark:text-gray-200">Email</span>
                </div>
                <div class="flex items-center gap-3">
                    <x-filament::input.checkbox wire:model.defer="sendWhatsApp" />
                    <span class="text-sm text-gray-700 dark:text-gray-200">WhatsApp</span>
                </div>
            </div>
        </x-filament::card>

        <div class="flex justify-end">
            <x-filament::button type="submit">Send notification</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
