@php
    $json = fn ($value) => $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Account</x-slot>
            <form wire:submit.prevent="updateAccount" class="space-y-3">
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Open name</label>
                        <input
                            wire:model.defer="accountName"
                            type="text"
                            class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                            placeholder="e.g. Brand display name"
                        >
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Open email</label>
                        <input
                            wire:model.defer="accountEmail"
                            type="email"
                            class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                            placeholder="email@example.com"
                        >
                    </div>
                </div>
                <div class="flex gap-2">
                    <x-filament::button type="button" color="gray" wire:click="fetchSettings">
                        Fetch settings
                    </x-filament::button>
                    <x-filament::button type="submit">
                        Update account
                    </x-filament::button>
                </div>
            </form>

            @if ($settingsData)
                <div class="mt-4">
                    <pre class="rounded-md border bg-gray-50 p-3 text-sm overflow-auto">{{ $json($settingsData) }}</pre>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Product</x-slot>
            <form wire:submit.prevent="fetchProduct" class="space-y-3">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Product ID (pid)</label>
                    <input
                        wire:model.defer="pid"
                        type="text"
                        class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                        placeholder="PID from CJ"
                        required
                    >
                </div>
                <x-filament::button type="submit">Fetch product</x-filament::button>
            </form>

            @if ($productData)
                <div class="mt-4">
                    <pre class="rounded-md border bg-gray-50 p-3 text-sm overflow-auto">{{ $json($productData) }}</pre>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Variants</x-slot>
            <form wire:submit.prevent="fetchVariants" class="space-y-3">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Product ID (pid)</label>
                    <input
                        wire:model.defer="pid"
                        type="text"
                        class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                        placeholder="PID from CJ"
                        required
                    >
                </div>
                <x-filament::button type="submit">Fetch variants</x-filament::button>
            </form>

            @if ($variantsData)
                <div class="mt-4">
                    <pre class="rounded-md border bg-gray-50 p-3 text-sm overflow-auto">{{ $json($variantsData) }}</pre>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Stock by Variant</x-slot>
            <form wire:submit.prevent="fetchStock" class="space-y-3">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Variant ID (vid)</label>
                    <input
                        wire:model.defer="vid"
                        type="text"
                        class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                        placeholder="Variant ID"
                        required
                    >
                </div>
                <x-filament::button type="submit">Fetch stock</x-filament::button>
            </form>

            @if ($stockData)
                <div class="mt-4">
                    <pre class="rounded-md border bg-gray-50 p-3 text-sm overflow-auto">{{ $json($stockData) }}</pre>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
