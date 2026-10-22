{{-- CJ Catalog Custom Progress Indicators --}}
{{-- Design 4: Modern Animated Pulse Design --}}
@if ($activeImportTrackingKey)
    <div wire:poll.{{ $this->getImportPollIntervalSeconds() }}s="refreshQueueImportStatus" 
         class="relative overflow-hidden rounded-2xl border-0 bg-gradient-to-br from-violet-600 via-purple-600 to-indigo-600 p-6 shadow-2xl">
        
        <!-- Animated Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent animate-pulse"></div>
            <svg class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>
        </div>

        <!-- Content -->
        <div class="relative z-10">
            <!-- Header -->
            <div class="mb-6 text-center">
                <div class="mx-auto mb-4 h-16 w-16 rounded-full bg-white/20 p-3 backdrop-blur-sm">
                    <div class="relative h-full w-full">
                        <svg class="h-full w-full animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <!-- Center percentage -->
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-lg font-bold text-white">{{ min(100, max(0, $importPercent)) }}%</span>
                        </div>
                    </div>
                </div>
                <h3 class="text-xl font-bold text-white">CJ Import in Progress</h3>
                <p class="text-purple-100">{{ str_replace('_', ' ', ucfirst($importStatusLabel)) }}</p>
            </div>

            <!-- Circular Progress Ring -->
            <div class="mb-6 flex justify-center">
                <div class="relative h-32 w-32">
                    <!-- Background ring -->
                    <svg class="h-32 w-32 transform -rotate-90">
                        <circle cx="64" cy="64" r="56" stroke="rgba(255,255,255,0.2)" stroke-width="12" fill="none"></circle>
                        <!-- Progress ring -->
                        <circle cx="64" cy="64" r="56" stroke="url(#gradient)" stroke-width="12" fill="none"
                                stroke-dasharray="{{ 351.86 * (min(100, max(0, $importPercent)) / 100) }} 351.86"
                                stroke-linecap="round"
                                class="transition-all duration-700 ease-out">
                            <animate attributeName="stroke-dasharray" 
                                     from="0 351.86" 
                                     to="{{ 351.86 * (min(100, max(0, $importPercent)) / 100) }} 351.86" 
                                     dur="0.7s" 
                                     fill="freeze"/>
                        </circle>
                    </svg>
                    <!-- Gradient definition -->
                    <svg class="absolute h-0 w-0">
                        <defs>
                            <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#60A5FA" />
                                <stop offset="50%" stop-color="#A78BFA" />
                                <stop offset="100%" stop-color="#F472B6" />
                            </linearGradient>
                        </defs>
                    </svg>
                    <!-- Center content -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <p class="text-3xl font-bold text-white">{{ min(100, max(0, $importPercent)) }}%</p>
                        <p class="text-xs text-purple-100">Complete</p>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-3 gap-4">
                <div class="rounded-lg bg-white/10 p-4 text-center backdrop-blur-sm">
                    <div class="mb-2 flex justify-center">
                        <div class="h-8 w-8 rounded-full bg-blue-500 p-2">
                            <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-white">{{ number_format($importTotal) }}</p>
                    <p class="text-xs text-purple-100">Total Items</p>
                </div>

                <div class="rounded-lg bg-white/10 p-4 text-center backdrop-blur-sm">
                    <div class="mb-2 flex justify-center">
                        <div class="h-8 w-8 rounded-full bg-green-500 p-2">
                            <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-white">{{ number_format($importProcessed) }}</p>
                    <p class="text-xs text-green-100">Completed</p>
                </div>

                <div class="rounded-lg {{ $importFailed > 0 ? 'bg-red-500/20' : 'bg-white/10' }} p-4 text-center backdrop-blur-sm">
                    <div class="mb-2 flex justify-center">
                        <div class="h-8 w-8 rounded-full {{ $importFailed > 0 ? 'bg-red-500' : 'bg-gray-500' }} p-2">
                            <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-white">{{ number_format($importFailed) }}</p>
                    <p class="text-xs {{ $importFailed > 0 ? 'text-red-100' : 'text-purple-100' }}">{{ $importFailed > 0 ? 'Failed' : 'Issues' }}</p>
                </div>
            </div>

            <!-- Progress Messages -->
            <div class="mt-4 rounded-lg bg-white/10 p-3 backdrop-blur-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full bg-green-400 animate-pulse"></div>
                        <span class="text-sm text-white">
                            @if ($importProcessed >= $importTotal)
                                Import completed successfully!
                            @elseif ($importFailed > 0)
                                Processing with {{ $importFailed }} issues
                            @else
                                Processing smoothly...
                            @endif
                        </span>
                    </div>
                    @if ($importProcessed > 0 && $importProcessed < $importTotal)
                        <?php 
                        $remaining = $importTotal - $importProcessed;
                        $rate = $importProcessed / max(1, microtime(true) - ($importStartedAt ? strtotime($importStartedAt) : microtime(true)));
                        $eta = $remaining / max(0.1, $rate);
                        ?>
                        <span class="text-xs text-purple-100">
                            {{ floor($eta / 60) }}m {{ floor($eta % 60) }}s remaining
                        </span>
                    @endif
                </div>
            </div>

            <!-- Action Buttons -->
            @if ($importFailed > 0)
                <div class="mt-4 flex gap-3">
                    <button wire:click="retryFailedQueuedImports" 
                            wire:loading.attr="disabled"
                            class="flex-1 rounded-lg bg-white/20 px-4 py-3 text-sm font-medium text-white backdrop-blur-sm transition-all hover:bg-white/30 disabled:opacity-50">
                        <span wire:loading.remove>
                            <svg class="mr-2 inline h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Retry Failed Items
                        </span>
                        <span wire:loading>Retrying...</span>
                    </button>
                    <button wire:click="cancelImport" 
                            class="rounded-lg bg-red-500/20 px-4 py-3 text-sm font-medium text-white backdrop-blur-sm transition-all hover:bg-red-500/30">
                        Cancel
                    </button>
                </div>
            @endif
        </div>
    </div>
@endif
