<div class="space-y-3 text-sm text-gray-700 dark:text-gray-200">
    <div class="text-xs text-gray-500 dark:text-gray-400">
        {{ $record->created_at?->toDayDateTimeString() ?? 'Unknown time' }}
    </div>

    @foreach ($record->changes as $key => $change)
        <div class="space-y-1 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">{{ $key }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Previous</p>
            <p class="font-mono text-xs">{{ $change['old'] ?? '' }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">New</p>
            <p class="font-mono text-xs">{{ $change['new'] ?? '' }}</p>
        </div>
    @endforeach
</div>
