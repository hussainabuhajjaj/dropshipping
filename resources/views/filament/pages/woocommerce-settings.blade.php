<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex gap-4">
            <x-filament::button type="submit">
                Save Settings
            </x-filament::button>

            <x-filament::button
                color="gray"
                wire:click="testConnection"
                wire:loading.attr="disabled"
            >
                Test Connection
            </x-filament::button>

            <x-filament::button
                color="warning"
                wire:click="syncProducts"
                wire:loading.attr="disabled"
            >
                Sync All Products
            </x-filament::button>
        </div>
    </form>

    <div class="mt-8">
        <h2 class="text-lg font-medium mb-4">Sync Statistics</h2>
        @livewire(App\Domain\WooCommerce\Filament\WooCommerceSyncStatsWidget::class)
    </div>
</x-filament-panels::page>