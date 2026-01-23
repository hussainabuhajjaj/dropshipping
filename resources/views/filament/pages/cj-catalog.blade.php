@php
    $json = fn ($value) => $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;
    $items = $items ?? [];
    $totalPages = max($totalPages ?? 1, 1);
    $totalPagesKnown = $totalPagesKnown ?? false;
    $canLoadMore = $canLoadMore ?? false;
    $logoUrl = config('app.logo') ?? asset('storage/logo.png');
@endphp

<x-filament-panels::page>
    {{-- Global Loading Overlay with Logo
    <div wire:loading.delay class="fixed inset-0 z-50 flex items-center justify-center bg-white/80 backdrop-blur dark:bg-gray-900/80">
        <div class="flex flex-col items-center gap-3">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <img src="{{ $logoUrl }}" alt="Logo" class="h-10 w-10 object-contain" />
                </div>
                <div class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ config('app.name') }}</div>
            </div>
            <div class="h-12 w-12 animate-spin rounded-full border-4 border-primary-200 border-t-primary-500 dark:border-primary-800 dark:border-t-primary-400"></div>
            <p class="text-sm text-gray-600 dark:text-gray-300">Loading CJ Catalog...</p>
        </div>
    </div>

    {{-- Stats Overview Bar --}}
    {{-- <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Products</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($total) }}</p>
                    </div>
                    <div class="rounded-full bg-primary-100 p-3 dark:bg-primary-900/30">
                        <x-filament::icon icon="heroicon-o-cube" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Page {{ $pageNum }} of {{ $totalPagesKnown ? $totalPages : '--' }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Price</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $avgPrice ? '$' . $avgPrice : '--' }}</p>
                    </div>
                    <div class="rounded-full bg-success-100 p-3 dark:bg-success-900/30">
                        <x-filament::icon icon="heroicon-o-currency-dollar" class="h-6 w-6 text-success-600 dark:text-success-400" />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ number_format($loaded) }} loaded</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Inventory</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($inventoryTotal) }}</p>
                    </div>
                    <div class="rounded-full bg-warning-100 p-3 dark:bg-warning-900/30">
                        <x-filament::icon icon="heroicon-o-archive-box" class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ number_format($withImages) }} with images</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Sync Status</p>
                        <p class="mt-2 text-3xl font-bold text-success-600 dark:text-success-400">{{ number_format($syncEnabledCount) }}</p>
                    </div>
                    <div class="rounded-full bg-info-100 p-3 dark:bg-info-900/30">
                        <x-filament::icon icon="heroicon-o-arrow-path" class="h-6 w-6 text-info-600 dark:text-info-400" />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    <span class="text-warning-600 dark:text-warning-400">{{ $syncDisabledCount }} disabled</span>
                    @if ($syncStaleCount > 0)
                        <span class="text-danger-600 dark:text-danger-400">, {{ $syncStaleCount }} stale</span>
                    @endif
                </p>
            </div>
        </div>
    </div> --}} 

    {{-- Stats Infolist Section --}}
    <div class="mb-6">
        <x-filament::section
            heading="Detailed Statistics"
            description="Complete overview of catalog metrics"
            icon="heroicon-o-chart-bar"
            :collapsible="false"
        >
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->getStatsData() as $stat)
                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $stat['label'] }}</p>
                                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $stat['value'] }}</p>
                            </div>
                            <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-700">
                                <x-filament::icon :icon="$stat['icon']" class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>

    <div class="grid gap-6 lg:grid-cols-[380px_1fr]">
        {{-- Sidebar: Filters and Actions --}}
        <div class="space-y-6">
            {{-- Search Filters --}}
            <x-filament::section
                heading="Search & Filters"
                description="Find products in the CJ catalog"
                icon="heroicon-o-funnel"
                :collapsible="true"
            >
                <form wire:submit.prevent="applyFilters" class="space-y-4">
                    {{-- Quick Search Fieldset --}}
                    <x-filament::fieldset>
                        <x-slot name="label">
                            <x-filament::icon icon="heroicon-o-magnifying-glass" class="inline-block h-4 w-4 mr-1" />
                            Quick Search
                        </x-slot>
                        <x-filament::input.wrapper>
                            <x-filament::input
                                id="productName"
                                wire:model.defer="productName"
                                type="text"
                                placeholder="Product name or keyword..."
                            />
                        </x-filament::input.wrapper>
                    </x-filament::fieldset>

                    {{-- Advanced Filters Fieldset --}}
                    <x-filament::fieldset>
                        <x-slot name="label">
                            <x-filament::icon icon="heroicon-o-adjustments-horizontal" class="inline-block h-4 w-4 mr-1" />
                            Advanced Filters
                        </x-slot>
                        <div class="space-y-3">
                            <x-filament::input.wrapper>
                                {{-- <label for="productSku" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Product SKU</label> --}}
                                <x-filament::input
                                    id="productSku"
                                    wire:model.defer="productSku"
                                    type="text"
                                    placeholder="Exact SKU..."
                                />
                            </x-filament::input.wrapper>
                            <x-filament::input.wrapper>
                                {{-- <label for="materialKey" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Material</label> --}}
                                <x-filament::input
                                    id="materialKey"
                                    wire:model.defer="materialKey"
                                    type="text"
                                    placeholder="Cotton, alloy..."
                                />
                            </x-filament::input.wrapper>
                            <x-filament::input.wrapper>
                                {{-- <label for="categoryId" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Category</label> --}}
                                <x-filament::input.select
                                    id="categoryId"
                                    wire:model.defer="categoryId"
                                >
                                    <option value="">All categories</option>
                                    @foreach ($categoryOptions as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            <x-filament::input.wrapper>
                                {{-- <label for="warehouseId" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Warehouse</label> --}}
                                <x-filament::input.select
                                    id="warehouseId"
                                    wire:model.defer="warehouseId"
                                    :disabled="$warehouseLoadFailed"
                                >
                                    <option value="">All warehouses</option>
                                    @foreach ($warehouseOptions as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                    @if ($warehouseLoadFailed)
                                        <x-slot name="helperText">
                                            Warehouse list unavailable. Refresh to retry.
                                        </x-slot>
                                    @elseif (empty($warehouseOptions))
                                        <x-slot name="helperText">
                                            No warehouses returned.
                                        </x-slot>
                                    @endif
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            <x-filament::input.wrapper>
                                <label class="flex items-center gap-2">
                                    <x-filament::input.checkbox
                                        wire:model.defer="inStockOnly"
                                    />
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Show in-stock only</span>
                                </label>
                            </x-filament::input.wrapper>
                            <x-filament::input.wrapper>
                                {{-- <label for="sort" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Sort</label> --}}
                                <x-filament::input.select
                                    id="sort"
                                    wire:model.defer="sort"
                                >
                                    <option value="">Default</option>
                                    <option value="1">Price: Low to High</option>
                                    <option value="2">Price: High to Low</option>
                                    <option value="5">Newest</option>
                                    <option value="6">Best Selling</option>
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            <x-filament::input.wrapper>
                                {{-- <label for="storeProductId" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Store Product ID</label> --}}
                                <x-filament::input
                                    id="storeProductId"
                                    wire:model.defer="storeProductId"
                                    type="text"
                                    placeholder="Store product ID..."
                                />
                            </x-filament::input.wrapper>
                        </div>
                    </x-filament::fieldset>

                    {{-- Pagination Controls Fieldset --}}
                    <x-filament::fieldset>
                        <x-slot name="label">
                            <x-filament::icon icon="heroicon-o-list-bullet" class="inline-block h-4 w-4 mr-1" />
                            Pagination
                        </x-slot>
                        <div class="grid grid-cols-2 gap-3">
                            <x-filament::input.wrapper>
                                <label for="pageNum" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Page</label>
                                <x-filament::input
                                    id="pageNum"
                                    wire:model.defer="pageNum"
                                    type="number"
                                    min="1"
                                />
                            </x-filament::input.wrapper>
                            <x-filament::input.wrapper>
                                <label for="pageSize" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Per Page</label>
                                <x-filament::input
                                    id="pageSize"
                                    wire:model.defer="pageSize"
                                    type="number"
                                    min="10"
                                    max="200"
                                />
                            </x-filament::input.wrapper>
                        </div>
                    </x-filament::fieldset>

                    {{-- Action Buttons --}}
                    <div class="flex gap-2">
                        <x-filament::button type="submit" class="flex-1" size="sm" wire:loading.attr="disabled">
                            <x-filament::icon icon="heroicon-o-funnel" class="-ml-1 h-4 w-4" />
                            Apply
                        </x-filament::button>
                        <x-filament::button type="button" color="gray" size="sm" wire:click="resetFilters" wire:loading.attr="disabled">
                            <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                        </x-filament::button>
                    </div>
                </form>

                {{-- Pro Tips --}}
                <div class="rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-800 dark:bg-primary-950">
                    <div class="flex gap-2">
                        <x-filament::icon icon="heroicon-o-light-bulb" class="mt-0.5 h-4 w-4 flex-shrink-0 text-primary-600 dark:text-primary-400" />
                        <div class="text-xs text-primary-700 dark:text-primary-300">
                            <p class="font-semibold">Pro Tips:</p>
                            <ul class="mt-1 space-y-0.5 pl-3">
                                <li class="list-disc">Combine filters for better results</li>
                                <li class="list-disc">Use exact SKU for precise matching</li>
                                <li class="list-disc">Import adds product with sync enabled</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section
                heading="Imported Catalog Matches"
                description="Products already synced to your store from this catalog batch"
                icon="heroicon-o-check-badge"
                :collapsible="true"
                :collapsed="false"
            >
                <div class="space-y-2">
                    @if ($existingCatalog)
                        @foreach (array_slice($existingCatalog, 0, 6) as $pid => $entry)
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        PID {{ $pid }}
                                        @if (! empty($entry['synced_at']))
                                            • Synced {{ $entry['synced_at'] }}
                                        @endif
                                    </p>
                                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $entry['name'] }}</p>
                                </div>
                                <a
                                    href="{{ \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $entry['id']]) }}"
                                    target="_blank"
                                    class="text-xs font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                                >
                                    Open
                                </a>
                            </div>
                        @endforeach
                        @if (count($existingCatalog) > 6)
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Showing first 6 of {{ count($existingCatalog) }} imported matches.
                            </p>
                        @endif
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No imported products detected in this catalog batch yet.
                        </p>
                    @endif
                </div>
            </x-filament::section>

            {{-- Quick Actions --}}
            <x-filament::section
                heading="Quick Actions"
                description="Bulk operations and navigation"
                icon="heroicon-o-bolt"
            >
                <div class="space-y-3">
                    {{-- Navigation --}}
                    <div class="grid grid-cols-3 gap-2">
                        <x-filament::button type="button" color="gray" size="sm" wire:click="previousPage" :disabled="$pageNum <= 1" wire:loading.attr="disabled" class="justify-center">
                            <x-filament::icon icon="heroicon-o-chevron-left" class="h-4 w-4" />
                        </x-filament::button>
                        <x-filament::button type="button" size="sm" wire:click="loadMore" :disabled="! $canLoadMore" wire:loading.attr="disabled" class="justify-center">
                            Load More
                        </x-filament::button>
                        <x-filament::button type="button" color="gray" size="sm" wire:click="nextPage" :disabled="! $canLoadMore" wire:loading.attr="disabled" class="justify-center">
                            <x-filament::icon icon="heroicon-o-chevron-right" class="h-4 w-4" />
                        </x-filament::button>
                    </div>

                    <x-filament::button type="button" color="gray" size="sm" wire:click="fetch" wire:loading.attr="disabled" class="w-full justify-center">
                        <x-filament::icon icon="heroicon-o-arrow-path" class="-ml-1 h-4 w-4" />
                        Refresh Catalog
                    </x-filament::button>

                    <div class="border-t border-gray-200 pt-3 dark:border-gray-700"></div>

                    {{-- Bulk Actions --}}
                    <x-filament::button type="button" color="primary" size="sm" wire:click="importDisplayedProducts" wire:loading.attr="disabled" class="w-full justify-center">
                        <x-filament::icon icon="heroicon-o-arrow-down-tray" class="-ml-1 h-4 w-4" />
                        Import Current Page
                    </x-filament::button>

                    <x-filament::button type="button" color="success" size="sm" wire:click="importMyProductsNow" wire:loading.attr="disabled" class="w-full justify-center">
                        <x-filament::icon icon="heroicon-o-star" class="-ml-1 h-4 w-4" />
                        Import My Products
                    </x-filament::button>

                    <x-filament::button type="button" color="warning" size="sm" wire:click="queueSyncJob" wire:loading.attr="disabled" class="w-full justify-center">
                        <x-filament::icon icon="heroicon-o-queue-list" class="-ml-1 h-4 w-4" />
                        Queue Sync Job
                    </x-filament::button>

                    {{-- Last Command Status --}}
                    @if ($lastCommandMessage)
                        <div class="rounded-lg bg-gray-50 p-2.5 text-xs dark:bg-gray-800">
                            <p class="font-medium text-gray-700 dark:text-gray-200">Last Action:</p>
                            <p class="mt-0.5 text-gray-600 dark:text-gray-400">{{ $lastCommandMessage }}</p>
                            @if ($lastCommandAt)
                                <p class="mt-0.5 text-gray-500 dark:text-gray-500">{{ $lastCommandAt }}</p>
                            @endif
                        </div>
                    @endif
                    <div class="flex flex-wrap gap-2 pt-3 text-xs">
                        <span class="rounded-full border border-gray-200 bg-white px-3 py-1 font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900">
                            Batch {{ $pageNum }} / {{ $totalPagesKnown ? $totalPages : '--' }}
                        </span>
                        <span class="rounded-full border border-gray-200 bg-white px-3 py-1 font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900">
                            Loaded {{ number_format($loaded) }}
                        </span>
                        <span class="rounded-full border border-gray-200 bg-white px-3 py-1 font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900">
                            Latest batch {{ $lastBatchCount }} items
                        </span>
                        <span class="rounded-full border border-gray-200 bg-white px-3 py-1 font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900">
                            Inventory {{ number_format($inventoryTotal) }}
                        </span>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Main Content --}}
        <div class="space-y-6">
            @if ($loaded === 0)
                <x-filament::empty-state>
                    <x-slot name="heading">
                        No products found
                    </x-slot>
                    <x-slot name="description">
                        Adjust your filters and search criteria, or click "Refresh Catalog" to load products from CJ Dropshipping.
                    </x-slot>
                    <x-slot name="icon">
                        heroicon-o-cube
                    </x-slot>
                </x-filament::empty-state>
            @else
                <x-filament::section
                    heading="Product Catalog"
                    description="Browse and import CJ Dropshipping products"
                    icon="heroicon-o-shopping-bag"
                >
                    {{ $this->table }}
                </x-filament::section>
            @endif

            {{-- Debug Section (Collapsible) --}}
            <x-filament::section
                heading="Developer Tools"
                description="API response inspection"
                icon="heroicon-o-code-bracket-square"
                :collapsible="true"
                :collapsed="true"
            >
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <pre class="overflow-auto text-xs text-gray-700 dark:text-gray-300">{{ $json($products) }}</pre>
                </div>
            </x-filament::section>
        </div>
    </div>

    {{-- Image Preview Modal --}}
    @php
        $imagePreviewModalId = $this->getImagePreviewModalId();
    @endphp

    <x-filament::modal
        :id="$imagePreviewModalId"
        :heading="$imagePreviewName ?? 'Product Preview'"
        :close-by-clicking-away="true"
        :close-by-escaping="true"
        :teleport="'body'"
        :width="'7xl'"
        :x-on:modal-closed="'if ($event.detail.id === ' . \Illuminate\Support\Js::from($imagePreviewModalId) . ') $wire.closeImagePreview()'"
    >
        <div class="space-y-4">
            {{-- Main Image --}}
            <div class="flex items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
                @if ($imagePreviewUrl)
                    <img
                        src="{{ $imagePreviewUrl }}"
                        alt="{{ $imagePreviewName ?? 'CJ product image' }}"
                        class="max-h-[85vh] w-full object-contain"
                        loading="lazy"
                    />
                @else
                    <div class="flex h-64 items-center justify-center">
                        <div class="text-center">
                            <x-filament::icon icon="heroicon-o-photo" class="mx-auto h-12 w-12 text-gray-400" />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No image available</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Image Thumbnails --}}
            @if (count($imagePreviewUrls) > 1)
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Images ({{ count($imagePreviewUrls) }})
                    </p>
                    <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                        @foreach ($imagePreviewUrls as $url)
                            <button
                                type="button"
                                wire:click="setActivePreviewImage({{ \Illuminate\Support\Js::from($url) }})"
                                class="{{ $imagePreviewUrl === $url 
                                    ? 'group relative overflow-hidden rounded-lg border border-primary-500 ring-2 ring-primary-300 shadow-md transition-all duration-200' 
                                    : 'group relative overflow-hidden rounded-lg border border-gray-200 hover:border-primary-400 hover:shadow-sm dark:border-gray-700 transition-all duration-200' }}"
                            >
                                <img
                                    src="{{ $url }}"
                                    alt="Thumbnail"
                                    class="h-20 w-full object-cover transition-transform group-hover:scale-110"
                                    loading="lazy"
                                />
                                @if ($imagePreviewUrl === $url)
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/20">
                                        <x-filament::icon icon="heroicon-s-check-circle" class="h-6 w-6 text-white" />
                                    </div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Video Previews --}}
            @if (count($videoPreviewUrls) > 0)
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Videos ({{ count($videoPreviewUrls) }})
                    </p>
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach ($videoPreviewUrls as $videoUrl)
                            <video
                                controls
                                preload="metadata"
                                class="w-full rounded-xl border border-gray-200 bg-black shadow-sm dark:border-gray-700"
                                src="{{ $videoUrl }}"
                            ></video>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-filament::modal>
</x-filament-panels::page>
