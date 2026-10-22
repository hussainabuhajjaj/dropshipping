<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Save settings
        </x-filament::button>
    </form>

    @php($preview = $this->getPreview())
    @php($slide = $preview['slide'])

    <x-filament::section>
        <x-slot name="heading">Live preview</x-slot>
        <x-slot name="description">What visitors see first on the homepage for the selected locale.</x-slot>

        <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
            @if(! empty($slide['image']))
                <img src="{{ $slide['image'] }}" alt="" class="h-72 w-full object-cover" />
            @else
                <div class="flex h-72 w-full items-center justify-center bg-gradient-to-br from-slate-900 to-slate-700 text-sm text-slate-300">
                    No image
                </div>
            @endif

            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-transparent"></div>

            <div class="absolute inset-x-0 bottom-0 p-5 text-white">
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::badge :color="$this->sourceColor($preview['source'])">
                        {{ $preview['source_label'] }}
                    </x-filament::badge>
                    @if(! empty($slide['badge']))
                        <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">
                            {{ $slide['badge'] }}
                        </span>
                    @endif
                </div>
                <h2 class="mt-2 max-w-2xl text-2xl font-black leading-tight">{{ $slide['title'] ?: '—' }}</h2>
                @if(! empty($slide['subtitle']))
                    <p class="mt-1 max-w-2xl text-sm text-white/80">{{ $slide['subtitle'] }}</p>
                @endif
                <div class="mt-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold uppercase tracking-wide">
                    {{ $slide['cta_label'] ?: 'Shop now' }}
                    <span class="text-white/60">{{ $slide['cta_url'] ?: '/products' }}</span>
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-2 text-xs text-gray-600 dark:text-gray-300 sm:grid-cols-3">
            <div>Active campaigns: <strong>{{ $preview['counts']['campaigns'] }}</strong></div>
            <div>Hero slides: <strong>{{ $preview['counts']['slides'] }}</strong></div>
            <div>Hero banner: <strong>{{ $preview['counts']['base_banner'] ? 'Yes' : 'No' }}</strong></div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Priority chain</x-slot>
        <x-slot name="description">The first available source wins. The preview badge above shows the current winner.</x-slot>

        <div class="space-y-2">
            @foreach($this->getPriorityChain() as $row)
                <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 px-4 py-3 dark:border-white/10">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold">{{ $row['label'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['detail'] }}</div>
                    </div>
                    <x-filament::badge :color="$row['active'] ? 'success' : 'gray'">
                        {{ $row['status'] }}
                    </x-filament::badge>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
