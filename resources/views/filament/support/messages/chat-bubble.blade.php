@php
    $record = $getRecord();
    $senderType = (string) ($record->sender_type ?? 'system');
    $isInternal = (bool) ($record->is_internal_note ?? false);
    $isOutbound = in_array($senderType, ['agent', 'ai'], true) || $isInternal;
    $attachmentUrl = (string) (data_get($record, 'metadata.attachment_url') ?? '');
    $attachmentName = (string) (data_get($record, 'metadata.attachment_name') ?? '');
    $attachmentType = (string) (data_get($record, 'metadata.attachment_type') ?? '');
    $attachmentMime = (string) (data_get($record, 'metadata.attachment_mime') ?? '');
    $isImageAttachment = $attachmentUrl !== '' && ($attachmentType === 'image' || str_starts_with($attachmentMime, 'image/'));
    $body = trim((string) ($record->body ?? ''));
    $senderLabel = match ($senderType) {
        'customer' => 'Customer',
        'agent' => (string) ($record->senderUser?->name ?: 'Agent'),
        'ai' => 'AI Assistant',
        'system' => 'System',
        default => ucfirst($senderType),
    };
    $timestamp = $record->created_at?->timezone(config('app.timezone'))->format('M j, Y · H:i');
    $bubbleClasses = match (true) {
        $isInternal => 'bg-warning-50 text-warning-900 ring-1 ring-warning-200 dark:bg-warning-900/20 dark:text-warning-100 dark:ring-warning-700/40',
        $senderType === 'customer' => 'bg-gray-100 text-gray-900 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-700',
        $senderType === 'agent' => 'bg-primary-600 text-white ring-1 ring-primary-500/40',
        $senderType === 'ai' => 'bg-info-600 text-white ring-1 ring-info-500/40',
        default => 'bg-gray-200 text-gray-900 ring-1 ring-gray-300 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-700',
    };
@endphp

<div class="py-1.5">
    <div class="flex {{ $isOutbound ? 'justify-end' : 'justify-start' }}">
        <div class="w-full max-w-3xl {{ $isOutbound ? 'ml-10' : 'mr-10' }}">
            <div class="mb-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 {{ $isOutbound ? 'justify-end' : 'justify-start' }}">
                <span class="font-medium">{{ $senderLabel }}</span>
                @if ($isInternal)
                    <span class="rounded-full bg-warning-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-warning-700 dark:bg-warning-900/40 dark:text-warning-200">
                        Internal
                    </span>
                @endif
                @if ($timestamp)
                    <span>{{ $timestamp }}</span>
                @endif
            </div>

            <div class="rounded-2xl px-4 py-3 text-sm leading-6 shadow-sm {{ $bubbleClasses }}">
                @if ($body !== '')
                    <p class="whitespace-pre-line break-words">{{ $body }}</p>
                @endif

                @if ($attachmentUrl !== '')
                    <div class="{{ $body !== '' ? 'mt-3' : '' }}">
                        @if ($isImageAttachment)
                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer" class="block">
                                <img src="{{ $attachmentUrl }}" alt="{{ $attachmentName !== '' ? $attachmentName : 'Attachment' }}" class="h-40 w-auto max-w-full rounded-xl border border-black/10 object-cover shadow-sm" loading="lazy" />
                            </a>
                        @endif

                        <a
                            href="{{ $attachmentUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-2 inline-flex items-center gap-2 rounded-lg bg-white/90 px-3 py-1.5 text-xs font-semibold text-gray-700 ring-1 ring-gray-300 transition hover:bg-white dark:bg-gray-900/80 dark:text-gray-100 dark:ring-gray-600"
                        >
                            <x-filament::icon icon="heroicon-o-paperclip" class="h-4 w-4" />
                            <span>{{ $attachmentName !== '' ? $attachmentName : 'Open attachment' }}</span>
                        </a>
                    </div>
                @endif
            </div>

            @if (! $isInternal && $isOutbound)
                <div class="mt-1 text-right text-[11px] text-gray-500 dark:text-gray-400">
                    {{ $record->read_at ? 'Seen by customer' : 'Delivered' }}
                </div>
            @endif
        </div>
    </div>
</div>
