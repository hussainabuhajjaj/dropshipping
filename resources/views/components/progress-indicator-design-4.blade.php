{{-- Progress Indicator Component - Design 4: Modern Animated Pulse --}}
@props([
    'activeImportTrackingKey' => null,
    'importPercent' => 0,
    'importTotal' => 0,
    'importProcessed' => 0,
    'importFailed' => 0,
    'importStatusLabel' => 'processing',
    'importStartedAt' => null,
    'pollInterval' => 3
])

@if ($activeImportTrackingKey)
    <div wire:poll.{{ $pollInterval }}s="refreshQueueImportStatus" 
         style="position: relative; overflow: hidden; border-radius: 1rem; border: none; background: linear-gradient(135deg, #7c3aed, #a855f7, #6366f1); padding: 1.5rem; box-shadow: 0 25px 50px -12px rgba(124, 58, 237, 0.25);">
        
        <!-- Animated Background Pattern -->
        <div style="position: absolute; inset: 0; opacity: 0.1;">
            <div style="position: absolute; inset: 0; background: linear-gradient(to right, transparent, rgba(255,255,255,0.2), transparent); animation: pulse 3s ease-in-out infinite;"></div>
            <svg style="height: 100%; width: 100%;" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"></path>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)"></rect>
            </svg>
        </div>

        <!-- Content -->
        <div style="position: relative; z-index: 10;">
            <!-- Header -->
            <div style="margin-bottom: 1.5rem; text-align: center;">
                <div style="margin: 0 auto 1rem; height: 4rem; width: 4rem; border-radius: 50%; background-color: rgba(255,255,255,0.2); padding: 0.75rem; backdrop-filter: blur(10px);">
                    <div style="position: relative; height: 100%; width: 100%;">
                        <svg style="height: 100%; width: 100%; animation: spin 1s linear infinite; color: white;" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <!-- Center percentage -->
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: flex; align-items: center; justify-content: center;">
                            <span style="font-size: 0.875rem; font-weight: bold; color: white;">{{ min(100, max(0, $importPercent)) }}%</span>
                        </div>
                    </div>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: bold; color: white; margin: 0;">CJ Import in Progress</h3>
                <p style="font-size: 0.875rem; color: #e9d5ff; margin: 0;">{{ str_replace('_', ' ', ucfirst($importStatusLabel)) }}</p>
            </div>

            <!-- Circular Progress Ring -->
            <div style="margin-bottom: 1.5rem; display: flex; justify-content: center;">
                <div style="position: relative; height: 8rem; width: 8rem;">
                    <!-- Background ring -->
                    <svg width="128" height="128" style="transform: rotate(-90deg);">
                        <circle cx="64" cy="64" r="56" stroke="rgba(255,255,255,0.2)" stroke-width="12" fill="none"></circle>
                        <!-- Progress ring -->
                        <circle cx="64" cy="64" r="56" stroke="url(#gradient)" stroke-width="12" fill="none"
                                stroke-dasharray="{{ 351.86 * (min(100, max(0, $importPercent)) / 100) }} 351.86"
                                stroke-linecap="round"
                                style="transition: all 0.7s ease-out;">
                            <animate attributeName="stroke-dasharray" 
                                     from="0 351.86" 
                                     to="{{ 351.86 * (min(100, max(0, $importPercent)) / 100) }} 351.86" 
                                     dur="0.7s" 
                                     fill="freeze"/>
                        </circle>
                    </svg>
                    <!-- Gradient definition -->
                    <svg style="position: absolute; height: 0; width: 0;">
                        <defs>
                            <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#60a5fa"></stop>
                                <stop offset="50%" stop-color="#a78bfa"></stop>
                                <stop offset="100%" stop-color="#f472b6"></stop>
                            </linearGradient>
                        </defs>
                    </svg>
                    <!-- Center content -->
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <p style="font-size: 1.875rem; font-weight: bold; color: white; margin: 0;">{{ min(100, max(0, $importPercent)) }}%</p>
                        <p style="font-size: 0.75rem; color: #e9d5ff; margin: 0;">Complete</p>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                <div style="border-radius: 0.5rem; background-color: rgba(255,255,255,0.1); padding: 1rem; text-align: center; backdrop-filter: blur(10px);">
                    <div style="margin-bottom: 0.5rem; display: flex; justify-content: center;">
                        <div style="height: 2rem; width: 2rem; border-radius: 50%; background-color: #3b82f6; padding: 0.5rem;">
                            <svg style="height: 1rem; width: 1rem; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                    </div>
                    <p style="font-size: 1.25rem; font-weight: bold; color: white; margin: 0;">{{ number_format($importTotal) }}</p>
                    <p style="font-size: 0.75rem; color: #e9d5ff; margin: 0;">Total Items</p>
                </div>

                <div style="border-radius: 0.5rem; background-color: rgba(255,255,255,0.1); padding: 1rem; text-align: center; backdrop-filter: blur(10px);">
                    <div style="margin-bottom: 0.5rem; display: flex; justify-content: center;">
                        <div style="height: 2rem; width: 2rem; border-radius: 50%; background-color: #10b981; padding: 0.5rem;">
                            <svg style="height: 1rem; width: 1rem; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <p style="font-size: 1.25rem; font-weight: bold; color: white; margin: 0;">{{ number_format($importProcessed) }}</p>
                    <p style="font-size: 0.75rem; color: #a7f3d0; margin: 0;">Completed</p>
                </div>

                <div style="border-radius: 0.5rem; {{ $importFailed > 0 ? 'background-color: rgba(239, 68, 68, 0.2)' : 'background-color: rgba(255,255,255,0.1)' }}; padding: 1rem; text-align: center; backdrop-filter: blur(10px);">
                    <div style="margin-bottom: 0.5rem; display: flex; justify-content: center;">
                        <div style="height: 2rem; width: 2rem; border-radius: 50%; {{ $importFailed > 0 ? 'background-color: #ef4444' : 'background-color: #6b7280' }}; padding: 0.5rem;">
                            <svg style="height: 1rem; width: 1rem; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <p style="font-size: 1.25rem; font-weight: bold; color: white; margin: 0;">{{ number_format($importFailed) }}</p>
                    <p style="font-size: 0.75rem; color: {{ $importFailed > 0 ? '#fecaca' : '#e9d5ff' }}; margin: 0;">{{ $importFailed > 0 ? 'Failed' : 'Issues' }}</p>
                </div>
            </div>

            <!-- Progress Messages -->
            <div style="margin-top: 1rem; border-radius: 0.5rem; background-color: rgba(255,255,255,0.1); padding: 0.75rem; backdrop-filter: blur(10px);">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="height: 0.5rem; width: 0.5rem; border-radius: 50%; {{ $importFailed > 0 ? 'background-color: #f59e0b' : 'background-color: #10b981' }}; animation: pulse 2s ease-in-out infinite;"></div>
                        <span style="font-size: 0.875rem; color: white;">
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
                        <span style="font-size: 0.75rem; color: #e9d5ff;">
                            {{ floor($eta / 60) }}m {{ floor($eta % 60) }}s remaining
                        </span>
                    @endif
                </div>
            </div>

            <!-- Action Buttons -->
            @if ($importFailed > 0)
                <div style="margin-top: 1rem; display: flex; gap: 0.75rem;">
                    <button wire:click="retryFailedQueuedImports" 
                            wire:loading.attr="disabled"
                            style="flex: 1; border-radius: 0.5rem; background-color: rgba(255,255,255,0.2); padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 500; color: white; border: none; cursor: pointer; transition: all 0.2s; backdrop-filter: blur(10px); disabled: opacity: 0.5;"
                            onmouseover="this.style.backgroundColor='rgba(255,255,255,0.3)'"
                            onmouseout="this.style.backgroundColor='rgba(255,255,255,0.2)'">
                        <span wire:loading.remove>
                            <svg style="display: inline; height: 1rem; width: 1rem; margin-right: 0.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Retry Failed Items
                        </span>
                        <span wire:loading>Retrying...</span>
                    </button>
                    <button wire:click="cancelImport" 
                            style="border-radius: 0.5rem; background-color: rgba(239, 68, 68, 0.2); padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 500; color: white; border: none; cursor: pointer; transition: all 0.2s; backdrop-filter: blur(10px);"
                            onmouseover="this.style.backgroundColor='rgba(239, 68, 68, 0.3)'"
                            onmouseout="this.style.backgroundColor='rgba(239, 68, 68, 0.2)'">
                        Cancel
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Add CSS animations -->
    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
@endif
