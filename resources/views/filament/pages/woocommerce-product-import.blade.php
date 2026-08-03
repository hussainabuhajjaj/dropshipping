<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                    Import Products
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Browse products from WooCommerce, select what to import, and assign a category.
                </p>
            </div>
        </div>

        {{ $this->form }}

        {{ $this->table }}
    </div>
</x-filament-panels::page>
