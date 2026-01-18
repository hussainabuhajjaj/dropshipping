@php
    $previewHtml = $preview_html ?? '';
@endphp

<div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="text-xs uppercase tracking-wide text-gray-400">Email preview (full template)</div>
    <div class="mt-3 overflow-auto rounded-md border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950" style="max-height:520px;">
        {!! $previewHtml !!}
    </div>
</div>
