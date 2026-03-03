{{-- Filament Progress Indicator - Design 3: Minimalist Card-Based Progress --}}
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
         style="border-radius: 1rem; border: 1px solid #e5e7eb; background-color: white; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
        
        <!-- Header -->
        <div style="margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="position: relative;">
                    <div style="height: 2.5rem; width: 2.5rem; border-radius: 50%; background-color: #dbeafe; padding: 0.5rem;">
                        <svg style="height: 1.5rem; width: 1.5rem; animation: spin 1s linear infinite; color: #2563eb;" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    @if ($importFailed > 0)
                        <div style="position: absolute; top: -0.25rem; right: -0.25rem; height: 0.75rem; width: 0.75rem; border-radius: 50%; background-color: #ef4444; border: 2px solid white;"></div>
                    @endif
                </div>
                <div>
                    <h3 style="font-size: 1.125rem; font-weight: 600; color: #111827; margin: 0;">Import Progress</h3>
                    <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">{{ str_replace('_', ' ', ucfirst($importStatusLabel)) }}</p>
                </div>
            </div>
            <div style="text-align: right;">
                <p style="font-size: 1.5rem; font-weight: bold; color: #111827; margin: 0;">{{ min(100, max(0, $importPercent)) }}%</p>
                <p style="font-size: 0.75rem; color: #6b7280; margin: 0;">Complete</p>
            </div>
        </div>

        <!-- Progress Bar -->
        <div style="margin-bottom: 1.5rem;">
            <div style="position: relative; height: 0.75rem; width: 100%; overflow: hidden; border-radius: 9999px; background-color: #e5e7eb;">
                <div style="height: 100%; border-radius: 9999px; background: linear-gradient(to right, #3b82f6, #2563eb); transition: all 0.5s ease-out; width: {{ min(100, max(0, $importPercent)) }}%;">
                    <!-- Animated stripes -->
                    <div style="height: 100%; width: 100%; background: linear-gradient(to right, transparent, rgba(255,255,255,0.2), transparent); animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;"></div>
                </div>
            </div>
            <div style="margin-top: 0.5rem; display: flex; justify-content: space-between; font-size: 0.75rem; color: #6b7280;">
                <span>0</span>
                <span>{{ number_format($importTotal) }} items</span>
                <span>100%</span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem;">
            <!-- Total Card -->
            <div style="border-radius: 0.5rem; border: 1px solid #e5e7eb; background-color: #f9fafb; padding: 0.75rem;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <div style="height: 2rem; width: 2rem; border-radius: 50%; background-color: #dbeafe; padding: 0.375rem;">
                        <svg style="height: 1.25rem; width: 1.25rem; color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <div>
                        <p style="font-size: 1.125rem; font-weight: bold; color: #111827; margin: 0;">{{ number_format($importTotal) }}</p>
                        <p style="font-size: 0.75rem; color: #6b7280; margin: 0;">Total</p>
                    </div>
                </div>
            </div>

            <!-- Completed Card -->
            <div style="border-radius: 0.5rem; border: 1px solid #e5e7eb; background-color: #f0fdf4; padding: 0.75rem;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <div style="height: 2rem; width: 2rem; border-radius: 50%; background-color: #dcfce7; padding: 0.375rem;">
                        <svg style="height: 1.25rem; width: 1.25rem; color: #16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p style="font-size: 1.125rem; font-weight: bold; color: #14532d; margin: 0;">{{ number_format($importProcessed) }}</p>
                        <p style="font-size: 0.75rem; color: #16a34a; margin: 0;">Done</p>
                    </div>
                </div>
            </div>

            <!-- Failed Card -->
            <div style="border-radius: 0.5rem; border: 1px solid {{ $importFailed > 0 ? '#fecaca' : '#e5e7eb' }}; background-color: {{ $importFailed > 0 ? '#fef2f2' : '#f9fafb' }}; padding: 0.75rem;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <div style="height: 2rem; width: 2rem; border-radius: 50%; background-color: {{ $importFailed > 0 ? '#fecaca' : '#f3f4f6' }}; padding: 0.375rem;">
                        <svg style="height: 1.25rem; width: 1.25rem; color: {{ $importFailed > 0 ? '#dc2626' : '#6b7280' }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p style="font-size: 1.125rem; font-weight: bold; color: {{ $importFailed > 0 ? '#7f1d1d' : '#111827' }}; margin: 0;">{{ number_format($importFailed) }}</p>
                        <p style="font-size: 0.75rem; color: {{ $importFailed > 0 ? '#dc2626' : '#6b7280' }}; margin: 0;">{{ $importFailed > 0 ? 'Failed' : 'Issues' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Time Estimate -->
        <div style="margin-top: 1rem; border-radius: 0.5rem; background-color: #eff6ff; padding: 0.75rem;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg style="height: 1rem; width: 1rem; color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span style="font-size: 0.875rem; font-weight: 500; color: #1e3a8a;">Time Remaining</span>
                </div>
                @if ($importProcessed > 0)
                    <?php 
                    $remaining = $importTotal - $importProcessed;
                    $rate = $importProcessed / max(1, microtime(true) - ($importStartedAt ? strtotime($importStartedAt) : microtime(true)));
                    $eta = $remaining / max(0.1, $rate);
                    ?>
                    <span style="font-size: 0.875rem; color: #1d4ed8;">
                        {{ floor($eta / 60) }}m {{ floor($eta % 60) }}s
                    </span>
                @else
                    <span style="font-size: 0.875rem; color: #1d4ed8;">Calculating...</span>
                @endif
            </div>
        </div>

        <!-- Action Buttons -->
        @if ($importFailed > 0)
            <div style="margin-top: 1rem; display: flex; gap: 0.75rem;">
                <button wire:click="retryFailedQueuedImports" 
                        wire:loading.attr="disabled"
                        class="fi-btn fi-btn-danger"
                        style="flex: 1; border-radius: 0.5rem; background-color: #dc2626; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: white; border: none; cursor: pointer; transition: background-color 0.2s; disabled: opacity: 0.5;"
                        onmouseover="this.style.backgroundColor='#b91c1c'"
                        onmouseout="this.style.backgroundColor='#dc2626'">
                    <span wire:loading.remove>Retry Failed Items</span>
                    <span wire:loading>Retrying...</span>
                </button>
                <button wire:click="cancelImport" 
                        class="fi-btn fi-btn-secondary"
                        style="border-radius: 0.5rem; border: 1px solid #d1d5db; background-color: white; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: #374151; border: none; cursor: pointer; transition: background-color 0.2s;"
                        onmouseover="this.style.backgroundColor='#f9fafb'"
                        onmouseout="this.style.backgroundColor='white'">
                    Cancel
                </button>
            </div>
        @endif
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
