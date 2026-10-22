{{-- CJ Catalog Custom Progress Indicators --}}
{{-- Design 3: Minimalist Card-Based Progress --}}
@if ($activeImportTrackingKey)
    <div wire:poll.{{ $this->getImportPollIntervalSeconds() }}s="refreshQueueImportStatus" 
         class="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-800">
        
        <!-- Header -->
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="h-10 w-10 rounded-full bg-blue-100 p-2 dark:bg-blue-900">
                        <svg class="h-6 w-6 animate-spin text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    @if ($importFailed > 0)
                        <div class="absolute -top-1 -right-1 h-3 w-3 rounded-full bg-red-500 border-2 border-white dark:border-gray-800"></div>
                    @endif
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Import Progress</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', ucfirst($importStatusLabel)) }}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ min(100, max(0, $importPercent)) }}%</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Complete</p>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="mb-6">
            <div class="relative h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-blue-600 transition-all duration-500 ease-out"
                     style="width: {{ min(100, max(0, $importPercent)) }}%">
                    <!-- Animated stripes -->
                    <div class="h-full w-full bg-gradient-to-r from-transparent via-white/20 to-transparent animate-pulse"></div>
                </div>
            </div>
            <div class="mt-2 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>0</span>
                <span>{{ number_format($importTotal) }} items</span>
                <span>100%</span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-3 gap-3">
            <!-- Total Card -->
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-700">
                <div class="flex items-center justify-center gap-2">
                    <div class="h-8 w-8 rounded-full bg-blue-100 p-1.5 dark:bg-blue-900">
                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($importTotal) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Total</p>
                    </div>
                </div>
            </div>

            <!-- Completed Card -->
            <div class="rounded-lg border border-gray-200 bg-green-50 p-3 dark:border-green-600 dark:bg-green-900/20">
                <div class="flex items-center justify-center gap-2">
                    <div class="h-8 w-8 rounded-full bg-green-100 p-1.5 dark:bg-green-900">
                        <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-green-900 dark:text-green-100">{{ number_format($importProcessed) }}</p>
                        <p class="text-xs text-green-600 dark:text-green-400">Done</p>
                    </div>
                </div>
            </div>

            <!-- Failed Card -->
            <div class="rounded-lg border {{ $importFailed > 0 ? 'border-red-200 bg-red-50 dark:border-red-600 dark:bg-red-900/20' : 'border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-700' }} p-3">
                <div class="flex items-center justify-center gap-2">
                    <div class="h-8 w-8 rounded-full {{ $importFailed > 0 ? 'bg-red-100 dark:bg-red-900' : 'bg-gray-100 dark:bg-gray-600' }} p-1.5">
                        <svg class="h-5 w-5 {{ $importFailed > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-lg font-bold {{ $importFailed > 0 ? 'text-red-900 dark:text-red-100' : 'text-gray-900 dark:text-white' }}">{{ number_format($importFailed) }}</p>
                        <p class="text-xs {{ $importFailed > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $importFailed > 0 ? 'Failed' : 'Issues' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Time Estimate -->
        <div class="mt-4 rounded-lg bg-blue-50 p-3 dark:bg-blue-900/20">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm font-medium text-blue-900 dark:text-blue-100">Time Remaining</span>
                </div>
                @if ($importProcessed > 0)
                    <?php 
                    $remaining = $importTotal - $importProcessed;
                    $rate = $importProcessed / max(1, microtime(true) - ($importStartedAt ? strtotime($importStartedAt) : microtime(true)));
                    $eta = $remaining / max(0.1, $rate);
                    ?>
                    <span class="text-sm text-blue-700 dark:text-blue-300">
                        {{ floor($eta / 60) }}m {{ floor($eta % 60) }}s
                    </span>
                @else
                    <span class="text-sm text-blue-700 dark:text-blue-300">Calculating...</span>
                @endif
            </div>
        </div>

        <!-- Action Buttons -->
        @if ($importFailed > 0)
            <div class="mt-4 flex gap-3">
                <button wire:click="retryFailedQueuedImports" 
                        wire:loading.attr="disabled"
                        class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50">
                    <span wire:loading.remove>Retry Failed Items</span>
                    <span wire:loading>Retrying...</span>
                </button>
                <button wire:click="cancelImport" 
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                    Cancel
                </button>
            </div>
        @endif
    </div>
@endif
