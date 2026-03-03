{{-- Filament Progress Indicator - Design 2: Sleek Multi-Stage Progress --}}
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
         class="fi-ta-field fi-ta-field-text"
         style="background: linear-gradient(to right, #1e293b, #581c87, #1e293b); border: none; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); position: relative; overflow: hidden;">
        
        <!-- Animated Background Pattern -->
        <div style="position: absolute; inset: 0; opacity: 0.1;">
            <div style="position: absolute; inset: 0; background: linear-gradient(to right, transparent, rgba(255,255,255,0.2), transparent); animation: pulse 3s ease-in-out infinite;"></div>
        </div>

        <!-- Content -->
        <div style="position: relative; z-index: 10;">
            <!-- Header -->
            <div style="margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="position: relative;">
                        <div style="height: 2rem; width: 2rem; border-radius: 50%; background-color: rgba(255,255,255,0.2); padding: 0.25rem;">
                            <svg style="height: 1.5rem; width: 1.5rem; animation: spin 1s linear infinite; color: white;" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        @if ($importFailed > 0)
                            <div style="position: absolute; top: -0.25rem; right: -0.25rem; height: 0.75rem; width: 0.75rem; border-radius: 50%; background-color: #10b981; border: 2px solid #1e293b;"></div>
                        @endif
                    </div>
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: bold; color: white; margin: 0;">CJ Import in Progress</h3>
                        <p style="font-size: 0.875rem; color: #e9d5ff; margin: 0;">{{ str_replace('_', ' ', ucfirst($importStatusLabel)) }}</p>
                    </div>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 1.5rem; font-weight: bold; color: white; margin: 0;">{{ min(100, max(0, $importPercent)) }}%</p>
                    <p style="font-size: 0.75rem; color: #e9d5ff; margin: 0;">Complete</p>
                </div>
            </div>

            <!-- Multi-Stage Progress Bar -->
            <div style="margin-bottom: 1.5rem;">
                <!-- Main Progress -->
                <div style="position: relative; margin-bottom: 0.75rem;">
                    <div style="height: 0.5rem; width: 100%; overflow: hidden; border-radius: 9999px; background-color: rgba(255,255,255,0.1);">
                        <div style="height: 100%; border-radius: 9999px; background: linear-gradient(to right, #3b82f6, #ec4899, #3b82f6); transition: all 0.7s ease-out; width: {{ min(100, max(0, $importPercent)) }}%;">
                            <!-- Animated shimmer -->
                            <div style="height: 100%; width: 100%; background: linear-gradient(to right, transparent, rgba(255,255,255,0.3), transparent); animation: pulse 2s ease-in-out infinite;"></div>
                        </div>
                    </div>
                    <!-- Progress markers -->
                    <div style="margin-top: 0.5rem; display: flex; justify-content: space-between; font-size: 0.75rem; color: #e9d5ff;">
                        <span>0%</span>
                        <span>25%</span>
                        <span>50%</span>
                        <span>75%</span>
                        <span>100%</span>
                    </div>
                </div>

                <!-- Stage Indicators -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem;">
                    <div style="text-align: center;">
                        <div style="margin: 0 auto 0.25rem; height: 0.5rem; width: 0.5rem; border-radius: 50%; background-color: {{ $importPercent >= 25 ? '#10b981' : 'rgba(255,255,255,0.2)' }};"></div>
                        <p style="font-size: 0.75rem; color: #e9d5ff; margin: 0;">Started</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="margin: 0 auto 0.25rem; height: 0.5rem; width: 0.5rem; border-radius: 50%; background-color: {{ $importPercent >= 50 ? '#10b981' : ($importPercent >= 25 ? '#f59e0b' : 'rgba(255,255,255,0.2)') }};"></div>
                        <p style="font-size: 0.75rem; color: #e9d5ff; margin: 0;">Processing</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="margin: 0 auto 0.25rem; height: 0.5rem; width: 0.5rem; border-radius: 50%; background-color: {{ $importPercent >= 75 ? '#10b981' : ($importPercent >= 50 ? '#f59e0b' : 'rgba(255,255,255,0.2)') }};"></div>
                        <p style="font-size: 0.75rem; color: #e9d5ff; margin: 0;">Validating</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="margin: 0 auto 0.25rem; height: 0.5rem; width: 0.5rem; border-radius: 50%; background-color: {{ $importPercent >= 95 ? '#10b981' : ($importPercent >= 75 ? '#f59e0b' : 'rgba(255,255,255,0.2)') }};"></div>
                        <p style="font-size: 0.75rem; color: #e9d5ff; margin: 0;">Completing</p>
                    </div>
                </div>
            </div>

            <!-- Detailed Stats -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; border-radius: 0.5rem; background-color: rgba(255,255,255,0.05); padding: 1rem; backdrop-filter: blur(10px);">
                <div style="text-align: center;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 0.25rem;">
                        <svg style="height: 1rem; width: 1rem; color: #60a5fa;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        <span style="font-size: 1.25rem; font-weight: bold; color: white;">{{ number_format($importTotal) }}</span>
                    </div>
                    <p style="font-size: 0.75rem; color: #e9d5ff; margin: 0;">Total Items</p>
                </div>
                <div style="text-align: center;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 0.25rem;">
                        <svg style="height: 1rem; width: 1rem; color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span style="font-size: 1.25rem; font-weight: bold; color: white;">{{ number_format($importProcessed) }}</span>
                    </div>
                    <p style="font-size: 0.75rem; color: #e9d5ff; margin: 0;">Completed</p>
                </div>
                <div style="text-align: center;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 0.25rem;">
                        <svg style="height: 1rem; width: 1rem; color: {{ $importFailed > 0 ? '#ef4444' : '#9ca3af' }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span style="font-size: 1.25rem; font-weight: bold; color: white;">{{ number_format($importFailed) }}</span>
                    </div>
                    <p style="font-size: 0.75rem; color: #e9d5ff; margin: 0;">{{ $importFailed > 0 ? 'Failed' : 'Issues' }}</p>
                </div>
            </div>

            <!-- Progress Messages -->
            <div style="margin-top: 1rem; border-radius: 0.5rem; background-color: rgba(255,255,255,0.05); padding: 0.75rem; backdrop-filter: blur(10px);">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="height: 0.5rem; width: 0.5rem; border-radius: 50%; background-color: {{ $importFailed > 0 ? '#f59e0b' : '#10b981' }}; animation: pulse 2s ease-in-out infinite;"></div>
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
                            class="fi-btn fi-btn-primary"
                            style="flex: 1; border-radius: 0.5rem; background: linear-gradient(to right, #f59e0b, #f97316); padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 500; color: white; border: none; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); disabled: opacity: 0.5;"
                            onmouseover="this.style.background='linear-gradient(to right, #d97706, #ea580c)'"
                            onmouseout="this.style.background='linear-gradient(to right, #f59e0b, #f97316)'">
                        <span wire:loading.remove>
                            <svg style="display: inline; height: 1rem; width: 1rem; margin-right: 0.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Retry Failed
                        </span>
                        <span wire:loading>Retrying...</span>
                    </button>
                    <button wire:click="cancelImport" 
                            class="fi-btn fi-btn-secondary"
                            style="border-radius: 0.5rem; background-color: rgba(107, 114, 128, 0.5); padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 500; color: white; border: none; cursor: pointer; transition: background-color 0.2s; backdrop-filter: blur(10px);"
                            onmouseover="this.style.backgroundColor='rgba(75, 85, 99, 0.7)'"
                            onmouseout="this.style.backgroundColor='rgba(107, 114, 128, 0.5)'">
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
