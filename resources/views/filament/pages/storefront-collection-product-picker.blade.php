<x-filament-panels::page>
    <style>
        .scp-wrap { display: flex; flex-direction: column; gap: 16px; }
        .scp-help { font-size: 13px; color: #4b5563; }
        .scp-filters { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .scp-filters-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .scp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .scp-card { display: block; width: 100%; text-align: left; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; padding: 12px; }
        .scp-card.is-selected { border-color: rgba(34, 197, 94, 0.55); background: rgba(34, 197, 94, 0.12); }
        .scp-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .scp-name { font-weight: 600; font-size: 14px; color: #111827; line-height: 1.3; }
        .scp-meta { font-size: 12px; color: #6b7280; margin-top: 2px; }
        .scp-badge { display: inline-flex; align-items: center; border: 1px solid #e5e7eb; border-radius: 999px; padding: 2px 8px; font-size: 12px; color: #374151; background: #f9fafb; }
        .scp-img { width: 100%; border-radius: 10px; margin-top: 10px; aspect-ratio: 1 / 1; object-fit: cover; border: 1px solid #f3f4f6; background: #f9fafb; }
        .scp-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 10px; font-size: 12px; color: #374151; }
        .scp-row strong { font-weight: 600; }
        .scp-check { display: flex; align-items: center; gap: 8px; margin-top: 10px; font-size: 13px; color: #111827; }
        .scp-pagination { margin-top: 16px; }
        .scp-bottom { position: sticky; bottom: 0; z-index: 10; padding: 12px 0 0; background: linear-gradient(to top, rgba(255,255,255,0.95), rgba(255,255,255,0)); }
        .scp-bottom-inner { display: flex; align-items: center; justify-content: space-between; gap: 12px; border: 1px solid #e5e7eb; border-radius: 12px; background: rgba(255,255,255,0.92); padding: 10px 12px; backdrop-filter: blur(6px); }
        .scp-btn { border: 1px solid #0f172a; background: #0f172a; color: #fff; border-radius: 10px; padding: 10px 12px; font-weight: 600; font-size: 13px; }
        .scp-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        @media (max-width: 900px) {
            .scp-filters { grid-template-columns: 1fr; }
            .scp-filters-2 { grid-template-columns: 1fr; }
            .scp-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="scp-wrap">
        <div class="scp-help">
            Filter products, select cards, then click <strong>Attach selected</strong>.
        </div>

        <div class="scp-filters">
            <x-filament::input.wrapper>
                <x-filament::input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name or slug" />
            </x-filament::input.wrapper>

            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="categoryId">
                    <option value="">All categories</option>
                    @foreach ($this->categoryOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="isActive">
                    <option value="">Active (any)</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="isFeatured">
                    <option value="">Featured (any)</option>
                    <option value="1">Featured</option>
                    <option value="0">Not featured</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <div class="scp-filters-2">
            <x-filament::input.wrapper>
                <x-filament::input type="number" step="0.01" wire:model.live.debounce.300ms="minPrice" placeholder="Min price" />
            </x-filament::input.wrapper>
            <x-filament::input.wrapper>
                <x-filament::input type="number" step="0.01" wire:model.live.debounce.300ms="maxPrice" placeholder="Max price" />
            </x-filament::input.wrapper>
            <x-filament::input.wrapper>
                <x-filament::input type="number" step="0.1" wire:model.live.debounce.300ms="minRating" placeholder="Min rating" />
            </x-filament::input.wrapper>
            <x-filament::input.wrapper>
                <x-filament::input type="number" step="0.1" wire:model.live.debounce.300ms="minMargin" placeholder="Min margin %" />
            </x-filament::input.wrapper>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="perPage">
                    <option value="24">24 / page</option>
                    <option value="48">48 / page</option>
                    <option value="96">96 / page</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <div>
            <div class="scp-grid">
                @foreach ($this->products as $product)
                    @php
                        $thumb = $this->productThumbUrl($product) ?: 'https://via.placeholder.com/300x300?text=No+Image';
                        $selected = in_array((int) $product->id, $this->selectedProductIds, true);
                        $currency = $product->currency ?: 'USD';
                    @endphp

                    <button type="button" wire:click="toggleSelected({{ (int) $product->id }})" class="scp-card {{ $selected ? 'is-selected' : '' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="scp-name">
                                        {{ $product->name }}
                                    </div>
                                    <div class="scp-meta">
                                        {{ $product->category?->name ?? '—' }}
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    <span class="scp-badge">#{{ $product->id }}</span>
                                </div>
                            </div>

                            <div class="mt-3">
                                <img
                                    src="{{ $thumb }}"
                                    alt="{{ $product->name }}"
                                    class="scp-img"
                                    loading="lazy"
                                    referrerpolicy="no-referrer"
                                    crossorigin="anonymous"
                                    onerror="this.onerror=null;this.src='https://via.placeholder.com/300x300?text=No+Image';"
                                />
                            </div>

                            <div class="scp-row">
                                <span><strong>{{ number_format((float) ($product->selling_price ?? 0), 2) }}</strong> {{ $currency }}</span>
                                <span>
                                    {{ number_format((float) ($product->selling_price ?? 0), 2) }} {{ $currency }}
                                </span>
                                <span>
                                    ⭐ {{ number_format((float) ($product->reviews_avg_rating ?? 0), 2) }}
                                </span>
                            </div>

                            <div class="scp-row">
                                <span>
                                    Margin: {{ number_format((float) optional($product->latestMarginLog)->new_margin_percent, 2) }}%
                                </span>
                                <span>
                                    @if ($product->is_active)
                                        <span class="scp-badge">Active</span>
                                    @else
                                        <span class="scp-badge">Inactive</span>
                                    @endif
                                </span>
                            </div>

                            <div class="scp-check">
                                <input type="checkbox" @checked($selected) class="mr-2" />
                                <span>Selected</span>
                            </div>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="scp-pagination">
            {{ $this->products->links() }}
        </div>

        <div class="scp-bottom">
            <div class="scp-bottom-inner">
                <div class="scp-help">
                    Selected: <strong>{{ count($this->selectedProductIds) }}</strong>
                </div>
                <button
                    type="button"
                    class="scp-btn"
                    @disabled(count($this->selectedProductIds) === 0)
                    wire:click="mountAction('attachSelected')"
                >
                    Attach selected
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
