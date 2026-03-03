{{-- CJ Catalog Custom Progress Indicators --}}
{{-- Design 1: Modern Circular Progress --}}
@if ($activeImportTrackingKey)
    <div wire:poll.{{ $this->getImportPollIntervalSeconds() }}s="refreshQueueImportStatus" 
         class="relative overflow-hidden rounded-2xl border border-gray-200 bg-gradient-to-br from-indigo-50 via-white to-purple-50 p-6 shadow-xl dark:border-gray-700 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
        
        <!-- Circular Progress Container -->
        <div class="flex items-center justify-between">
            <!-- Left Side - Circular Progress -->
            <div class="relative">
                <!-- SVG Circular Progress -->
                <div class="relative h-24 w-24">
                    <svg class="h-24 w-24 transform -rotate-90">
                        <!-- Background Circle -->
                        <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="none" 
                                class="text-gray-200 dark:text-gray-700"></circle>
                        <!-- Progress Circle -->
                        <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="none"
                                stroke-dasharray="{{ 251.2 * (min(100, max(0, $importPercent)) / 100) }} 251.2"
                                class="transition-all duration-500 ease-out {{ $importFailed > 0 ? 'text-amber-500' : 'text-emerald-500' }}">
                            <animate attributeName="stroke-dasharray" 
                                     from="0 251.2" 
                                     to="{{ 251.2 * (min(100, max(0, $importPercent)) / 100) }} 251.2" 
                                     dur="0.5s" 
                                     fill="freeze"/>
                        </circle>
                    </svg>
                    <!-- Center Content -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ min(100, max(0, $importPercent)) }}%</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', ucfirst($importStatusLabel)) }}</span>
                    </div>
                </div>
            </div>

            <!-- Right Side - Stats & Info -->
            <div class="flex-1 pl-6">
                <!-- Status Badge -->
                <div class="mb-3 flex items-center gap-2">
                    <div class="flex h-2 w-2 items-center justify-center rounded-full {{ $importFailed > 0 ? 'bg-amber-500' : 'bg-emerald-500' }}">
                        <div class="h-2 w-2 animate-ping rounded-full {{ $importFailed > 0 ? 'bg-amber-400' : 'bg-emerald-400' }}"></div>
                    </div>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $importFailed > 0 ? 'Processing with Issues' : 'Processing Smoothly' }}
                    </span>
                </div>

                <!-- Progress Stats Grid -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center">
                        <p class="text-xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($importTotal) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Total Items</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($importProcessed) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Completed</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold {{ $importFailed > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' }}">{{ number_format($importFailed) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $importFailed > 0 ? 'Failed' : 'Issues' }}</p>
                    </div>
                </div>

                <!-- Time Estimate -->
                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    @if ($importProcessed > 0)
                        <?php 
                        $remaining = $importTotal - $importProcessed;
                        $rate = $importProcessed / max(1, microtime(true) - ($importStartedAt ? strtotime($importStartedAt) : microtime(true)));
                        $eta = $remaining / max(0.1, $rate);
                        ?>
                        <span>Estimated remaining: {{ floor($eta / 60) }}m {{ floor($eta % 60) }}s</span>
                    @else
                        <span>Initializing import...</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Action Button -->
        @if ($importFailed > 0)
            <div class="mt-4 flex gap-2">
                <button wire:click="retryFailedQueuedImports" 
                        wire:loading.attr="disabled"
                        class="flex-1 rounded-lg bg-amber-500 px-3 py-2 text-xs font-medium text-white transition-colors hover:bg-amber-600 disabled:opacity-50">
                    <span wire:loading.remove>Retry Failed Items</span>
                    <span wire:loading>Retrying...</span>
                </button>
                <button wire:click="cancelImport" 
                        class="rounded-lg bg-gray-500 px-3 py-2 text-xs font-medium text-white transition-colors hover:bg-gray-600">
                    Cancel
                </button>
            </div>
        @endif
    </div>
@endif
