@php
    /** @var array<int, array{id:int|string, url:string, position?:int|null}> $images */
    $images = is_array($images ?? null) ? $images : [];
    $allIds = collect($images)
        ->map(fn ($img) => (string) ($img['id'] ?? ''))
        ->filter()
        ->values()
        ->all();
@endphp

<div
    x-data="{
        state: @entangle($getStatePath()).live,
        allIds: @js($allIds),
        selectAll() { this.state = [...this.allIds] },
        clearAll() { this.state = [] },
    }"
    class="space-y-3"
>
    <div class="flex flex-wrap items-center gap-2">
        <x-filament::button type="button" size="sm" color="gray" x-on:click="selectAll()">
            Select all
        </x-filament::button>
        <x-filament::button type="button" size="sm" color="gray" x-on:click="clearAll()">
            Clear
        </x-filament::button>
        <x-filament::badge color="primary" x-text="`${(state || []).length} selected`"></x-filament::badge>
    </div>

    @if ($images === [])
        <div class="text-sm text-gray-500 dark:text-gray-400">
            No images found.
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($images as $img)
                @php
                    $id = (string) ($img['id'] ?? '');
                    $url = (string) ($img['url'] ?? '');
                    $pos = $img['position'] ?? null;
                @endphp

                <label class="flex gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 cursor-pointer">
                    <input
                        type="checkbox"
                        class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        value="{{ $id }}"
                        x-model="state"
                    />
                    <div class="flex-1 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                #{{ $pos ?? '—' }} · ID {{ $id }}
                            </div>
                            <a
                                href="{{ $url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-xs text-primary-600 hover:underline"
                            >
                                Open
                            </a>
                        </div>
                        <div class="aspect-[4/3] overflow-hidden rounded-lg bg-gray-50 dark:bg-gray-900/60">
                            <img
                                src="{{ $url }}"
                                alt="Product image"
                                class="h-full w-full object-contain"
                                loading="lazy"
                                draggable="false"
                                onerror="this.style.display='none'"
                            />
                        </div>
                    </div>
                </label>
            @endforeach
        </div>
    @endif
</div>

