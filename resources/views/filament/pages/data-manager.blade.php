<x-filament-panels::page>
    <div class="space-y-8">
        <x-filament::section
            heading="Export data"
            description="Download CSV exports for products and customers."
            icon="heroicon-o-arrow-down-tray"
        >
            <x-filament::actions>
                <x-filament::button tag="a" href="{{ route('admin.exports.products') }}">
                    Export products
                </x-filament::button>
                <x-filament::button tag="a" href="{{ route('admin.exports.customers') }}" color="gray">
                    Export customers
                </x-filament::button>
            </x-filament::actions>
        </x-filament::section>

        <x-filament::section
            heading="Import products"
            description="Upload a CSV with product fields like name, slug, selling_price, stock_on_hand."
            icon="heroicon-o-arrow-up-tray"
        >
            <form wire:submit.prevent="importProducts" class="space-y-4">
                <x-filament::input type="file" wire:model="productImport" />
                @if (! empty($productImportSummary))
                    <x-filament::fieldset label="Last product import">
                        <pre class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 overflow-auto dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ json_encode($productImportSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </x-filament::fieldset>
                @endif
                <x-filament::actions>
                    <x-filament::button type="submit">Import products</x-filament::button>
                </x-filament::actions>
            </form>
        </x-filament::section>

        <x-filament::section
            heading="Import customers"
            description="Upload a CSV with customer fields like email, first_name, last_name, phone."
            icon="heroicon-o-user-plus"
        >
            <form wire:submit.prevent="importCustomers" class="space-y-4">
                <x-filament::input type="file" wire:model="customerImport" />
                @if (! empty($customerImportSummary))
                    <x-filament::fieldset label="Last customer import">
                        <pre class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 overflow-auto dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ json_encode($customerImportSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </x-filament::fieldset>
                @endif
                <x-filament::actions>
                    <x-filament::button type="submit">Import customers</x-filament::button>
                </x-filament::actions>
            </form>
        </x-filament::section>
    </div>
</x-filament-panels::page>
