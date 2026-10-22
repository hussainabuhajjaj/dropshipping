<x-filament::section>
    <x-slot name="heading">
        WooCommerce Sync Overview
    </x-slot>

    <div class="grid grid-cols-2 gap-4 mb-4">
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
            <dt class="text-sm font-medium text-gray-500">Products Synced</dt>
            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $products_synced }}</dd>
        </div>
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
            <dt class="text-sm font-medium text-gray-500">Products Failed</dt>
            <dd class="mt-1 text-2xl font-semibold text-danger-600">{{ $products_failed }}</dd>
        </div>
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
            <dt class="text-sm font-medium text-gray-500">Orders Synced</dt>
            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $orders_synced }}</dd>
        </div>
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
            <dt class="text-sm font-medium text-gray-500">Orders Failed</dt>
            <dd class="mt-1 text-2xl font-semibold text-danger-600">{{ $orders_failed }}</dd>
        </div>
    </div>

    @if($recent_syncs->isNotEmpty())
        <h4 class="text-sm font-medium mb-2">Recent Sync Activity</h4>
        <div class="space-y-1">
            @foreach($recent_syncs as $log)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400 capitalize">{{ $log->entity_type }} #{{ $log->entity_id }}</span>
                    <span class="px-2 py-0.5 rounded text-xs font-medium
                        @if($log->status === 'success') bg-success-100 text-success-700
                        @elseif($log->status === 'failed') bg-danger-100 text-danger-700
                        @else bg-gray-100 text-gray-600 @endif
                    ">{{ $log->status }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
