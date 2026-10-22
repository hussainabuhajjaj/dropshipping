<div class="space-y-3 text-sm text-gray-700 dark:text-gray-200">
    <div class="text-xs text-gray-500 dark:text-gray-400">
        {{ $record->created_at?->toDayDateTimeString() ?? 'Unknown time' }}
    </div>

    <div class="grid gap-2 text-xs text-gray-500 dark:text-gray-400">
        <div>Total rows: {{ $record->total_rows }}</div>
        <div>Created: {{ $record->created_count }}</div>
        <div>Updated: {{ $record->updated_count }}</div>
        <div>Skipped: {{ $record->skipped_count }}</div>
    </div>

    @if (! empty($record->summary))
        <x-filament::fieldset label="Summary">
            <pre class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 overflow-auto dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ json_encode($record->summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </x-filament::fieldset>
    @endif
</div>
