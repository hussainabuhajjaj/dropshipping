@php
use App\Filament\Components\ProgressIndicator;

/** @var ProgressIndicator $component */
@endphp

{{-- Debug: Show current design --}}
<div style="background: #fbbf24; padding: 0.25rem; margin-bottom: 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; text-align: center;">
    DEBUG: Current Design = {{ $component->getDesign() }}
</div>

@if ($component->getActiveImportTrackingKey())
    @switch($component->getDesign())
        @case(1)
            <div style="background: #dbeafe; padding: 0.5rem; margin-bottom: 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; text-align: center;">
                LOADING DESIGN 1: Modern Circular Progress
            </div>
            <x-progress-indicator-design-1 
                :activeImportTrackingKey="$component->getActiveImportTrackingKey()"
                :importPercent="$component->getImportPercent()"
                :importTotal="$component->getImportTotal()"
                :importProcessed="$component->getImportProcessed()"
                :importFailed="$component->getImportFailed()"
                :importStatusLabel="$component->getImportStatusLabel()"
                :importStartedAt="$component->getImportStartedAt()"
                :pollInterval="$component->getPollInterval()" />
            @break
        
        @case(2)
            <div style="background: #f3e8ff; padding: 0.5rem; margin-bottom: 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; text-align: center;">
                LOADING DESIGN 2: Sleek Multi-Stage Progress
            </div>
            <x-progress-indicator-design-2 
                :activeImportTrackingKey="$component->getActiveImportTrackingKey()"
                :importPercent="$component->getImportPercent()"
                :importTotal="$component->getImportTotal()"
                :importProcessed="$component->getImportProcessed()"
                :importFailed="$component->getImportFailed()"
                :importStatusLabel="$component->getImportStatusLabel()"
                :importStartedAt="$component->getImportStartedAt()"
                :pollInterval="$component->getPollInterval()" />
            @break
        
        @case(3)
            <div style="background: #dcfce7; padding: 0.5rem; margin-bottom: 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; text-align: center;">
                LOADING DESIGN 3: Minimalist Card-Based Progress
            </div>
            <x-progress-indicator-design-3 
                :activeImportTrackingKey="$component->getActiveImportTrackingKey()"
                :importPercent="$component->getImportPercent()"
                :importTotal="$component->getImportTotal()"
                :importProcessed="$component->getImportProcessed()"
                :importFailed="$component->getImportFailed()"
                :importStatusLabel="$component->getImportStatusLabel()"
                :importStartedAt="$component->getImportStartedAt()"
                :pollInterval="$component->getPollInterval()" />
            @break
        
        @case(4)
            <div style="background: #fce7f3; padding: 0.5rem; margin-bottom: 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; text-align: center;">
                LOADING DESIGN 4: Modern Animated Pulse
            </div>
            <x-progress-indicator-design-4 
                :activeImportTrackingKey="$component->getActiveImportTrackingKey()"
                :importPercent="$component->getImportPercent()"
                :importTotal="$component->getImportTotal()"
                :importProcessed="$component->getImportProcessed()"
                :importFailed="$component->getImportFailed()"
                :importStatusLabel="$component->getImportStatusLabel()"
                :importStartedAt="$component->getImportStartedAt()"
                :pollInterval="$component->getPollInterval()" />
            @break
        
        @default
            <div style="background: #fee2e2; padding: 0.5rem; margin-bottom: 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; text-align: center;">
                DEFAULT: Loading Design 3 (Invalid design: {{ $component->getDesign() }})
            </div>
            <x-progress-indicator-design-3 
                :activeImportTrackingKey="$component->getActiveImportTrackingKey()"
                :importPercent="$component->getImportPercent()"
                :importTotal="$component->getImportTotal()"
                :importProcessed="$component->getImportProcessed()"
                :importFailed="$component->getImportFailed()"
                :importStatusLabel="$component->getImportStatusLabel()"
                :importStartedAt="$component->getImportStartedAt()"
                :pollInterval="$component->getPollInterval()" />
            @break
    @endswitch
@else
    <div style="background: #fca5a5; padding: 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; text-align: center;">
        NO ACTIVE IMPORT TRACKING KEY
    </div>
@endif
