@php
    $json = fn ($value) => $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;
    $totalPages = max($totalPages ?? 1, 1);
    $totalPagesKnown = $totalPagesKnown ?? false;
    $canLoadMore = $canLoadMore ?? false;
    $activeFiltersCount = collect([
        $productName,
        $productSku,
        $materialKey,
        $categoryId,
        $categorySearch,
        $warehouseId,
        $sort,
        $storeProductId,
        $inStockOnly ? 'stock' : null,
        ($shipToCountry ?? null),
    ])->filter(fn ($value) => filled($value))->count();
    $selectedCount = count($selectedTableRecords ?? []);
    $filteredCategoryOptions = $this->filteredCategoryOptions();
    $importStatus = $queueImportStatus ?? [];
    $importTotal = (int) ($importStatus['total'] ?? 0);
    $importProcessed = (int) ($importStatus['processed'] ?? 0);
    $importSuccess = (int) ($importStatus['success'] ?? 0);
    $importFailed = (int) ($importStatus['failed'] ?? 0);
    $importPercent = $importTotal > 0 ? (int) floor(($importProcessed / $importTotal) * 100) : 0;
    $importStatusLabel = (string) ($importStatus['status'] ?? 'idle');
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-primary-200/70 bg-gradient-to-br from-primary-50 via-white to-primary-100/70 p-5 shadow-sm dark:border-primary-900/70 dark:from-primary-950/40 dark:via-gray-900 dark:to-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-cloud-arrow-down" class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                        <p class="text-sm font-semibold tracking-wide text-primary-700 dark:text-primary-300">CJ Catalog Workspace</p>
                    </div>
                    <h2 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">Discover, review, and import products faster</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Apply filters, inspect media, and sync selected records into your local catalog.</p>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                    <x-filament::button size="sm" icon="heroicon-o-arrow-path" wire:click="fetch" wire:loading.attr="disabled">
                        Refresh
                    </x-filament::button>
                    <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="loadMore" :disabled="! $canLoadMore" wire:loading.attr="disabled">
                        Load More
                    </x-filament::button>
                    <x-filament::button size="sm" color="success" icon="heroicon-o-cloud-arrow-down" wire:click="queueImportDisplayedProducts" wire:loading.attr="disabled">
                        Queue Page Import
                    </x-filament::button>
                    <x-filament::button size="sm" color="warning" icon="heroicon-o-queue-list" wire:click="queueSyncJob" wire:loading.attr="disabled">
                        Queue Sync
                    </x-filament::button>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-filament::badge color="gray">Page {{ $pageNum }} / {{ $totalPagesKnown ? $totalPages : '--' }}</x-filament::badge>
                <x-filament::badge color="gray">Loaded {{ number_format($loaded) }}</x-filament::badge>
                <x-filament::badge color="gray">Filters {{ $activeFiltersCount }}</x-filament::badge>
                @if ($shipToCountry)
                    <x-filament::badge color="primary">Ship-to {{ $shipToCountry }}</x-filament::badge>
                @endif
            </div>
        </section>

        <div
            role="status"
            aria-live="polite"
            wire:loading.flex
            class="items-center gap-2 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 text-sm font-medium text-primary-700 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-200"
        >
            <x-filament::loading-indicator class="h-4 w-4" />
            Updating CJ catalog...
        </div>

        <div class="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
            <aside class="space-y-6 xl:sticky xl:top-6 xl:self-start">
                <x-filament::section heading="Catalog Filters" description="Set your query criteria before fetch." icon="heroicon-o-funnel">
                    <form wire:submit.prevent="applyFilters" class="space-y-5" role="search" aria-label="CJ catalog filters">
                        <x-filament::fieldset label="Search">
                            <div class="space-y-3">
                                <div>
                                    <label for="productName" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Product name</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input id="productName" wire:model.defer="productName" type="text" placeholder="Search by keyword" />
                                    </x-filament::input.wrapper>
                                </div>
                                <div>
                                    <label for="productSku" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Product SKU</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input id="productSku" wire:model.defer="productSku" type="text" placeholder="Exact SKU" />
                                    </x-filament::input.wrapper>
                                </div>
                                <div>
                                    <label for="materialKey" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Material key</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input id="materialKey" wire:model.defer="materialKey" type="text" placeholder="Material key" />
                                    </x-filament::input.wrapper>
                                </div>
                            </div>
                        </x-filament::fieldset>

                        <x-filament::fieldset label="Catalog Scope">
                            <div class="space-y-3">
                                <div>
                                    <label for="categorySearch" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Category search</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input id="categorySearch" wire:model.live.debounce.300ms="categorySearch" type="text" placeholder="Type to filter categories" />
                                    </x-filament::input.wrapper>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ count($filteredCategoryOptions) }} match(es)</p>
                                </div>
                                <div>
                                    <label for="categoryId" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Category</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select
                                            id="categoryId"
                                            wire:model.live="categoryId"
                                            wire:key="cj-category-select-{{ md5((string) ($categorySearch ?? '') . '-' . count($filteredCategoryOptions)) }}"
                                        >
                                            <option value="">All categories</option>
                                            @foreach ($filteredCategoryOptions as $id => $label)
                                                <option value="{{ $id }}">{{ $label }}</option>
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                </div>
                                <div>
                                    <label for="warehouseId" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Warehouse</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select id="warehouseId" wire:model.defer="warehouseId" :disabled="$warehouseLoadFailed">
                                            <option value="">All warehouses</option>
                                            @foreach ($warehouseOptions as $id => $label)
                                                <option value="{{ $id }}">{{ $label }}</option>
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                    @if ($warehouseLoadFailed)
                                        <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">Warehouse list unavailable. Refresh and retry.</p>
                                    @endif
                                </div>
                                <div>
                                    <label for="sort" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Sort</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select id="sort" wire:model.defer="sort">
                                            <option value="">Default</option>
                                            <option value="1">Price: Low to High</option>
                                            <option value="2">Price: High to Low</option>
                                            <option value="5">Newest</option>
                                            <option value="6">Best Selling</option>
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                </div>
                                <div>
                                    <label for="storeProductId" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Store product ID</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input id="storeProductId" wire:model.defer="storeProductId" type="text" placeholder="Store product ID" />
                                    </x-filament::input.wrapper>
                                </div>
                                <label for="inStockOnly" class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                    <x-filament::input.checkbox id="inStockOnly" wire:model.defer="inStockOnly" />
                                    In-stock only
                                </label>
                            </div>
                        </x-filament::fieldset>

                        <x-filament::fieldset label="Pagination">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="pageNum" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Page</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input id="pageNum" wire:model.defer="pageNum" type="number" min="1" />
                                    </x-filament::input.wrapper>
                                </div>
                                <div>
                                    <label for="pageSize" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Per page</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input id="pageSize" wire:model.defer="pageSize" type="number" min="10" max="200" />
                                    </x-filament::input.wrapper>
                                </div>
                            </div>
                        </x-filament::fieldset>

                        <x-filament::fieldset label="Saved Presets">
                            <div class="space-y-3">
                                <div>
                                    <label for="selectedPresetId" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Saved presets</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select id="selectedPresetId" wire:model.defer="selectedPresetId">
                                            <option value="">Choose preset</option>
                                            @foreach ($presetOptions as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                </div>
                                <div>
                                    <label for="presetName" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Save current filters as</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input id="presetName" wire:model.defer="presetName" type="text" maxlength="120" placeholder="Preset name" />
                                    </x-filament::input.wrapper>
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <x-filament::button type="button" color="gray" size="sm" wire:click="applySelectedPreset" wire:loading.attr="disabled" class="justify-center">
                                        Apply
                                    </x-filament::button>
                                    <x-filament::button type="button" size="sm" wire:click="saveFilterPreset" wire:loading.attr="disabled" class="justify-center">
                                        Save
                                    </x-filament::button>
                                    <x-filament::button type="button" color="danger" size="sm" wire:click="deleteSelectedPreset" wire:loading.attr="disabled" class="justify-center">
                                        Delete
                                    </x-filament::button>
                                </div>
                            </div>
                        </x-filament::fieldset>

                        <div class="grid grid-cols-2 gap-2">
                            <x-filament::button type="submit" icon="heroicon-o-funnel" class="justify-center" wire:loading.attr="disabled">Apply Filters</x-filament::button>
                            <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-path" class="justify-center" wire:click="resetFilters" wire:loading.attr="disabled">Reset</x-filament::button>
                        </div>
                    </form>
                </x-filament::section>

                <x-filament::section heading="Quick Actions" description="Navigation and import helpers." icon="heroicon-o-bolt">
                    <div class="space-y-3">
                        <div class="grid gap-2">
                            <x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-arrow-path" class="w-full justify-center" wire:click="fetch" wire:loading.attr="disabled">Refresh Catalog</x-filament::button>
                            <x-filament::button type="button" size="sm" icon="heroicon-o-cloud-arrow-down" class="w-full justify-center" wire:click="queueImportDisplayedProducts" wire:loading.attr="disabled">Queue Current Page Import</x-filament::button>
                            <x-filament::button type="button" color="success" size="sm" icon="heroicon-o-rectangle-stack" class="w-full justify-center" wire:click="importMyProductsNow" wire:loading.attr="disabled">Import My Products</x-filament::button>
                            <x-filament::button type="button" color="warning" size="sm" icon="heroicon-o-queue-list" class="w-full justify-center" wire:click="queueSyncJob" wire:loading.attr="disabled">Queue Sync Job</x-filament::button>
                        </div>

                        @if ($activeImportTrackingKey)
                            <div wire:poll.{{ $this->getImportPollIntervalSeconds() }}s="refreshQueueImportStatus" class="rounded-lg border border-primary-200/70 bg-primary-50 p-3 dark:border-primary-800 dark:bg-primary-900/30">
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">Queued Import Progress</p>
                                    <x-filament::badge color="{{ $importFailed > 0 ? 'warning' : 'primary' }}">
                                        {{ str_replace('_', ' ', ucfirst($importStatusLabel)) }}
                                    </x-filament::badge>
                                </div>
                                <div class="mb-2 h-2 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-900">
                                    <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ min(100, max(0, $importPercent)) }}%"></div>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-xs text-gray-700 dark:text-gray-200">
                                    <span>Total {{ number_format($importTotal) }}</span>
                                    <span>Done {{ number_format($importProcessed) }}</span>
                                    <span>Failed {{ number_format($importFailed) }}</span>
                                </div>
                                @if ($importFailed > 0)
                                    <x-filament::button type="button" color="warning" size="sm" class="mt-3 w-full justify-center" wire:click="retryFailedQueuedImports" wire:loading.attr="disabled">
                                        Retry Failed PIDs
                                    </x-filament::button>
                                @endif
                            </div>
                        @endif

                        @if ($lastCommandMessage)
                            <x-filament::fieldset label="Last Action">
                                <p class="text-sm text-gray-700 dark:text-gray-200">{{ $lastCommandMessage }}</p>
                                @if ($lastCommandAt)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $lastCommandAt }}</p>
                                @endif
                            </x-filament::fieldset>
                        @endif
                    </div>
                </x-filament::section>
            </aside>

            <section class="space-y-6" wire:loading.attr="aria-busy" aria-live="polite">
                <x-filament::section heading="Catalog Results" description="Browse live CJ records and run row actions." icon="heroicon-o-shopping-bag">
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <x-filament::badge color="gray">Results {{ number_format($loaded) }}</x-filament::badge>
                        <x-filament::badge color="gray">Inventory {{ number_format($inventoryTotal) }}</x-filament::badge>
                        <x-filament::badge color="gray">With Images {{ number_format($withImages) }}</x-filament::badge>
                        <x-filament::badge color="success">Sync Enabled {{ number_format($syncEnabledCount) }}</x-filament::badge>
                        @if ($syncStaleCount > 0)
                            <x-filament::badge color="warning">Stale {{ number_format($syncStaleCount) }}</x-filament::badge>
                        @endif
                    </div>

                    @if ($loaded === 0)
                        <x-filament::empty-state icon="heroicon-o-cube" heading="No products found" description="Change filters or refresh catalog to fetch products from CJ." />
                    @else
                        {{ $this->table }}

                        <div
                            class="sticky bottom-4 z-20 mt-4 rounded-xl border border-gray-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-900/95"
                            x-data="{
                                selectedCount: 0,
                                refresh() {
                                    this.selectedCount = Array.from(document.querySelectorAll('.fi-ta-record-checkbox:checked')).length
                                },
                                init() {
                                    this.refresh()
                                    this._interval = setInterval(() => this.refresh(), 400)
                                }
                            }"
                            x-init="init()"
                        >
                            <div class="mb-2 flex items-center justify-between text-xs font-medium text-gray-600 dark:text-gray-300">
                                <span>Page {{ $pageNum }} / {{ $totalPagesKnown ? $totalPages : '--' }}</span>
                                <span>Selected <span x-text="selectedCount"></span></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                                <x-filament::button
                                    type="button"
                                    color="gray"
                                    wire:click="previousPage"
                                    :disabled="$pageNum <= 1"
                                    wire:loading.attr="disabled"
                                    class="justify-center"
                                    aria-label="Previous page"
                                >
                                    <x-filament::icon icon="heroicon-o-chevron-left" class="mr-1 h-4 w-4" />
                                    Prev
                                </x-filament::button>

                                <x-filament::button
                                    type="button"
                                    wire:click="loadMore"
                                    :disabled="! $canLoadMore"
                                    wire:loading.attr="disabled"
                                    class="justify-center"
                                >
                                    Load More
                                </x-filament::button>

                                <x-filament::button
                                    type="button"
                                    color="gray"
                                    wire:click="nextPage"
                                    :disabled="! $canLoadMore"
                                    wire:loading.attr="disabled"
                                    class="justify-center"
                                    aria-label="Next page"
                                >
                                    Next
                                    <x-filament::icon icon="heroicon-o-chevron-right" class="ml-1 h-4 w-4" />
                                </x-filament::button>

                                <x-filament::button
                                    type="button"
                                    color="primary"
                                    x-on:click.prevent="$wire.queueImportSelectedByKeys(Array.from(document.querySelectorAll('.fi-ta-record-checkbox:checked')).map((el) => el.value))"
                                    x-bind:disabled="selectedCount < 1"
                                    wire:loading.attr="disabled"
                                    class="justify-center"
                                >
                                    Import Selected
                                </x-filament::button>
                            </div>
                        </div>
                    @endif
                </x-filament::section>

                @if ($existingCatalog)
                    <x-filament::section heading="Imported Matches" description="Products already linked to local records." icon="heroicon-o-check-badge" :collapsible="true">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach (array_slice($existingCatalog, 0, 10) as $pid => $entry)
                                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        PID {{ $pid }}
                                        @if (! empty($entry['synced_at']))
                                            • Synced {{ $entry['synced_at'] }}
                                        @endif
                                    </p>
                                    <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $entry['name'] }}</p>
                                    <a
                                        href="{{ \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $entry['id']]) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-2 inline-flex text-xs font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                                    >
                                        Open Product
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif

                @if (config('app.debug'))
                    <x-filament::section heading="Developer Payload" description="Raw API payload for troubleshooting." icon="heroicon-o-code-bracket-square" :collapsible="true" :collapsed="true">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                            <pre class="overflow-auto text-xs text-gray-700 dark:text-gray-300">{{ $json($products) }}</pre>
                        </div>
                    </x-filament::section>
                @endif
            </section>
        </div>
    </div>

    @php
        $imagePreviewModalId = $this->getImagePreviewModalId();
    @endphp

    <x-filament::modal
        :id="$imagePreviewModalId"
        :heading="$imagePreviewName ?? 'Product Preview'"
        :close-by-clicking-away="true"
        :close-by-escaping="true"
        :teleport="'body'"
        :width="'4xl'"
        :x-on:modal-closed="'if ($event.detail.id === ' . \Illuminate\Support\Js::from($imagePreviewModalId) . ') $wire.closeImagePreview()'"
    >
        @php
            $imageCount = count($imagePreviewUrls);
            $videoCount = count($videoPreviewUrls);
            $activeImageIndex = max(1, (int) (array_search($imagePreviewUrl, $imagePreviewUrls, true) ?: 0) + 1);
        @endphp
        <div class="grid gap-4 lg:grid-cols-[340px_minmax(0,1fr)]">
            <div class="space-y-3">
                <div class="relative flex min-h-[320px] items-center justify-center overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900">
                    @if ($imagePreviewUrl)
                        <img
                            src="{{ $imagePreviewUrl }}"
                            alt="{{ $imagePreviewName ?? 'CJ product image' }}"
                            class="h-[320px] w-[260px] rounded-xl object-contain sm:h-[360px] sm:w-[300px]"
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

                @if ($imageCount > 0)
                    <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Gallery {{ $activeImageIndex }} / {{ $imageCount }}
                        </p>
                        <div class="grid grid-cols-4 gap-2 sm:grid-cols-5">
                            @foreach ($imagePreviewUrls as $url)
                                <button
                                    type="button"
                                    wire:click="setActivePreviewImage({{ \Illuminate\Support\Js::from($url) }})"
                                    class="{{ $imagePreviewUrl === $url
                                        ? 'group relative overflow-hidden rounded-lg border border-primary-500 ring-2 ring-primary-300 shadow-md'
                                        : 'group relative overflow-hidden rounded-lg border border-gray-200 hover:border-primary-400 hover:shadow-sm dark:border-gray-700' }}"
                                    aria-label="Set preview image"
                                >
                                    <img
                                        src="{{ $url }}"
                                        alt="Preview thumbnail"
                                        class="h-12 w-full object-cover transition-transform group-hover:scale-105"
                                        loading="lazy"
                                    />
                                    @if ($imagePreviewUrl === $url)
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/20">
                                            <x-filament::icon icon="heroicon-s-check-circle" class="h-5 w-5 text-white" />
                                        </div>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <aside class="space-y-3">
                <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Product Detail</p>
                    <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $imagePreviewName ?? 'CJ Product' }}
                    </h3>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($imagePreviewPid)
                            <x-filament::badge color="gray">PID {{ $imagePreviewPid }}</x-filament::badge>
                        @endif
                        <x-filament::badge color="primary">{{ number_format($imageCount) }} Images</x-filament::badge>
                        @if ($videoCount > 0)
                            <x-filament::badge color="success">{{ number_format($videoCount) }} Videos</x-filament::badge>
                        @endif
                    </div>
                    <div class="mt-4 space-y-2 rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-700 dark:bg-gray-800/60">
                        <div class="flex items-center justify-between text-gray-700 dark:text-gray-200">
                            <span>Active media</span>
                            <span class="font-medium">{{ $activeImageIndex }} / {{ max($imageCount, 1) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-gray-700 dark:text-gray-200">
                            <span>Image quality</span>
                            <span class="font-medium">Original from CJ</span>
                        </div>
                        <div class="flex items-center justify-between text-gray-700 dark:text-gray-200">
                            <span>Use case</span>
                            <span class="font-medium">Catalog preview</span>
                        </div>
                    </div>
                </div>

                @if ($videoCount > 0)
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Product Videos
                        </p>
                        <div class="max-h-[44vh] space-y-3 overflow-auto pr-1">
                            @foreach ($videoPreviewUrls as $videoUrl)
                                <video
                                    controls
                                    preload="metadata"
                                    class="w-full rounded-lg border border-gray-200 bg-black shadow-sm dark:border-gray-700"
                                    src="{{ $videoUrl }}"
                                ></video>
                            @endforeach
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </x-filament::modal>
</x-filament-panels::page>
