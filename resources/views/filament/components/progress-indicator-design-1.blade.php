{{-- Filament Progress Indicator - Design 1: Modern Circular Progress --}}
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
         style="background: linear-gradient(135deg, #f3f4f6 0%, #ffffff 50%, #ede9fe 100%); border: 1px solid #e5e7eb; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); position: relative; overflow: hidden;">
        
        <!-- Circular Progress Container -->
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <!-- Left Side - Circular Progress -->
            <div style="position: relative;">
                <div style="width: 6rem; height: 6rem; position: relative;">
                    <svg width="96" height="96" style="transform: rotate(-90deg);">
                        <!-- Background Circle -->
                        <circle cx="48" cy="48" r="40" stroke="#e5e7eb" stroke-width="8" fill="none"></circle>
                        <!-- Progress Circle -->
                        <circle cx="48" cy="48" r="40" stroke="{{ $importFailed > 0 ? '#f59e0b' : '#10b981' }}" stroke-width="8" fill="none"
                                stroke-dasharray="{{ 251.2 * (min(100, max(0, $importPercent)) / 100) }} 251.2"
                                style="transition: all 0.5s ease-out;">
                            <animate attributeName="stroke-dasharray" 
                                     from="0 251.2" 
                                     to="{{ 251.2 * (min(100, max(0, $importPercent)) / 100) }} 251.2" 
                                     dur="0.5s" 
                                     fill="freeze"/>
                        </circle>
                    </svg>
                    <!-- Center Content -->
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <span style="font-size: 1.5rem; font-weight: bold; color: #1f2937;">{{ min(100, max(0, $importPercent)) }}%</span>
                        <span style="font-size: 0.75rem; color: #6b7280;">{{ str_replace('_', ' ', ucfirst($importStatusLabel)) }}</span>
                    </div>
                </div>
            </div>

            <!-- Right Side - Stats & Info -->
            <div style="flex: 1; padding-left: 1.5rem;">
                <!-- Status Badge -->
                <div style="margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 0.5rem; height: 0.5rem; background-color: {{ $importFailed > 0 ? '#f59e0b' : '#10b981' }}; border-radius: 50%; position: relative;">
                        <div style="width: 0.5rem; height: 0.5rem; background-color: {{ $importFailed > 0 ? '#fbbf24' : '#34d399' }}; border-radius: 50%; position: absolute; top: 0; left: 0; animation: ping 1s cubic-bezier(0, 0, 0.2, 1) infinite;"></div>
                    </div>
                    <span style="font-size: 0.875rem; font-weight: 500; color: #374151;">
                        {{ $importFailed > 0 ? 'Processing with Issues' : 'Processing Smoothly' }}
                    </span>
                </div>

                <!-- Progress Stats Grid -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                    <div style="text-align: center;">
                        <p style="font-size: 1.25rem; font-weight: bold; color: #1f2937; margin: 0;">{{ number_format($importTotal) }}</p>
                        <p style="font-size: 0.75rem; color: #6b7280; margin: 0;">Total Items</p>
                    </div>
                    <div style="text-align: center;">
                        <p style="font-size: 1.25rem; font-weight: bold; color: #10b981; margin: 0;">{{ number_format($importProcessed) }}</p>
                        <p style="font-size: 0.75rem; color: #6b7280; margin: 0;">Completed</p>
                    </div>
                    <div style="text-align: center;">
                        <p style="font-size: 1.25rem; font-weight: bold; {{ $importFailed > 0 ? 'color: #ef4444' : 'color: #6b7280' }}; margin: 0;">{{ number_format($importFailed) }}</p>
                        <p style="font-size: 0.75rem; color: #6b7280; margin: 0;">{{ $importFailed > 0 ? 'Failed' : 'Issues' }}</p>
                    </div>
                </div>

                <!-- Time Estimate -->
                <div style="margin-top: 0.75rem; font-size: 0.75rem; color: #6b7280;">
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
            <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                <button wire:click="retryFailedQueuedImports" 
                        wire:loading.attr="disabled"
                        class="fi-btn fi-btn-primary"
                        style="flex: 1; background-color: #f59e0b; color: white; padding: 0.5rem 0.75rem; border-radius: 0.5rem; font-size: 0.75rem; font-weight: 500; border: none; cursor: pointer; transition: background-color 0.2s;"
                        onmouseover="this.style.backgroundColor='#d97706'"
                        onmouseout="this.style.backgroundColor='#f59e0b'">
                    <span wire:loading.remove>Retry Failed Items</span>
                    <span wire:loading>Retrying...</span>
                </button>
                <button wire:click="cancelImport" 
                        class="fi-btn fi-btn-secondary"
                        style="background-color: #6b7280; color: white; padding: 0.5rem 0.75rem; border-radius: 0.5rem; font-size: 0.75rem; font-weight: 500; border: none; cursor: pointer; transition: background-color 0.2s;"
                        onmouseover="this.style.backgroundColor='#4b5563'"
                        onmouseout="this.style.backgroundColor='#6b7280'">
                    Cancel
                </button>
            </div>
        @endif
    </div>

    <!-- Add CSS animation -->
    <style>
        @keyframes ping {
            75%, 100% {
                transform: scale(2);
                opacity: 0;
            }
        }
    </style>
@endif
