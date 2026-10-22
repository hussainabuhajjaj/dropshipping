<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Collection Info -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ $this->record->title }}</h3>
                    <p class="text-sm text-gray-600">{{ $this->record->slug }} • {{ $this->attachedProductsCount }} products currently in collection</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                          :class="{
                              'bg-blue-100 text-blue-800': $this->record->type === 'collection',
                              'bg-green-100 text-green-800': $this->record->type === 'guide',
                              'bg-purple-100 text-purple-800': $this->record->type === 'seasonal',
                              'bg-orange-100 text-orange-800': $this->record->type === 'drop',
                          }">
                        {{ ucfirst($this->record->type) }}
                    </span>
                    @if($this->record->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Active
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            Inactive
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Selection Summary -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-medium text-blue-900">Attach Products</h4>
                    <p class="text-sm text-blue-700">
                        {{ $this->selectedProductsCount }} products selected
                        • {{ $this->availableProductsCount }} products available to attach
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if($this->selectedProductsCount > 0)
                        <button wire:click="addSelectedProducts" 
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Selected
                        </button>
                        <button wire:click="addSelectedProducts(true)" 
                                class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Add and return
                        </button>
                    @endif
                    <button wire:click="$set('selectedProducts', [])" 
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Clear Selection
                    </button>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            {{ $this->table }}
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gray-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-gray-900">{{ \App\Models\Product::count() }}</div>
                <div class="text-sm text-gray-600">Total Products</div>
            </div>
            <div class="bg-green-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-green-900">{{ \App\Models\Product::where('is_active', true)->count() }}</div>
                <div class="text-sm text-green-600">Active Products</div>
            </div>
            <div class="bg-blue-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-blue-900">{{ $this->attachedProductsCount }}</div>
                <div class="text-sm text-blue-600">In Collection</div>
            </div>
        </div>

        <!-- Tips -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Quick Tips</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <ul class="list-disc list-inside space-y-1">
                            <li>Use filters to narrow down products before selecting</li>
                            <li>Bulk actions can add/remove multiple products at once</li>
                            <li>Products are automatically positioned in the order they're added</li>
                            <li>You can reorder products from the main collection edit page</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
