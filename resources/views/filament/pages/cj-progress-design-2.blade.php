{{-- CJ Catalog Custom Progress Indicators --}}
{{-- Design 2: Sleek Multi-Stage Progress --}}
@if ($activeImportTrackingKey)
    <div wire:poll.{{ $this->getImportPollIntervalSeconds() }}s="refreshQueueImportStatus" 
         class="relative overflow-hidden rounded-xl border-0 bg-gradient-to-r from-slate-900 via-purple-900 to-slate-900 p-6 shadow-2xl">
        
        <!-- Header with Title -->
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-600">
                        <svg class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <div class="absolute -bottom-1 -right-1 h-3 w-3 rounded-full bg-green-500 border-2 border-slate-900"></div>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">CJ Import in Progress</h3>
                    <p class="text-sm text-purple-200">{{ str_replace('_', ' ', ucfirst($importStatusLabel)) }}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-white">{{ min(100, max(0, $importPercent)) }}%</p>
                <p class="text-xs text-purple-200">Complete</p>
            </div>
        </div>

        <!-- Multi-Stage Progress Bar -->
        <div class="mb-6 space-y-3">
            <!-- Main Progress -->
            <div class="relative">
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-700">
                    <div class="h-full rounded-full bg-gradient-to-r from-purple-500 via-pink-500 to-purple-500 transition-all duration-700 ease-out"
                         style="width: {{ min(100, max(0, $importPercent)) }}%">
                        <!-- Animated shimmer -->
                        <div class="h-full w-full bg-gradient-to-r from-transparent via-white/20 to-transparent animate-pulse"></div>
                    </div>
                </div>
                <!-- Progress markers -->
                <div class="mt-2 flex justify-between text-xs text-purple-200">
                    <span>0%</span>
                    <span>25%</span>
                    <span>50%</span>
                    <span>75%</span>
                    <span>100%</span>
                </div>
            </div>

            <!-- Stage Indicators -->
            <div class="grid grid-cols-4 gap-2">
                <div class="text-center">
                    <div class="mx-auto mb-1 h-2 w-2 rounded-full {{ $importPercent >= 25 ? 'bg-green-500' : 'bg-slate-600' }}"></div>
                    <p class="text-xs text-slate-300">Started</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-1 h-2 w-2 rounded-full {{ $importPercent >= 50 ? 'bg-green-500' : ($importPercent >= 25 ? 'bg-yellow-500' : 'bg-slate-600') }}"></div>
                    <p class="text-xs text-slate-300">Processing</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-1 h-2 w-2 rounded-full {{ $importPercent >= 75 ? 'bg-green-500' : ($importPercent >= 50 ? 'bg-yellow-500' : 'bg-slate-600') }}"></div>
                    <p class="text-xs text-slate-300">Validating</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-1 h-2 w-2 rounded-full {{ $importPercent >= 95 ? 'bg-green-500' : ($importPercent >= 75 ? 'bg-yellow-500' : 'bg-slate-600') }}"></div>
                    <p class="text-xs text-slate-300">Completing</p>
                </div>
            </div>
        </div>

        <!-- Detailed Stats -->
        <div class="grid grid-cols-3 gap-4 rounded-lg bg-slate-800/50 p-4 backdrop-blur-sm">
            <div class="text-center">
                <div class="flex items-center justify-center gap-1">
                    <svg class="h-4 w-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                    <span class="text-xl font-bold text-white">{{ number_format($importTotal) }}</span>
                </div>
                <p class="text-xs text-slate-400">Total Items</p>
            </div>
            <div class="text-center">
                <div class="flex items-center justify-center gap-1">
                    <svg class="h-4 w-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-xl font-bold text-white">{{ number_format($importProcessed) }}</span>
                </div>
                <p class="text-xs text-slate-400">Completed</p>
            </div>
            <div class="text-center">
                <div class="flex items-center justify-center gap-1">
                    <svg class="h-4 w-4 {{ $importFailed > 0 ? 'text-red-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-xl font-bold text-white">{{ number_format($importFailed) }}</span>
                </div>
                <p class="text-xs text-slate-400">{{ $importFailed > 0 ? 'Failed' : 'Issues' }}</p>
            </div>
        </div>

        <!-- Action Buttons -->
        @if ($importFailed > 0)
            <div class="mt-4 flex gap-3">
                <button wire:click="retryFailedQueuedImports" 
                        wire:loading.attr="disabled"
                        class="flex-1 rounded-lg bg-gradient-to-r from-amber-500 to-orange-500 px-4 py-2 text-sm font-medium text-white transition-all hover:from-amber-600 hover:to-orange-600 disabled:opacity-50 shadow-lg">
                    <span wire:loading.remove>
                        <svg class="mr-1 inline h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Retry Failed
                    </span>
                    <span wire:loading>Retrying...</span>
                </button>
                <button wire:click="cancelImport" 
                        class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-slate-600">
                    Cancel
                </button>
            </div>
        @endif
    </div>
@endif
