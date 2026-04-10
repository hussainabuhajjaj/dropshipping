@php
    $json = fn ($value) => $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;

    $list = $results['list'] ?? $results['data'] ?? $results;
    $list = is_array($list) ? collect($list)->filter(fn ($item) => is_array($item))->values() : collect();

    $sourceProductsList = is_array($sourceProducts ?? null)
        ? collect($sourceProducts)->filter(fn ($item) => is_array($item))->values()
        : collect();

    $manualSourceIds = collect(preg_split('/[\s,]+/', (string) ($sourceIdsInput ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [])
        ->map(fn ($id) => trim((string) $id))
        ->filter()
        ->unique()
        ->values();

    $listSourceIds = $list
        ->map(fn (array $item) => $item['cjSourcingId'] ?? $item['sourcingId'] ?? $item['sourceId'] ?? null)
        ->filter(fn ($id) => is_scalar($id) && trim((string) $id) !== '')
        ->map(fn ($id) => trim((string) $id))
        ->unique()
        ->values();

    $resolvedSourceIds = $manualSourceIds->isNotEmpty() ? $manualSourceIds : $listSourceIds;
    $invalidLookupIds = collect($invalidLookupIds ?? []);
    $notFoundLookupIds = collect($notFoundLookupIds ?? []);
    $importedResolvedProductIds = collect($importedResolvedProductIds ?? []);
    $selectedResolvedProductIds = collect($selectedResolvedProductIds ?? []);
    $importPreviewProductsList = collect($importPreviewProducts ?? [])->filter(fn ($item) => is_array($item))->values();
    $importPreviewFailedPidsList = collect($importPreviewFailedPids ?? [])->filter()->values();
    $categoryOptions = is_array($categoryOptions ?? null) ? $categoryOptions : [];
    $statusFilter = filled($statusFilter ?? null) ? (string) $statusFilter : null;

    $matchedCjProducts = $sourceProductsList
        ->filter(fn (array $item) => filled($item['cjProductId'] ?? null))
        ->count();

    $matchedVariantSkus = $sourceProductsList
        ->filter(fn (array $item) => filled($item['cjVariantSku'] ?? null))
        ->count();

    $statusSummary = $sourceProductsList
        ->groupBy(fn (array $item) => trim((string) ($item['sourceStatusStr'] ?? $item['sourceStatus'] ?? 'Unknown')))
        ->map(fn ($items) => $items->count())
        ->sortDesc();

    $filteredSourceProductsList = $statusFilter
        ? $sourceProductsList
            ->filter(fn (array $item) => trim((string) ($item['sourceStatusStr'] ?? $item['sourceStatus'] ?? 'Unknown')) === $statusFilter)
            ->values()
        : $sourceProductsList;

    $recentRows = $list->isNotEmpty()
        ? $list->map(fn (array $item) => [
            'displayCode' => $item['sourceNumber'] ?? $item['cjSourcingId'] ?? $item['sourcingId'] ?? $item['sourceId'] ?? '—',
            'internalId' => $item['cjSourcingId'] ?? $item['sourcingId'] ?? $item['sourceId'] ?? null,
            'sourceStyle' => $item['source'] ?? $item['allStyle'] ?? $item['sourceType'] ?? 'Marketplace',
            'detailName' => $item['productName'] ?? $item['title'] ?? ($item['productUrl'] ?? $item['url'] ?? '—'),
            'detailProductId' => $item['productId'] ?? $item['sourceProductId'] ?? null,
            'detailUrl' => $item['productUrl'] ?? $item['url'] ?? null,
            'targetPrice' => $item['targetPrice'] ?? $item['target_price'] ?? null,
            'status' => $item['status'] ?? $item['sourceStatus'] ?? 'Unknown',
            'statusLabel' => $item['statusLabel'] ?? $item['sourceStatusStr'] ?? ($item['status'] ?? $item['sourceStatus'] ?? 'Unknown'),
            'cjProductId' => $item['cjProductId'] ?? $item['spu'] ?? $item['productSku'] ?? null,
            'cjVariantSku' => $item['cjVariantSku'] ?? null,
            'createdAt' => $item['createDate'] ?? $item['createdAt'] ?? '—',
            'updatedAt' => $item['updateDate'] ?? $item['updatedAt'] ?? null,
            'isImported' => $importedResolvedProductIds->contains((string) ($item['cjProductId'] ?? $item['spu'] ?? $item['productSku'] ?? '')),
            'isSelected' => $selectedResolvedProductIds->contains((string) ($item['cjProductId'] ?? $item['spu'] ?? $item['productSku'] ?? '')),
        ])
        : $sourceProductsList->map(fn (array $item) => [
            'displayCode' => $item['sourceNumber'] ?? $item['sourceId'] ?? '—',
            'internalId' => $item['sourceId'] ?? null,
            'sourceStyle' => $item['shopName'] ?? 'Bulk lookup',
            'detailName' => 'Bulk lookup result',
            'detailProductId' => $item['productId'] ?? null,
            'detailUrl' => null,
            'targetPrice' => null,
            'status' => $item['sourceStatus'] ?? 'Unknown',
            'statusLabel' => $item['sourceStatusStr'] ?? ($item['sourceStatus'] ?? 'Unknown'),
            'cjProductId' => $item['cjProductId'] ?? null,
            'cjVariantSku' => $item['cjVariantSku'] ?? null,
            'createdAt' => '—',
            'updatedAt' => null,
            'isImported' => $importedResolvedProductIds->contains((string) ($item['cjProductId'] ?? '')),
            'isSelected' => $selectedResolvedProductIds->contains((string) ($item['cjProductId'] ?? '')),
        ]);

    $badgeColor = function (?string $status): string {
        $status = str()->lower(trim((string) $status));

        return match (true) {
            $status === '',
            $status === 'unknown' => 'gray',
            str_contains($status, 'success'),
            str_contains($status, 'completed'),
            str_contains($status, 'approved'),
            str_contains($status, 'matched'),
            str_contains($status, 'done') => 'success',
            str_contains($status, 'pending'),
            str_contains($status, 'processing'),
            str_contains($status, 'waiting'),
            str_contains($status, 'review') => 'warning',
            str_contains($status, 'fail'),
            str_contains($status, 'error'),
            str_contains($status, 'reject'),
            str_contains($status, 'closed') => 'danger',
            default => 'primary',
        };
    };

    $stats = [
        ['label' => 'Requests', 'value' => $list->count(), 'description' => 'Current sourcing page from CJ.', 'color' => 'gray'],
        ['label' => 'Ready IDs', 'value' => $resolvedSourceIds->count(), 'description' => 'IDs available for the next lookup.', 'color' => 'primary'],
        ['label' => 'CJ Products', 'value' => $matchedCjProducts, 'description' => 'Resolved sourcing records with a CJ product id.', 'color' => 'success'],
        ['label' => 'Not Found', 'value' => $notFoundLookupIds->count(), 'description' => 'IDs CJ returned no data for.', 'color' => 'warning'],
    ];
@endphp

<x-filament::page>
    <div class="space-y-6">
        <x-filament::section
            heading="CJ Sourcing"
            description="Create sourcing requests, bulk query sourcing IDs, and inspect CJ mappings from one workspace."
        >
            <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-4">
                @foreach ($stats as $stat)
                    <x-filament::card>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</div>
                                <x-filament::badge :color="$stat['color']">{{ number_format($stat['value']) }}</x-filament::badge>
                            </div>
                            <div class="text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($stat['value']) }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['description'] }}</div>
                        </div>
                    </x-filament::card>
                @endforeach
            </div>
        </x-filament::section>

        <div class="grid gap-6 2xl:grid-cols-2">
            <x-filament::section
                heading="Create Request"
                description="Submit a fresh supplier URL to CJ."
                icon="heroicon-o-plus-circle"
            >
                <x-filament::card>
                    <form wire:submit.prevent="createRequest" class="space-y-5">
                        <div class="grid gap-4 xl:grid-cols-[1.3fr,0.7fr]">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Product URL</label>
                                <x-filament::input
                                    wire:model.defer="productUrl"
                                    type="url"
                                    required
                                    class="w-full"
                                    placeholder="https://supplier.example/product"
                                />
                                @error('productUrl')
                                    <p class="text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Source ID</label>
                                <x-filament::input
                                    wire:model.defer="sourceId"
                                    type="text"
                                    required
                                    class="w-full"
                                    placeholder="Marketplace item id"
                                />
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Used only for create-request submissions.
                                </p>
                                @error('sourceId')
                                    <p class="text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Internal note</label>
                            <textarea
                                wire:model.defer="note"
                                rows="4"
                                class="fi-input block w-full"
                                placeholder="Optional sourcing context"
                            ></textarea>
                        </div>

                        <div class="flex justify-end">
                            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                                Submit request
                            </x-filament::button>
                        </div>
                    </form>
                </x-filament::card>
            </x-filament::section>

            <x-filament::section
                heading="Bulk Lookup"
                description="Query one or many CJ sourcing ids and resolve CJ product mappings."
                icon="heroicon-o-magnifying-glass-circle"
            >
                <x-filament::card>
                    <form wire:submit.prevent="fetchSourceProducts" class="space-y-5">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Source IDs</label>
                            <textarea
                                wire:model.defer="sourceIdsInput"
                                rows="6"
                                class="fi-input block w-full font-mono text-sm"
                                placeholder="CJSPU956416427&#10;CJSPU956416421&#10;CJSPU956416413"
                            ></textarea>
                        </div>

	                        <div class="flex flex-wrap gap-2">
	                            <x-filament::badge color="success">Bulk lookup</x-filament::badge>
	                            <x-filament::badge color="gray">{{ $manualSourceIds->isNotEmpty() ? 'Manual mode' : 'Using request list IDs' }}</x-filament::badge>
	                            <x-filament::badge color="primary">{{ $resolvedSourceIds->count() }} ready</x-filament::badge>
	                            @if ($invalidLookupIds->isNotEmpty())
	                                <x-filament::badge color="danger">{{ $invalidLookupIds->count() }} invalid</x-filament::badge>
	                            @endif
	                            @if ($notFoundLookupIds->isNotEmpty())
	                                <x-filament::badge color="warning">{{ $notFoundLookupIds->count() }} not found</x-filament::badge>
	                            @endif
	                        </div>

	                        <div class="flex flex-wrap items-center gap-2">
	                            <x-filament::button
	                                type="button"
	                                color="gray"
	                                size="sm"
	                                wire:click="normalizeSourceIdsInput"
	                            >
	                                Normalize IDs
	                            </x-filament::button>
	                        </div>

	                        <x-filament::fieldset label="Lookup rules">
	                            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
	                                <p>Paste the sourcing ids exactly as CJ gives them, including alphanumeric ids like <span class="font-mono">CJSPU...</span>.</p>
                                <p>If a bulk request fails, the page retries ids individually and keeps partial results.</p>
                            </div>
                        </x-filament::fieldset>

                        <x-filament::fieldset label="Import options">
                            <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Batch size</label>
                                    <x-filament::input
                                        wire:model.live="importBatchSize"
                                        type="number"
                                        min="1"
                                        max="50"
                                    />
                                </div>

                                <div class="space-y-2 lg:col-span-2">
	                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Default category (optional)</label>
	                                    <select wire:model.live="importDefaultCategoryId" class="fi-input block w-full">
	                                        <option value="">No default category</option>
	                                        @foreach ($categoryOptions as $categoryId => $categoryName)
	                                            <option value="{{ $categoryId }}">{{ $categoryName }}</option>
	                                        @endforeach
	                                    </select>
	                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 lg:grid-cols-2 xl:grid-cols-4">
                                <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                    <input type="checkbox" wire:model.live="importEnrich" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    <span>Fetch full product details</span>
                                </label>

                                <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                    <input type="checkbox" wire:model.live="importAutoActivate" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    <span>Auto-activate if valid</span>
                                </label>

                                <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                    <input type="checkbox" wire:model.live="importSkipExisting" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    <span>Skip already imported</span>
                                </label>

                                <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                    <input type="checkbox" wire:model.live="importQueueTranslations" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    <span>Queue translations</span>
                                </label>

                                <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                    <input type="checkbox" wire:model.live="importQueueSeo" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    <span>Generate SEO</span>
                                </label>

                                <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                    <input type="checkbox" wire:model.live="importForceReprice" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    <span>Force reprice existing products</span>
                                </label>
                            </div>
                        </x-filament::fieldset>

                        @if ($invalidLookupIds->isNotEmpty())
                            <x-filament::fieldset label="Skipped invalid ids">
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($invalidLookupIds as $id)
                                        <x-filament::badge color="danger">{{ $id }}</x-filament::badge>
                                    @endforeach
                                </div>
                            </x-filament::fieldset>
                        @endif

                        @if ($notFoundLookupIds->isNotEmpty())
                            <x-filament::fieldset label="No CJ record returned for these ids">
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($notFoundLookupIds as $id)
                                        <x-filament::badge color="warning">{{ $id }}</x-filament::badge>
                                    @endforeach
                                </div>
                            </x-filament::fieldset>
                        @endif

                        @if ($matchedCjProducts > 0)
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::badge color="primary">{{ $selectedResolvedProductIds->count() }} selected</x-filament::badge>
                                    <x-filament::badge color="success">{{ $matchedCjProducts }} resolved</x-filament::badge>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <x-filament::button type="button" color="gray" size="sm" wire:click="selectAllResolvedProducts">
                                        Select all
                                    </x-filament::button>
                                    <x-filament::button type="button" color="gray" size="sm" wire:click="clearResolvedProductSelection">
                                        Clear
                                    </x-filament::button>
                                    <x-filament::button
                                        type="button"
                                        size="sm"
                                        wire:click="previewSelectedResolvedProducts"
                                    >
                                        Preview selected
                                    </x-filament::button>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Import uses the CJ Catalog pipeline with weight-based pricing, batch size {{ (int) $importBatchSize }}, enrich {{ $importEnrich ? 'on' : 'off' }}, auto-activate {{ $importAutoActivate ? 'on' : 'off' }}, skip existing {{ $importSkipExisting ? 'on' : 'off' }}, translations {{ $importQueueTranslations ? 'on' : 'off' }}, SEO {{ $importQueueSeo ? 'on' : 'off' }}, force reprice {{ $importForceReprice ? 'on' : 'off' }}.
                            </div>
                        @endif

                        <div class="flex justify-end">
                            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                                Run lookup
                            </x-filament::button>
                        </div>
                    </form>
                </x-filament::card>
            </x-filament::section>
        </div>

        @if ($manualSourceIds->isNotEmpty())
            <x-filament::section
                heading="Lookup Preview"
                description="These ids will be sent to CJ."
                icon="heroicon-o-queue-list"
            >
                <x-filament::card>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($manualSourceIds as $id)
                            <x-filament::badge color="gray">{{ $id }}</x-filament::badge>
                        @endforeach
                    </div>
                </x-filament::card>
            </x-filament::section>
        @endif

        <div class="grid gap-6 xl:grid-cols-[0.95fr,1.05fr]">
            <x-filament::section
                heading="Recent Requests"
                description="{{ $list->isNotEmpty() ? 'Latest records returned by the CJ sourcing list API.' : 'CJ list API returned no rows, so this table is showing your bulk lookup results.' }}"
                icon="heroicon-o-clipboard-document-list"
            >
                <x-filament::card>
                    <form wire:submit.prevent="refreshList" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-[100px,120px,auto]">
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Page</label>
                                <x-filament::input wire:model.defer="pageNum" type="number" min="1" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Page size</label>
                                <x-filament::input wire:model.defer="pageSize" type="number" min="1" max="200" />
                            </div>
                            <div class="flex items-end justify-start sm:justify-end">
                                <x-filament::button type="submit" color="gray" icon="heroicon-o-arrow-path">
                                    Refresh
                                </x-filament::button>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                    <thead class="bg-gray-50 dark:bg-gray-900/60">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Select</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Source</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Details</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">CJ Mapping</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Dates</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                        @forelse ($recentRows as $row)
                                            <tr class="align-top">
                                                <td class="px-4 py-4">
                                                    @if (! empty($row['cjProductId']) && ! $row['isImported'])
                                                        <input
                                                            type="checkbox"
                                                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                                            wire:click="toggleResolvedProductSelection('{{ addslashes((string) $row['cjProductId']) }}')"
                                                            @checked($row['isSelected'])
                                                        />
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="space-y-1">
                                                        <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['displayCode'] }}</div>
                                                        @if (! empty($row['internalId']) && $row['internalId'] !== $row['displayCode'])
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                Internal ID: <span class="font-mono">{{ $row['internalId'] }}</span>
                                                            </div>
                                                        @endif
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['sourceStyle'] }}</div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="space-y-1">
                                                        <div class="text-sm text-gray-900 dark:text-white">{{ $row['detailName'] }}</div>
                                                        @if (! empty($row['detailProductId']))
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                Product ID: <span class="font-mono">{{ $row['detailProductId'] }}</span>
                                                            </div>
                                                        @endif
                                                        @if (filled($row['targetPrice']))
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">Target Price: {{ $row['targetPrice'] }}</div>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <x-filament::badge :color="$badgeColor($row['statusLabel'])">{{ $row['statusLabel'] }}</x-filament::badge>
                                                    @if ($row['status'] !== $row['statusLabel'])
                                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Code {{ $row['status'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="space-y-1 text-xs text-gray-500 dark:text-gray-400">
                                                        <div>CJ Product: <span class="font-mono">{{ $row['cjProductId'] ?: '—' }}</span></div>
                                                        <div>Variant SKU: <span class="font-mono">{{ $row['cjVariantSku'] ?: '—' }}</span></div>
                                                        @if (! empty($row['cjProductId']) && $row['isImported'])
                                                            <div><x-filament::badge color="success">Imported</x-filament::badge></div>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="space-y-1 text-xs text-gray-500 dark:text-gray-400">
                                                        <div>{{ $row['createdAt'] }}</div>
                                                        @if (! empty($row['updatedAt']))
                                                            <div>{{ $row['updatedAt'] }}</div>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="flex flex-col gap-2">
                                                        <x-filament::button
                                                            size="sm"
                                                            wire:click="useSourceIdsForLookup('{{ addslashes((string) $row['displayCode']) }}')"
                                                        >
                                                            Query
                                                        </x-filament::button>
                                                        @if (! empty($row['cjProductId']))
                                                            <x-filament::button
                                                                size="sm"
                                                                color="gray"
                                                                wire:click="openImportPreview(['{{ addslashes((string) $row['cjProductId']) }}'], 'single')"
                                                            >
                                                                Preview
                                                            </x-filament::button>
                                                        @endif
                                                        @if (! empty($row['detailUrl']))
                                                            <x-filament::button
                                                                size="sm"
                                                                color="gray"
                                                                tag="a"
                                                                href="{{ $row['detailUrl'] }}"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                Open
                                                            </x-filament::button>
                                                        @endif
                                                        @if (! empty($row['cjProductId']))
                                                            @if ($row['isImported'])
                                                                <x-filament::button size="sm" color="success" disabled>
                                                                    Imported
                                                                </x-filament::button>
                                                            @else
                                                                <x-filament::button
                                                                    size="sm"
                                                                    color="primary"
                                                                    wire:click="openImportPreview(['{{ addslashes((string) $row['cjProductId']) }}'], 'single')"
                                                                >
                                                                    Preview &amp; Import
                                                                </x-filament::button>
                                                            @endif
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                                    No sourcing data available.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>
                </x-filament::card>
            </x-filament::section>

            <x-filament::section
                heading="Resolved Matches"
                description="Mappings returned by the bulk lookup."
                icon="heroicon-o-link"
            >
                <div class="space-y-4">
	                    @if ($statusSummary->isNotEmpty())
	                        <x-filament::card>
	                            <div class="flex flex-wrap gap-2">
	                                @foreach ($statusSummary as $status => $count)
	                                    <x-filament::badge
	                                        :color="$badgeColor($status)"
	                                        class="cursor-pointer"
	                                        wire:click="setStatusFilter('{{ addslashes((string) $status) }}')"
	                                        title="Filter by this status"
	                                    >
	                                        {{ $status }} · {{ $count }}
	                                    </x-filament::badge>
	                                @endforeach
	                                @if ($statusFilter)
	                                    <x-filament::badge color="warning">Filter: {{ $statusFilter }}</x-filament::badge>
	                                    <x-filament::button type="button" color="gray" size="sm" wire:click="setStatusFilter(null)">
	                                        Clear filter
	                                    </x-filament::button>
	                                @endif
	                            </div>
	                        </x-filament::card>
	                    @endif

	                    <div class="grid gap-4">
	                        @forelse ($filteredSourceProductsList as $item)
	                            @php
	                                $status = trim((string) ($item['sourceStatusStr'] ?? $item['sourceStatus'] ?? 'Unknown'));
	                                $displayCode = $item['sourceNumber'] ?? $item['sourceId'] ?? '—';
	                                $internalId = $item['sourceId'] ?? null;
                                $cjProductId = $item['cjProductId'] ?? null;
                                $cjVariantSku = $item['cjVariantSku'] ?? null;
                                $isImported = $cjProductId ? $importedResolvedProductIds->contains((string) $cjProductId) : false;
                                $isSelected = $cjProductId ? $selectedResolvedProductIds->contains((string) $cjProductId) : false;
                            @endphp

                            <x-filament::card>
                                <div class="space-y-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-filament::badge :color="$badgeColor($status)">{{ $status }}</x-filament::badge>
                                        @if (! empty($item['sourceStatus']) && ($item['sourceStatusStr'] ?? null))
                                            <x-filament::badge color="gray">Code {{ $item['sourceStatus'] }}</x-filament::badge>
                                        @endif
                                        @if ($isImported)
                                            <x-filament::badge color="success">Imported</x-filament::badge>
                                        @endif
                                        @if ($isSelected && ! $isImported)
                                            <x-filament::badge color="primary">Selected</x-filament::badge>
                                        @endif
                                    </div>

                                    <div class="grid gap-4 xl:grid-cols-3">
                                        <x-filament::fieldset label="Source">
                                            <div class="space-y-2">
                                                <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $displayCode }}</div>
                                                @if ($internalId && $internalId !== $displayCode)
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        Internal ID: <span class="font-mono">{{ $internalId }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </x-filament::fieldset>

                                        <x-filament::fieldset label="Marketplace record">
                                            <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                                <div>Product: <span class="font-mono">{{ $item['productId'] ?? '—' }}</span></div>
                                                <div>Variant: <span class="font-mono">{{ $item['variantId'] ?? '—' }}</span></div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $item['shopName'] ?? '—' }} · {{ $item['shopId'] ?? '—' }}
                                                </div>
                                            </div>
                                        </x-filament::fieldset>

                                        <x-filament::fieldset label="CJ mapping">
                                            @if ($cjProductId || $cjVariantSku)
                                                <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                                    <div>CJ Product: <span class="font-mono">{{ $cjProductId ?: '—' }}</span></div>
                                                    <div>Variant SKU: <span class="font-mono">{{ $cjVariantSku ?: '—' }}</span></div>
                                                    @if ($cjProductId)
                                                        <div class="flex flex-wrap gap-2 pt-2">
                                                            @if (! $isImported)
                                                                <x-filament::button
                                                                    size="sm"
                                                                    color="{{ $isSelected ? 'gray' : 'primary' }}"
                                                                    wire:click="toggleResolvedProductSelection('{{ addslashes((string) $cjProductId) }}')"
                                                                >
                                                                    {{ $isSelected ? 'Unselect' : 'Select' }}
                                                                </x-filament::button>
                                                            @endif
                                                            @if ($cjProductId)
                                                                <x-filament::button
                                                                    size="sm"
                                                                    color="gray"
                                                                    wire:click="openImportPreview(['{{ addslashes((string) $cjProductId) }}'], 'single')"
                                                                >
                                                                    Preview
                                                                </x-filament::button>
                                                            @endif
                                                            @if ($isImported)
                                                                <x-filament::button size="sm" color="success" disabled>
                                                                    Imported
                                                                </x-filament::button>
                                                            @else
                                                                <x-filament::button
                                                                    size="sm"
                                                                    wire:click="openImportPreview(['{{ addslashes((string) $cjProductId) }}'], 'single')"
                                                                >
                                                                    Preview &amp; Import
                                                                </x-filament::button>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="text-sm text-gray-500 dark:text-gray-400">No CJ mapping returned yet.</div>
                                            @endif
                                        </x-filament::fieldset>
                                    </div>
                                </div>
                            </x-filament::card>
                        @empty
                            <x-filament::card>
                                <div class="text-center text-sm text-gray-500 dark:text-gray-400">
                                    No source-product matches loaded yet.
                                </div>
                            </x-filament::card>
                        @endforelse
                    </div>
                </div>
            </x-filament::section>
        </div>

        <x-filament::section
            collapsible
            collapsed
            heading="Debug Payloads"
            description="Expand only when you need the raw CJ responses."
            icon="heroicon-o-code-bracket-square"
        >
            <div class="grid gap-4 xl:grid-cols-2">
                <x-filament::card>
                    <x-filament::fieldset label="Sourcing list response">
                        <pre class="overflow-auto rounded-xl border bg-gray-50 p-3 text-xs text-gray-700 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-200">{{ $json($results) }}</pre>
                    </x-filament::fieldset>
                </x-filament::card>

                <x-filament::card>
                    <x-filament::fieldset label="Source-product response">
                        <pre class="overflow-auto rounded-xl border bg-gray-50 p-3 text-xs text-gray-700 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-200">{{ $json($sourceProductsList) }}</pre>
                    </x-filament::fieldset>
                </x-filament::card>
            </div>
        </x-filament::section>
    </div>

    @php
        $importPreviewModalId = $this->getImportPreviewModalId();
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
                <div class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-300">
                    {{ $importPreviewError }}
                </div>
            @endif

            @if ($importPreviewFailedPidsList->isNotEmpty())
                <x-filament::fieldset label="Preview failed for these CJ products">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($importPreviewFailedPidsList as $pid)
                            <x-filament::badge color="danger">{{ $pid }}</x-filament::badge>
                        @endforeach
                    </div>
                </x-filament::fieldset>
            @endif

            <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-1">
                @foreach ($importPreviewProductsList as $preview)
                    @php
                        $previewProduct = is_array($preview['product'] ?? null) ? $preview['product'] : [];
                        $previewVariants = collect($preview['variants'] ?? [])->filter(fn ($item) => is_array($item))->values();
                        $previewImages = collect($preview['images'] ?? [])->filter()->values();
                        $previewValidation = is_array($preview['validation'] ?? null) ? $preview['validation'] : [];
                        $primaryImage = $previewImages->first();
                    @endphp

                    <x-filament::card>
                        <div class="space-y-4">
                            <div class="grid gap-5 xl:grid-cols-[160px,1fr]">
                                <div class="space-y-3">
                                    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/60">
                                        @if ($primaryImage)
                                            <img src="{{ $primaryImage }}" alt="" class="h-36 w-full object-cover" />
                                        @else
                                            <div class="flex h-36 items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                                                No image returned
                                            </div>
                                        @endif
                                    </div>
                                    @if ($previewImages->count() > 1)
                                        <div class="grid grid-cols-3 gap-2">
                                            @foreach ($previewImages->slice(1, 5) as $image)
                                                <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/60">
                                                    <img src="{{ $image }}" alt="" class="h-12 w-full object-cover" />
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="space-y-2">
                                                <div class="text-xl font-semibold text-gray-950 dark:text-white">
                                                    {{ $previewProduct['productNameEn'] ?? $previewProduct['productSku'] ?? ($preview['pid'] ?? 'CJ product') }}
                                                </div>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $previewProduct['categoryName'] ?? 'No category returned' }}
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
                                    </div>

                                    <div class="grid gap-4 xl:grid-cols-[1.15fr,0.85fr]">
                                        <div class="space-y-4">
                                            <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-900/50">
                                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Product overview</div>
                                                <div class="mt-3 flex flex-wrap items-end gap-4">
                                                    <div>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">Base CJ price</div>
                                                        <div class="text-2xl font-semibold text-gray-950 dark:text-white">${{ $previewProduct['sellPrice'] ?? '—' }}</div>
                                                    </div>
                                                    <div>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">Suggested sell range</div>
                                                        <div class="text-lg font-medium text-gray-900 dark:text-white">{{ $previewProduct['suggestSellPrice'] ?? '—' }}</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="grid gap-4 md:grid-cols-2">
                                                <x-filament::fieldset label="Product details">
                                                    <div class="mb-3 text-xs text-gray-500 dark:text-gray-400">Core CJ product metadata that will be imported into the catalog.</div>
                                                    <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                                        <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Status</span><span>{{ $previewProduct['status'] ?? '—' }}</span></div>
                                                        <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Type</span><span>{{ $previewProduct['productType'] ?? '—' }}</span></div>
                                                        <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Weight</span><span>{{ $previewProduct['productWeight'] ?? '—' }}</span></div>
                                                        <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Listed Num</span><span>{{ $previewProduct['listedNum'] ?? '—' }}</span></div>
                                                    </div>
                                                </x-filament::fieldset>

                                                <x-filament::fieldset label="Import validation">
                                                    <div class="mb-3 text-xs text-gray-500 dark:text-gray-400">Quick checks before import is allowed.</div>
                                                    <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                                        <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Variants</span><span>{{ $previewValidation['variants_count'] ?? $previewVariants->count() }}</span></div>
                                                        <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Import mode</span><span>{{ $previewVariants->isEmpty() ? 'Single product' : 'Variant product' }}</span></div>
                                                        <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Missing prices</span><span>{{ $previewValidation['variants_missing_price'] ?? 0 }}</span></div>
                                                        <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Missing inventory</span><span>{{ $previewValidation['variants_missing_inventory'] ?? 0 }}</span></div>
                                                        <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Pricing engine</span><span>Weight based</span></div>
                                                    </div>
                                                </x-filament::fieldset>
                                            </div>
                                        </div>

                                        <div class="space-y-4">
                                            <x-filament::fieldset label="Import configuration">
                                                <div class="mb-3 text-xs text-gray-500 dark:text-gray-400">These settings are the exact options the importer will use after confirmation.</div>
                                                <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                                    <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Fetch details</span><span>{{ $importEnrich ? 'On' : 'Off' }}</span></div>
                                                    <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Auto-activate</span><span>{{ $importAutoActivate ? 'On' : 'Off' }}</span></div>
                                                    <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Skip existing</span><span>{{ $importSkipExisting ? 'On' : 'Off' }}</span></div>
                                                    <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Translations</span><span>{{ $importQueueTranslations ? 'On' : 'Off' }}</span></div>
                                                    <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">SEO</span><span>{{ $importQueueSeo ? 'On' : 'Off' }}</span></div>
                                                    <div class="flex items-center justify-between gap-4"><span class="text-gray-500 dark:text-gray-400">Force reprice</span><span>{{ $importForceReprice ? 'On' : 'Off' }}</span></div>
                                                </div>
                                            </x-filament::fieldset>

                                            @if (! empty($previewProduct['description']))
                                                <x-filament::fieldset label="Description">
                                                    <div class="max-h-36 overflow-auto text-sm leading-6 text-gray-600 dark:text-gray-300">
                                                        {!! \Illuminate\Support\Str::limit(strip_tags((string) $previewProduct['description']), 520) !!}
                                                    </div>
                                                </x-filament::fieldset>
                                            @endif
                                        </div>
                                    </div>

                                    @if (! empty($previewValidation['errors']))
                                        <div class="rounded-xl border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-300">
                                            <ul class="list-disc space-y-1 pl-5">
                                                @foreach ($previewValidation['errors'] as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <x-filament::fieldset label="Variants">
                                <div class="mb-3 flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ $previewVariants->count() }} variants</span>
                                    <span>•</span>
                                    <span>{{ $previewVariants->sum(fn ($variant) => collect($variant['inventories'] ?? [])->sum(fn ($inventory) => (int) ($inventory['totalInventory'] ?? 0))) }} units total inventory</span>
                                </div>
                                @if ($previewVariants->isEmpty())
                                    <div class="mb-3 rounded-xl border border-primary-200 bg-primary-50 p-3 text-sm text-primary-700 dark:border-primary-900/60 dark:bg-primary-950/30 dark:text-primary-300">
                                        CJ returned this as a single product without variants. Import will create the product without variant rows.
                                    </div>
                                @endif
                                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                            <thead class="bg-gray-50 dark:bg-gray-900/60">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Variant</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">SKU</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Sell Price</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Suggested</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Inventory</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                                @forelse ($previewVariants as $variant)
                                                    @php
                                                        $inventories = collect($variant['inventories'] ?? [])->filter(fn ($item) => is_array($item))->values();
                                                        $inventoryTotal = $inventories->sum(fn ($inventory) => (int) ($inventory['totalInventory'] ?? 0));
                                                    @endphp
                                                    <tr>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $variant['variantKey'] ?? $variant['variantNameEn'] ?? '—' }}</td>
                                                        <td class="px-4 py-3 text-xs font-mono text-gray-500 dark:text-gray-400">{{ $variant['variantSku'] ?? '—' }}</td>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">${{ $variant['variantSellPrice'] ?? '—' }}</td>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $variant['variantSugSellPrice'] ?? '—' }}</td>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $inventoryTotal }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No variants returned.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </x-filament::fieldset>
                        </div>
                    </x-filament::card>
                @endforeach
            </div>

            <div class="flex justify-end gap-3">
                <x-filament::button type="button" color="gray" x-on:click="$dispatch('close-modal', { id: {{ \Illuminate\Support\Js::from($importPreviewModalId) }} })">
                    Cancel
                </x-filament::button>
                <x-filament::button
                    type="button"
                    wire:click="confirmImportPreview"
                    :disabled="$importPreviewProductsList->isEmpty() || $importPreviewHasBlockingErrors"
                >
                    Confirm Import
                </x-filament::button>
            </div>
        </div>
    </x-filament::modal>
</x-filament::page>
