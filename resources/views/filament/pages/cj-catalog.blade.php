@php
    $json = fn ($value) => $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;
    $totalPages = max($totalPages ?? 1, 1);
    $totalPagesKnown = $totalPagesKnown ?? false;
    $canLoadMore = $canLoadMore ?? false;
    $activeFiltersCount = collect([
        $productName, $productSku, $materialKey, $categoryId, $categorySearch,
        $warehouseId, $sort, $storeProductId,
        $inStockOnly ? 'stock' : null, ($shipToCountry ?? null),
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
    $importPreviewProductsList = collect($importPreviewProducts ?? [])->filter(fn ($item) => is_array($item))->values();
    $importPreviewFailedPidsList = collect($importPreviewFailedPids ?? [])->filter()->values();
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                    CJ Catalog
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Discover, review, and import products from CJ Dropshipping.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <x-filament::badge color="gray">Page {{ $pageNum }} / {{ $totalPagesKnown ? $totalPages : '--' }}</x-filament::badge>
                <x-filament::badge color="gray">{{ number_format($loaded) }} results</x-filament::badge>
                @if ($shipToCountry)
                    <x-filament::badge color="primary">Ship-to {{ $shipToCountry }}</x-filament::badge>
                @endif
            </div>
        </div>

        <div role="status" aria-live="polite" wire:loading.flex class="items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
            <x-filament::loading-indicator class="h-4 w-4" />
            Updating CJ catalog...
        </div>

        <div class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
            <aside class="space-y-4 xl:sticky xl:top-6 xl:self-start">
                <x-filament::section heading="Filters" description="Set your query criteria." icon="heroicon-o-funnel" :collapsible="true">
                    <form wire:submit.prevent="applyFilters" class="space-y-4" role="search" aria-label="CJ catalog filters">
                        <div class="space-y-3">
                            <div>
                                <label for="productName" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Product name</label>
                                <x-filament::input.wrapper>
                                    <x-filament::input id="productName" wire:model.defer="productName" type="text" placeholder="Search by keyword" />
                                </x-filament::input.wrapper>
                            </div>
                            <div>
                                <label for="productSku" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">SKU</label>
                                <x-filament::input.wrapper>
                                    <x-filament::input id="productSku" wire:model.defer="productSku" type="text" placeholder="Exact SKU" />
                                </x-filament::input.wrapper>
                            </div>
                        </div>

                        <div class="space-y-3">
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
                                    <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">Warehouse list unavailable.</p>
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
                            <label for="inStockOnly" class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                <x-filament::input.checkbox id="inStockOnly" wire:model.defer="inStockOnly" />
                                In-stock only
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <x-filament::button type="submit" icon="heroicon-o-funnel" class="justify-center" wire:loading.attr="disabled">
                                Apply
                            </x-filament::button>
                            <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-path" class="justify-center" wire:click="resetFilters" wire:loading.attr="disabled">
                                Reset
                            </x-filament::button>
                        </div>
                    </form>
                </x-filament::section>

                @if ($activeImportTrackingKey)
                    <x-filament::section heading="Import Progress" icon="heroicon-o-arrow-path" :collapsible="true">
                        @include('filament.components.progress-indicator-selector', [
                            'activeImportTrackingKey' => $activeImportTrackingKey,
                            'importPercent' => $importPercent ?? 0,
                            'importTotal' => $importTotal ?? 0,
                            'importProcessed' => $importProcessed ?? 0,
                            'importFailed' => $importFailed ?? 0,
                            'importStatusLabel' => $importStatusLabel ?? 'idle',
                            'importStartedAt' => $importStartedAt ?? null,
                            'pollInterval' => $this->getImportPollIntervalSeconds(),
                            'selectedDesign' => 4,
                        ])
                    </x-filament::section>
                @endif

                @if ($lastCommandMessage)
                    <x-filament::section heading="Last Action" :collapsible="true">
                        <p class="text-sm text-gray-700 dark:text-gray-200">{{ $lastCommandMessage }}</p>
                        @if ($lastCommandAt)
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $lastCommandAt }}</p>
                        @endif
                    </x-filament::section>
                @endif
            </aside>

            <section class="space-y-4" wire:loading.attr="aria-busy" aria-live="polite">
                @if ($loaded === 0)
                    <x-filament::empty-state icon="heroicon-o-cube" heading="No products found" description="Change filters or refresh catalog to fetch products from CJ." />
                @else
                    {{ $this->table }}

                    <div class="sticky bottom-4 z-20 rounded-lg border border-gray-200 bg-white/95 p-3 shadow-sm backdrop-blur dark:border-gray-700 dark:bg-gray-900/95" x-data="{
                        selectedCount: 0,
                        refresh() { this.selectedCount = Array.from(document.querySelectorAll('.fi-ta-record-checkbox:checked')).length },
                        init() { this.refresh(); this._interval = setInterval(() => this.refresh(), 400) }
                    }" x-init="init()">
                        <div class="mb-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>Page {{ $pageNum }} / {{ $totalPagesKnown ? $totalPages : '--' }}</span>
                            <span>Selected <span x-text="selectedCount"></span></span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                            <x-filament::button type="button" color="gray" wire:click="previousPage" :disabled="$pageNum <= 1" wire:loading.attr="disabled" class="justify-center" aria-label="Previous page">
                                <x-filament::icon icon="heroicon-o-chevron-left" class="mr-1 h-4 w-4" /> Prev
                            </x-filament::button>
                            <x-filament::button type="button" wire:click="loadMore" :disabled="! $canLoadMore" wire:loading.attr="disabled" class="justify-center">
                                Load More
                            </x-filament::button>
                            <x-filament::button type="button" color="gray" wire:click="nextPage" :disabled="! $canLoadMore" wire:loading.attr="disabled" class="justify-center" aria-label="Next page">
                                Next <x-filament::icon icon="heroicon-o-chevron-right" class="ml-1 h-4 w-4" />
                            </x-filament::button>
                            <x-filament::button type="button" color="success" icon="heroicon-o-rocket-launch" x-on:click.prevent="
                                const selected = Array.from(document.querySelectorAll('.fi-ta-record-checkbox:checked')).map((el) => el.value);
                                if (selected.length > 0) { $wire.mountTableBulkAction('importPipeline', selected); }
                            " x-bind:disabled="selectedCount < 1" wire:loading.attr="disabled" class="justify-center">
                                Import Selected
                            </x-filament::button>
                        </div>
                    </div>
                @endif

                @if ($existingCatalog)
                    <x-filament::section heading="Imported Matches" description="Products already linked to local records." icon="heroicon-o-check-badge" :collapsible="true">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach (array_slice($existingCatalog, 0, 10) as $pid => $entry)
                                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        PID {{ $pid }} @if (! empty($entry['synced_at'])) • Synced {{ $entry['synced_at'] }} @endif
                                    </p>
                                    <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $entry['name'] }}</p>
                                    <a href="{{ \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $entry['id']]) }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex text-xs font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                                        Open Product
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif
            </section>
        </div>
    </div>

    @php
        $importPreviewModalId = $this->getImportPreviewModalId();
        $imagePreviewModalId = $this->getImagePreviewModalId();
        $importPreviewHasBlockingErrors = $importPreviewFailedPidsList->isNotEmpty()
            || $importPreviewProductsList->contains(fn ($preview) => ! (bool) (($preview['validation']['is_valid'] ?? false)));
    @endphp

    <x-filament::modal
        :id="$importPreviewModalId"
        heading="Preview before import"
        :close-by-clicking-away="true"
        :close-by-escaping="true"
        :teleport="'body'"
        :width="'7xl'"
        :x-on:modal-closed="'if ($event.detail.id === ' . \Illuminate\Support\Js::from($importPreviewModalId) . ') $wire.closeImportPreview()'"
    >
        <div class="space-y-4">
            @if ($importPreviewError)
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                    {{ $importPreviewError }}
                </div>
            @endif

            @if ($importPreviewFailedPidsList->isNotEmpty())
                <x-filament::section heading="Preview failed for these CJ products">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($importPreviewFailedPidsList as $pid)
                            <x-filament::badge color="danger">{{ $pid }}</x-filament::badge>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            <div class="max-h-[70vh] space-y-4 overflow-y-auto pr-1">
                @foreach ($importPreviewProductsList as $preview)
                    @php
                        $previewProduct = is_array($preview['product'] ?? null) ? $preview['product'] : [];
                        $previewVariants = collect($preview['variants'] ?? [])->filter(fn ($item) => is_array($item))->values();
                        $previewImages = collect($preview['images'] ?? [])->filter()->values();
                        $previewValidation = is_array($preview['validation'] ?? null) ? $preview['validation'] : [];
                        $primaryImage = $previewImages->first();
                    @endphp

                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <div class="grid gap-5 xl:grid-cols-[140px,1fr]">
                            <div class="space-y-2">
                                <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/60">
                                    @if ($primaryImage)
                                        <img src="{{ $primaryImage }}" alt="" class="h-32 w-full object-cover" />
                                    @else
                                        <div class="flex h-32 items-center justify-center text-sm text-gray-500 dark:text-gray-400">No image</div>
                                    @endif
                                </div>
                                @if ($previewImages->count() > 1)
                                    <div class="grid grid-cols-3 gap-1">
                                        @foreach ($previewImages->slice(1, 5) as $image)
                                            <div class="overflow-hidden rounded border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/60">
                                                <img src="{{ $image }}" alt="" class="h-10 w-full object-cover" />
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="space-y-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ $previewProduct['productNameEn'] ?? $previewProduct['productSku'] ?? ($preview['pid'] ?? 'CJ product') }}
                                        </div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $previewProduct['categoryName'] ?? 'No category' }}
                                            @if (! empty($previewProduct['productNameCn']))
                                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $previewProduct['productNameCn'] }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <x-filament::badge color="gray">PID {{ $preview['pid'] ?? '—' }}</x-filament::badge>
                                        @if (! empty($previewProduct['productSku']))
                                            <x-filament::badge color="gray">SKU {{ $previewProduct['productSku'] }}</x-filament::badge>
                                        @endif
                                        <x-filament::badge :color="($previewValidation['is_valid'] ?? false) ? 'success' : 'danger'">
                                            {{ ($previewValidation['is_valid'] ?? false) ? 'Ready' : 'Needs fixes' }}
                                        </x-filament::badge>
                                    </div>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-2">
                                    <div class="rounded-lg border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-900/50">
                                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">Product overview</div>
                                        <div class="mt-3 flex items-end gap-4">
                                            <div>
                                                <div class="text-xs text-gray-500">Base CJ price</div>
                                                <div class="text-xl font-semibold text-gray-900 dark:text-white">${{ $previewProduct['sellPrice'] ?? '—' }}</div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500">Suggested sell</div>
                                                <div class="text-base font-medium text-gray-900 dark:text-white">{{ $previewProduct['suggestSellPrice'] ?? '—' }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-lg border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-900/50">
                                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">Import validation</div>
                                        <div class="mt-3 space-y-1 text-sm text-gray-700 dark:text-gray-200">
                                            <div class="flex justify-between"><span class="text-gray-500">Variants</span><span>{{ $previewValidation['variants_count'] ?? $previewVariants->count() }}</span></div>
                                            <div class="flex justify-between"><span class="text-gray-500">Missing prices</span><span>{{ $previewValidation['variants_missing_price'] ?? 0 }}</span></div>
                                            <div class="flex justify-between"><span class="text-gray-500">Missing inventory</span><span>{{ $previewValidation['variants_missing_inventory'] ?? 0 }}</span></div>
                                        </div>
                                    </div>
                                </div>

                                @if (! empty($previewValidation['errors']))
                                    <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                                        <ul class="list-disc space-y-1 pl-5">
                                            @foreach ($previewValidation['errors'] as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if (! empty($previewValidation['warnings']))
                                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300">
                                        <ul class="list-disc space-y-1 pl-5">
                                            @foreach ($previewValidation['warnings'] as $warning)
                                                <li>{{ $warning }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <x-filament::section heading="Variants ({{ $previewVariants->count() }})" :collapsible="true">
                                    @if ($previewVariants->isEmpty())
                                        <p class="text-sm text-gray-500">CJ returned this as a single product without variants.</p>
                                    @else
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                                <thead class="bg-gray-50 dark:bg-gray-900/60">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Variant</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">SKU</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Price</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Suggested</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Inventory</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                                    @forelse ($previewVariants as $variant)
                                                        @php
                                                            $inventories = collect($variant['inventories'] ?? [])->filter(fn ($item) => is_array($item))->values();
                                                            $inventoryTotal = $inventories->sum(fn ($inventory) => (int) ($inventory['totalInventory'] ?? 0));
                                                        @endphp
                                                        <tr>
                                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ $variant['variantKey'] ?? $variant['variantNameEn'] ?? '—' }}</td>
                                                            <td class="px-4 py-2 text-xs font-mono text-gray-500">{{ $variant['variantSku'] ?? '—' }}</td>
                                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">${{ $variant['variantSellPrice'] ?? '—' }}</td>
                                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ $variant['variantSugSellPrice'] ?? '—' }}</td>
                                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ $inventoryTotal }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="5" class="px-6 py-6 text-center text-sm text-gray-500">No variants returned.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </x-filament::section>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-3">
                <x-filament::button type="button" color="gray" x-on:click="$dispatch('close-modal', { id: {{ \Illuminate\Support\Js::from($importPreviewModalId) }} })">
                    Cancel
                </x-filament::button>
                <x-filament::button type="button" wire:click="confirmImportPreview" :disabled="$importPreviewProductsList->isEmpty() || $importPreviewHasBlockingErrors">
                    Confirm Import
                </x-filament::button>
            </div>
        </div>
    </x-filament::modal>

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
        <div class="grid gap-4 lg:grid-cols-[300px_minmax(0,1fr)]">
            <div class="space-y-3">
                <div class="relative flex min-h-[280px] items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900">
                    @if ($imagePreviewUrl)
                        <img src="{{ $imagePreviewUrl }}" alt="{{ $imagePreviewName ?? 'CJ product image' }}" class="h-[280px] w-full rounded-lg object-contain sm:h-[320px]" loading="lazy" />
                    @else
                        <div class="flex h-56 items-center justify-center">
                            <div class="text-center">
                                <x-filament::icon icon="heroicon-o-photo" class="mx-auto h-10 w-10 text-gray-400" />
                                <p class="mt-2 text-sm text-gray-500">No image available</p>
                            </div>
                        </div>
                    @endif
                </div>

                @if ($imageCount > 0)
                    <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                        <p class="mb-2 text-xs text-gray-500">Gallery {{ $activeImageIndex }} / {{ $imageCount }}</p>
                        <div class="grid grid-cols-4 gap-2 sm:grid-cols-5">
                            @foreach ($imagePreviewUrls as $url)
                                <button type="button" wire:click="setActivePreviewImage({{ \Illuminate\Support\Js::from($url) }})"
                                    class="{{ $imagePreviewUrl === $url ? 'overflow-hidden rounded border border-primary-500 ring-2 ring-primary-300' : 'overflow-hidden rounded border border-gray-200 hover:border-primary-400 dark:border-gray-700' }}"
                                    aria-label="Set preview image">
                                    <img src="{{ $url }}" alt="" class="h-10 w-full object-cover" loading="lazy" />
                                    @if ($imagePreviewUrl === $url)
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/20">
                                            <x-filament::icon icon="heroicon-s-check-circle" class="h-4 w-4 text-white" />
                                        </div>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <aside class="space-y-3">
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Product Detail</p>
                    <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $imagePreviewName ?? 'CJ Product' }}</h3>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($imagePreviewPid)
                            <x-filament::badge color="gray">PID {{ $imagePreviewPid }}</x-filament::badge>
                        @endif
                        <x-filament::badge color="primary">{{ number_format($imageCount) }} Images</x-filament::badge>
                        @if ($videoCount > 0)
                            <x-filament::badge color="success">{{ number_format($videoCount) }} Videos</x-filament::badge>
                        @endif
                    </div>
                    <div class="mt-4 space-y-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-700 dark:bg-gray-800/60">
                        <div class="flex justify-between text-gray-700 dark:text-gray-200">
                            <span>Active media</span>
                            <span class="font-medium">{{ $activeImageIndex }} / {{ max($imageCount, 1) }}</span>
                        </div>
                    </div>
                </div>

                @if ($videoCount > 0)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Videos</p>
                        <div class="max-h-[40vh] space-y-3 overflow-auto pr-1">
                            @foreach ($videoPreviewUrls as $videoUrl)
                                <video controls preload="metadata" class="w-full rounded-lg border border-gray-200 bg-black dark:border-gray-700" src="{{ $videoUrl }}"></video>
                            @endforeach
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </x-filament::modal>
</x-filament-panels::page>
