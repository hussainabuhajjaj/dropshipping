@php
    use Illuminate\Support\Str;

    $json = fn ($value) => $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;
@endphp

<x-filament-panels::page>
            <div class="mb-4">
                <x-filament::badge color="success">
                    Imported Products: <span wire:poll.2s>{{ $importedCount }}</span>
                </x-filament::badge>
            </div>
        <div class="mb-4">
            <x-filament::badge color="info">
                Sync Status: {{ $syncStatusLabel ?? 'Idle' }}
            </x-filament::badge>
        </div>
    <div class="space-y-6">
        <x-filament::section
            heading="CJ My Products"
            description="Products already added to your CJ account."
            icon="heroicon-o-rectangle-stack"
        >
            <div class="flex flex-wrap gap-2">
                <x-filament::badge color="gray">Total: {{ number_format($total ?? 0) }}</x-filament::badge>
                <x-filament::badge color="gray">Page {{ $pageNum ?? 1 }} of {{ max($totalPages ?? 1, 1) }}</x-filament::badge>
                <x-filament::badge color="gray">Loaded: {{ number_format(count($products['content'] ?? [])) }}</x-filament::badge>
                @if ($lastSyncAt)
                    <x-filament::badge color="success">Synced at {{ $lastSyncAt }}</x-filament::badge>
                @endif
            </div>
            @if ($lastSyncSummary)
                <p class="mt-2 text-xs text-slate-500">{{ Str::limit($lastSyncSummary, 180, '...') }}</p>
            @endif
        </x-filament::section>

        <x-filament::section
            heading="My product list"
            description="Quick view with pricing and CJ listing metrics."
            icon="heroicon-o-archive-box"
        >
            {{ $this->table }}
        </x-filament::section>

        @if (auth()->user()?->role === 'admin' && $lastApiErrorMessage)
            <x-filament::section
                heading="Last CJ API Error"
                description="Visible to admins only. Use this to help debug CJ API responses."
                icon="heroicon-o-exclamation-triangle"
            >
                <x-filament::fieldset label="Error">
                    <p class="text-sm text-red-700 font-medium mb-2">{{ $lastApiErrorMessage }}@if($lastApiErrorStatus) (status: {{ $lastApiErrorStatus }})@endif @if($lastApiErrorCode) (code: {{ $lastApiErrorCode }})@endif</p>
                    @if($lastApiErrorHint)
                        <p class="text-sm text-slate-700 mb-2"><strong>Hint:</strong> {{ $lastApiErrorHint }}</p>
                    @endif
                    <pre class="rounded-lg border bg-slate-50 p-3 text-xs text-slate-700 overflow-auto dark:bg-slate-900 dark:border-slate-800 dark:text-slate-200">@if(is_string($lastApiErrorBody)){{ $lastApiErrorBody }}@else{{ $json($lastApiErrorBody) }}@endif</pre>
                </x-filament::fieldset>
            </x-filament::section>
        @endif

        <x-filament::section
            heading="Raw CJ payload"
            description="Inspect the response to debug mapping."
            icon="heroicon-o-code-bracket-square"
        >
            <x-filament::fieldset label="Response">
                <pre class="rounded-lg border bg-slate-50 p-3 text-xs text-slate-700 overflow-auto dark:bg-slate-900 dark:border-slate-800 dark:text-slate-200">{{ $json($products ?? []) }}</pre>
            </x-filament::fieldset>
        </x-filament::section>
    </div>
</x-filament-panels::page>
