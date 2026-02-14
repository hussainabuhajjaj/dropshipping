<x-filament-panels::page>
    <form wire:submit.prevent="send" class="space-y-8">
        <x-filament::section
            heading="Compose message"
            description="Write the notification content and choose who should receive it."
            icon="heroicon-o-megaphone"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Title</label>
                    <x-filament::input
                        wire:model.defer="notificationTitle"
                        type="text"
                        class="w-full"
                        placeholder="Notification title"
                        required
                    />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Audience</label>
                    <x-filament::input.select
                        wire:model.defer="audience"
                        class="w-full"
                    >
                        <option value="customers">Customers</option>
                        <option value="newsletter">Newsletter subscribers</option>
                        <option value="admins">Admins & staff</option>
                        <option value="both">Customers + admins</option>
                    </x-filament::input.select>
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Message</label>
                <textarea
                    wire:model.defer="body"
                    rows="4"
                    class="fi-input w-full"
                    placeholder="Write the message body"
                    required
                ></textarea>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="Links & targeting"
            description="Optional links and a list of specific recipients."
            icon="heroicon-o-link"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Action URL (optional)</label>
                    <x-filament::input
                        wire:model.defer="actionUrl"
                        type="url"
                        class="w-full"
                        placeholder="https://..."
                    />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Action label (optional)</label>
                    <x-filament::input
                        wire:model.defer="actionLabel"
                        type="text"
                        class="w-full"
                        placeholder="View details"
                    />
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Recipient emails (optional)</label>
                <x-filament::input
                    wire:model.defer="recipientEmails"
                    type="text"
                    class="w-full"
                    placeholder="email1@example.com, email2@example.com"
                />
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Leave blank to target all recipients in the selected audience (if enabled).
                </p>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="Delivery channels"
            description="Choose how the notification should be delivered."
            icon="heroicon-o-adjustments-horizontal"
        >
            <x-filament::fieldset label="Channels">
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <x-filament::input.checkbox wire:model.defer="sendToAll" />
                        Send to all (when no emails provided)
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <x-filament::input.checkbox wire:model.defer="sendDatabase" />
                        In-app (database)
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <x-filament::input.checkbox wire:model.defer="sendPush" />
                        Push (Expo mobile)
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <x-filament::input.checkbox wire:model.defer="sendMail" />
                        Email
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <x-filament::input.checkbox wire:model.defer="sendWhatsApp" />
                        WhatsApp
                    </label>
                </div>
            </x-filament::fieldset>
        </x-filament::section>

        <x-filament::section
            heading="Ready to send"
            description="Review the details and dispatch the notification."
            icon="heroicon-o-paper-airplane"
        >
            <x-filament::actions>
                <x-filament::button type="submit">Send notification</x-filament::button>
            </x-filament::actions>
        </x-filament::section>
    </form>
</x-filament-panels::page>
