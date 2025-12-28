<div class="w-full">
    @if ($isOpen || request()->routeIs('filament.admin.resources.products.index'))
        <!-- Embedded View (on Products List Page) -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4 rounded-t-lg flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-lg">Translation Progress</h3>
                    <p class="text-blue-100 text-sm">Live tracking</p>
                </div>
                @unless(request()->routeIs('filament.admin.resources.products.index'))
                    <button wire:click="close" class="text-white hover:bg-blue-700 p-2 rounded">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                @endunless
            </div>

            <!-- Stats -->
            <div class="bg-gray-50 p-3 border-b border-gray-200 grid grid-cols-3 gap-2">
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $this->completedCount }}</div>
                    <div class="text-xs text-gray-600">Completed</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600">{{ $this->inProgressCount }}</div>
                    <div class="text-xs text-gray-600">In Progress</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">{{ $this->failedCount }}</div>
                    <div class="text-xs text-gray-600">Failed</div>
                </div>
            </div>

            <!-- Products List -->
            <div class="overflow-y-auto max-h-96 p-3 space-y-2">
                @forelse ($products as $product)
                    <div class="border border-gray-200 rounded-lg p-3 bg-white hover:bg-gray-50 transition">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex-1">
                                <p class="font-semibold text-gray-900 text-sm truncate">{{ $product['name'] }}</p>
                                <p class="text-xs text-gray-500">ID: {{ $product['id'] }}</p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if ($product['status'] === 'completed') bg-green-100 text-green-800
                                @elseif ($product['status'] === 'in_progress') bg-yellow-100 text-yellow-800
                                @elseif ($product['status'] === 'failed') bg-red-100 text-red-800
                                @else bg-gray-100 text-gray-800
                                @endif
                            ">
                                {{ ucfirst($product['status']) }}
                            </span>
                        </div>

                        @if ($product['status'] === 'in_progress')
                            <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                                <div class="bg-blue-500 h-2 rounded-full animate-pulse" style="width: 60%"></div>
                            </div>
                        @endif

                        @if (!empty($product['locales']))
                            <div class="flex flex-wrap gap-1">
                                @foreach ($product['locales'] as $locale)
                                    <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
                                        {{ strtoupper($locale) }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        @if ($product['last_translated_at'])
                            <p class="text-xs text-gray-500 mt-2">{{ $product['last_translated_at'] }}</p>
                        @endif
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-sm">No active translations</p>
                    </div>
                @endforelse
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 p-3 border-t border-gray-200 text-xs text-gray-500">
                <div class="flex justify-between items-center">
                    <span>Auto-updating every second</span>
                    <span class="inline-block w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                </div>
            </div>
        </div>
    @else
        <!-- Floating Button View (on other pages) -->
        <button wire:click="open" class="bg-blue-500 hover:bg-blue-600 text-white rounded-full p-4 shadow-lg hover:shadow-xl transition transform hover:scale-110">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </button>
    @endif

    <!-- Auto-refresh every 1 second -->
    <script>
        setInterval(() => {
            @this.loadProducts();
        }, 1000);
    </script>
</div>

