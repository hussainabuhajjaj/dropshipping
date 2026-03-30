<x-filament::page>
    <div class="space-y-6">

        <x-filament::section>
            <x-slot name="heading">Authentication</x-slot>

            @php
                $token = $this->getToken();
                $isExpired = $token?->isExpired() ?? true;
                $canRefresh = $token?->canRefresh() ?? false;
            @endphp

            @if($token)
                <x-filament::card>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="font-semibold">
                                @if($isExpired)
                                    <span class="text-danger-600">✗ Token Expired</span>
                                @else
                                    <span class="text-success-600">✓ Connected to AliExpress</span>
                                @endif
                            </div>

                            <div class="text-xs text-gray-500 mt-1">
                                @if($token->expires_at)
                                    Expires: {{ $token->expires_at->format('Y-m-d H:i:s') }} ({{ $token->expires_at->diffForHumans() }})
                                @else
                                    Expiration time: Not set
                                @endif
                            </div>

                            @if($canRefresh && $isExpired)
                                <div class="mt-1 text-xs text-primary-600">
                                    ℹ️ You can refresh your token
                                </div>
                            @endif
                        </div>

                        <div class="flex gap-2 flex-wrap">
                            @if($isExpired)
                                @if($canRefresh)
                                    <x-filament::button color="primary" wire:click="refreshToken">
                                        Refresh Token
                                    </x-filament::button>
                                @else
                                    <x-filament::button color="danger" wire:click="authenticateWithAliExpress">
                                        Re-authenticate
                                    </x-filament::button>
                                @endif
                            @else
                                <x-filament::badge color="success">Token active</x-filament::badge>
                            @endif
                        </div>
                    </div>
                </x-filament::card>
            @else
                <x-filament::card>
                    <div class="flex items-center justify-between gap-4">
                        <div class="font-semibold text-warning-700">
                            ⚠ Not authenticated with AliExpress
                        </div>

                        <x-filament::button color="primary" wire:click="authenticateWithAliExpress">
                            Authenticate
                        </x-filament::button>
                    </div>
                </x-filament::card>
            @endif
        </x-filament::section>

        @if($token && ! $isExpired)

            <x-filament::section>
                <x-slot name="heading">Actions</x-slot>

                <div class="flex flex-wrap gap-3">
                    <x-filament::button color="info" wire:click="syncCategories" icon="heroicon-o-arrow-path">
                        Sync Categories
                    </x-filament::button>

                    <x-filament::button color="primary" wire:click="searchProducts" icon="heroicon-o-magnifying-glass">
                        Preview Products
                    </x-filament::button>

                    <x-filament::button
                        color="success"
                        wire:click="importSelectedProducts"
                        icon="heroicon-o-arrow-down-tray"
                        :disabled="empty($selectedProductIds)"
                    >
                        Review Selected ({{ is_countable($selectedProductIds) ? count($selectedProductIds) : 0 }})
                    </x-filament::button>

                    @if($previewed)
                        <x-filament::button
                            color="gray"
                            wire:click="$set('previewed', false)"
                            icon="heroicon-o-x-mark"
                        >
                            Clear Preview
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Filters</x-slot>

                <x-filament::card>
                    {{ $this->form }}
                </x-filament::card>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    Preview Results
                    @if($previewed)
                        <span class="text-xs text-gray-500">
                            Loaded: {{ $this->getLoadedCount() }} ({{ $this->getLoadedApiPageCount() }} API pages) ·
                            Selected: {{ $this->getSelectedCount() }} ·
                            Imported: {{ $this->getImportedCount() }}
                        </span>
                    @endif
                </x-slot>

                    @if($previewed && is_countable($searchResults) && count($searchResults))
                        {{ $this->table }}
                    @elseif($previewed)
                        <div class="text-sm text-gray-600">
                            No results. Adjust filters and preview again.
                        </div>
                    @else
                        <div class="text-sm text-gray-600">
                            Loading preview results...
                        </div>
                    @endif
            </x-filament::section>

        @elseif($token && $isExpired)
            <x-filament::section>
                <x-slot name="heading">Token Expired</x-slot>
                <x-filament::card>
                    <div class="text-sm text-danger-700">
                        Your AliExpress token has expired. Refresh or re-authenticate to continue.
                    </div>
                </x-filament::card>
            </x-filament::section>
        @endif

    </div>

    @php
        $importPreviewModalId = $this->getImportPreviewModalId();
        $importPreview = $importPreview ?? $this->importPreview;
        $previewImages = collect($importPreview['images'] ?? [])->filter()->values();
        $previewVariants = $this->getImportPreviewVariantRows();
        $previewAttributes = $this->getImportPreviewAttributeRows();
        $previewValidation = is_array($importPreview['validation'] ?? null) ? $importPreview['validation'] : [];
        $previewStore = is_array($importPreview['store'] ?? null) ? $importPreview['store'] : [];
        $previewPackage = is_array($importPreview['package'] ?? null) ? $importPreview['package'] : [];
        $previewLogistics = is_array($importPreview['logistics'] ?? null) ? $importPreview['logistics'] : [];
        $previewRequest = is_array($importPreview['request_options'] ?? null) ? $importPreview['request_options'] : [];
        $pricingPreview = is_array($importPreview['pricing_preview'] ?? null) ? $importPreview['pricing_preview'] : [];
        $optionStockSummary = $previewVariants
            ->flatMap(function (array $variant) {
                $stock = is_numeric($variant['stock'] ?? null) ? (int) $variant['stock'] : 0;
                $properties = is_array($variant['properties'] ?? null) ? $variant['properties'] : [];

                return collect($properties)->map(
                    fn ($propertyValue, $propertyName) => [
                        'key' => (string) $propertyName . '::' . (string) $propertyValue,
                        'property' => (string) $propertyName,
                        'value' => (string) $propertyValue,
                        'stock' => $stock,
                    ]
                )->values();
            })
            ->groupBy('key')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'property' => $first['property'] ?? 'Option',
                    'value' => $first['value'] ?? '—',
                    'stock' => $rows->sum('stock'),
                ];
            })
            ->sortBy([
                ['property', 'asc'],
                ['value', 'asc'],
            ])
            ->values();
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
            @if (! empty($previewValidation['errors']))
                <div class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-300">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($previewValidation['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($previewValidation['warnings']))
                <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-300">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($previewValidation['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-5 xl:grid-cols-[1.1fr,0.9fr]">
                <div class="space-y-4">
                    <x-filament::card>
                        <div class="space-y-4">
                            <div>
                                <div class="text-xl font-semibold text-gray-950 dark:text-white">
                                    {{ $this->importForm['title'] ?? ($importPreview['title'] ?? 'AliExpress Product') }}
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @if (! empty($importPreview['ali_item_id']))
                                        <x-filament::badge color="gray">Item {{ $importPreview['ali_item_id'] }}</x-filament::badge>
                                    @endif
                                    <x-filament::badge :color="($previewValidation['is_valid'] ?? false) ? 'success' : 'danger'">
                                        {{ ($previewValidation['is_valid'] ?? false) ? 'Ready' : 'Needs fixes' }}
                                    </x-filament::badge>
                                    <x-filament::badge color="warning">AliExpress</x-filament::badge>
                                </div>
                            </div>

                            @if ($previewImages->isNotEmpty())
                                <div class="space-y-3">
                                    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/60">
                                        <img src="{{ $previewImages->first() }}" alt="" class="h-64 w-full object-contain" />
                                    </div>
                                    @if ($previewImages->count() > 1)
                                        <div class="grid grid-cols-5 gap-2">
                                            @foreach ($previewImages->slice(1, 10) as $image)
                                                <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/60">
                                                    <img src="{{ $image }}" alt="" class="h-14 w-full object-cover" />
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if (! empty($pricingPreview))
                                <div class="grid gap-3 md:grid-cols-4">
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/60">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Our sell price</div>
                                        <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                                            {{ $previewVariants->first()['currency'] ?? 'USD' }} {{ number_format((float) ($pricingPreview['selling_price'] ?? 0), 2) }}
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/60">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Weight</div>
                                        <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                                            {{ number_format((float) ($pricingPreview['weight_kg'] ?? 0), 4) }} kg
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/60">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">External shipping</div>
                                        <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                                            {{ $previewVariants->first()['currency'] ?? 'USD' }} {{ number_format((float) ($pricingPreview['external_shipping'] ?? 0), 2) }}
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/60">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Landed cost</div>
                                        <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                                            {{ $previewVariants->first()['currency'] ?? 'USD' }} {{ number_format((float) ($pricingPreview['landed_cost'] ?? 0), 2) }}
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <x-filament::fieldset label="Editable fields">
                                <div class="space-y-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Product title</label>
                                        <input
                                            type="text"
                                            wire:model.defer="importForm.title"
                                            class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Description</label>
                                        <textarea
                                            wire:model.defer="importForm.description"
                                            rows="6"
                                            class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                                        ></textarea>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Category</label>
                                        <select
                                            wire:model.defer="importForm.category_id"
                                            class="fi-select-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                                        >
                                            <option value="">Select category</option>
                                            @foreach ($this->getImportCategoryOptions() as $categoryId => $categoryName)
                                                <option value="{{ $categoryId }}">{{ $categoryName }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="grid gap-4 md:grid-cols-3">
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Ship to country</label>
                                            <input
                                                type="text"
                                                wire:model.defer="importForm.ship_to_country"
                                                class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Target currency</label>
                                            <input
                                                type="text"
                                                wire:model.defer="importForm.target_currency"
                                                class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Target language</label>
                                            <select
                                                wire:model.defer="importForm.target_language"
                                                class="fi-select-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                                            >
                                                @foreach ($this->getAliExpressLanguageOptions() as $languageCode => $languageLabel)
                                                    <option value="{{ $languageCode }}">{{ $languageLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="grid gap-4 md:grid-cols-3">
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Province code</label>
                                            <input
                                                type="text"
                                                wire:model.defer="importForm.province_code"
                                                class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">City code</label>
                                            <input
                                                type="text"
                                                wire:model.defer="importForm.city_code"
                                                class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Business model</label>
                                            <input
                                                type="text"
                                                wire:model.defer="importForm.biz_model"
                                                class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                                            />
                                        </div>
                                    </div>
                                    <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-200">
                                        <input
                                            type="checkbox"
                                            wire:model.defer="importForm.remove_personal_benefit"
                                            class="rounded border-gray-300 text-primary-600 shadow-sm"
                                        />
                                        <span>Remove personal benefit</span>
                                    </label>
                                </div>
                            </x-filament::fieldset>
                        </div>
                    </x-filament::card>
                </div>

                <div class="space-y-4">
                    @if ($optionStockSummary->isNotEmpty())
                        <x-filament::fieldset label="Option stock summary">
                            <div class="flex flex-wrap gap-2">
                                @foreach ($optionStockSummary as $optionStock)
                                    <x-filament::badge :color="($optionStock['stock'] ?? 0) > 0 ? 'success' : 'gray'">
                                        {{ $optionStock['property'] ?? 'Option' }}: {{ $optionStock['value'] ?? '—' }} · Stock {{ $optionStock['stock'] ?? 0 }}
                                    </x-filament::badge>
                                @endforeach
                            </div>
                        </x-filament::fieldset>
                    @endif

                    <x-filament::fieldset label="Variants">
                        <div class="space-y-3 max-h-[44vh] overflow-y-auto pr-1">
                            @forelse ($previewVariants as $variant)
                                @php
                                    $skuId = (string) ($variant['sku_id'] ?? '');
                                    $checked = in_array($skuId, (array) ($this->importForm['enabled_variant_ids'] ?? []), true);
                                    $variantStock = is_numeric($variant['stock'] ?? null) ? (int) $variant['stock'] : null;
                                @endphp
                                <label class="flex items-start gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                                    <input
                                        type="checkbox"
                                        wire:model.defer="importForm.enabled_variant_ids"
                                        value="{{ $skuId }}"
                                        @disabled(! ($variant['is_valid'] ?? false))
                                        class="mt-1 rounded border-gray-300 text-primary-600 shadow-sm"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $variant['title'] ?? 'Variant' }}</div>
                                            @if ($variantStock !== null)
                                                <x-filament::badge :color="$variantStock > 0 ? 'success' : 'gray'">
                                                    {{ $variantStock > 0 ? 'In stock' : 'Out of stock' }}: {{ $variantStock }}
                                                </x-filament::badge>
                                            @endif
                                            @if (! ($variant['is_valid'] ?? false))
                                                <x-filament::badge color="danger">Invalid</x-filament::badge>
                                            @elseif ($checked)
                                                <x-filament::badge color="success">Enabled</x-filament::badge>
                                            @endif
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            SKU {{ $skuId !== '' ? $skuId : '—' }}
                                            • {{ $variant['currency'] ?? 'USD' }} {{ number_format((float) ($variant['offer_sale_price'] ?? 0), 2) }}
                                            @if (($variant['sku_price'] ?? null) !== null)
                                                • Compare at {{ $variant['currency'] ?? 'USD' }} {{ number_format((float) $variant['sku_price'], 2) }}
                                            @endif
                                            @if (($variant['pricing_preview']['selling_price'] ?? null) !== null)
                                                • Our price {{ $variant['currency'] ?? 'USD' }} {{ number_format((float) $variant['pricing_preview']['selling_price'], 2) }}
                                            @endif
                                            @if (($variant['pricing_preview']['weight_kg'] ?? null) !== null)
                                                • Weight {{ number_format((float) $variant['pricing_preview']['weight_kg'], 4) }} kg
                                            @endif
                                            • Stock {{ $variant['stock'] ?? '—' }}
                                        </div>
                                        @if (! empty($variant['properties']))
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($variant['properties'] as $propertyName => $propertyValue)
                                                    <x-filament::badge color="gray">{{ $propertyName }}: {{ $propertyValue }}</x-filament::badge>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </label>
                            @empty
                                <div class="rounded-xl border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-300">
                                    No variants returned by AliExpress.
                                </div>
                            @endforelse
                        </div>
                    </x-filament::fieldset>

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-filament::fieldset label="Store & logistics">
                            <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Store</span><span>{{ $previewStore['store_name'] ?? '—' }}</span></div>
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Country</span><span>{{ $previewStore['store_country_code'] ?? '—' }}</span></div>
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Delivery</span><span>{{ $previewLogistics['delivery_time'] ?? '—' }}</span></div>
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Ship to</span><span>{{ $previewRequest['ship_to_country'] ?? ($previewLogistics['ship_to_country'] ?? 'CN') }}</span></div>
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Target currency</span><span>{{ $previewRequest['target_currency'] ?? ($previewVariants->first()['currency'] ?? 'USD') }}</span></div>
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Target language</span><span>{{ $previewRequest['target_language'] ?? 'en_US' }}</span></div>
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Province code</span><span>{{ $previewRequest['province_code'] ?? '—' }}</span></div>
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">City code</span><span>{{ $previewRequest['city_code'] ?? '—' }}</span></div>
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Remove personal benefit</span><span>{{ ! empty($previewRequest['remove_personal_benefit']) ? 'Yes' : 'No' }}</span></div>
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Business model</span><span>{{ $previewRequest['biz_model'] ?? '—' }}</span></div>
                            </div>
                        </x-filament::fieldset>

                        <x-filament::fieldset label="Package">
                                <div class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Gross weight</span><span>{{ $previewPackage['gross_weight'] ?? '—' }} kg</span></div>
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Stored weight</span><span>{{ $previewPackage['weight_grams'] ?? '—' }} g</span></div>
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Length</span><span>{{ $previewPackage['length_mm'] ?? '—' }} mm</span></div>
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Width</span><span>{{ $previewPackage['width_mm'] ?? '—' }} mm</span></div>
                                <div class="flex items-center justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">Height</span><span>{{ $previewPackage['height_mm'] ?? '—' }} mm</span></div>
                            </div>
                        </x-filament::fieldset>
                    </div>

                    @if ($previewAttributes->isNotEmpty())
                        <x-filament::fieldset label="Attributes">
                            <div class="flex flex-wrap gap-2">
                                @foreach ($previewAttributes as $attribute)
                                    <x-filament::badge color="gray">{{ $attribute['name'] ?? 'Attribute' }}: {{ $attribute['value'] ?? '—' }}</x-filament::badge>
                                @endforeach
                            </div>
                        </x-filament::fieldset>
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <x-filament::button type="button" color="gray" x-on:click="$dispatch('close-modal', { id: {{ \Illuminate\Support\Js::from($importPreviewModalId) }} })">
                    Cancel
                </x-filament::button>
                <x-filament::button type="button" wire:click="confirmImportPreview">
                    Confirm Import
                </x-filament::button>
            </div>
        </div>
    </x-filament::modal>
</x-filament::page>
