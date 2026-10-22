<div class="space-y-3 text-sm text-gray-700 dark:text-gray-200">
    <div class="space-y-1">
        <p class="text-xs text-gray-500 dark:text-gray-400">Title</p>
        <p class="font-medium">{{ $record->data['title'] ?? 'Notification' }}</p>
    </div>

    @if (! empty($record->data['body']))
        <div class="space-y-1">
            <p class="text-xs text-gray-500 dark:text-gray-400">Message</p>
            <p>{{ $record->data['body'] }}</p>
        </div>
    @endif

    @if (! empty($record->data['action_url']))
        <div class="space-y-1">
            <p class="text-xs text-gray-500 dark:text-gray-400">Action</p>
            <a class="text-primary-600 underline" href="{{ $record->data['action_url'] }}" target="_blank" rel="noopener">
                {{ $record->data['action_label'] ?? $record->data['action_url'] }}
            </a>
        </div>
    @endif

    <div class="text-xs text-gray-500 dark:text-gray-400">
        Sent {{ $record->created_at?->toDayDateTimeString() ?? 'unknown' }}
    </div>
</div>
