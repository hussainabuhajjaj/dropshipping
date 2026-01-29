<div class="space-y-4">
    <div>
        <div class="text-lg font-semibold">
            {{ $record['title'] ?? 'AliExpress Product' }}
        </div>
        <div class="text-sm text-gray-500">
            Variants: {{ $record['variants_count'] ?? 0 }}
        </div>
    </div>

    @if(!empty($record['images']))
        <div class="flex gap-3 overflow-x-auto">
            @foreach($record['images'] as $image)
                <img src="{{ $image }}" alt="" class="h-28 w-28 rounded object-cover" />
            @endforeach
        </div>
    @endif

    <div class="grid gap-3 md:grid-cols-2">
        <div class="rounded border p-3">
            <div class="text-xs text-gray-500">Price Range</div>
            <div class="text-base font-semibold">
                @if($record['minPrice'] !== null && $record['maxPrice'] !== null)
                    {{ $record['currency'] ?? 'USD' }} {{ number_format($record['minPrice'], 2) }}
                    @if($record['maxPrice'] !== $record['minPrice'])
                        - {{ $record['currency'] ?? 'USD' }} {{ number_format($record['maxPrice'], 2) }}
                    @endif
                @else
                    —
                @endif
            </div>
        </div>

        <div class="rounded border p-3">
            <div class="text-xs text-gray-500">Stock</div>
            <div class="text-base font-semibold">
                {{ $record['stock'] ?? 0 }}
            </div>
        </div>

        <div class="rounded border p-3">
            <div class="text-xs text-gray-500">Delivery</div>
            <div class="text-base font-semibold">
                @if(!empty($record['delivery_time']))
                    {{ $record['delivery_time'] }} days
                @else
                    —
                @endif
            </div>
            <div class="text-xs text-gray-500">
                Ship to: {{ $record['ship_to_country'] ?? '—' }}
            </div>
        </div>

        <div class="rounded border p-3">
            <div class="text-xs text-gray-500">Store</div>
            <div class="text-base font-semibold">
                {{ $record['store']['name'] ?? '—' }}
            </div>
            <div class="text-xs text-gray-500">
                Country: {{ $record['store']['country'] ?? '—' }}
            </div>
        </div>
    </div>

    <div class="rounded border p-3">
        <div class="text-xs text-gray-500">Store ratings</div>
        <div class="text-sm">
            Shipping speed: {{ $record['store']['shipping_speed'] ?? '—' }}
        </div>
        <div class="text-sm">
            Communication: {{ $record['store']['communication'] ?? '—' }}
        </div>
        <div class="text-sm">
            As described: {{ $record['store']['as_described'] ?? '—' }}
        </div>
    </div>
</div>
