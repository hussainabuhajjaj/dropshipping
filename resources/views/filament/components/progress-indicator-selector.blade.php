{{-- Progress Indicator Selector - Production Ready --}}
@props([
    'activeImportTrackingKey' => null,
    'importPercent' => 0,
    'importTotal' => 0,
    'importProcessed' => 0,
    'importFailed' => 0,
    'importStatusLabel' => 'processing',
    'importStartedAt' => null,
    'pollInterval' => 3,
    'selectedDesign' => 3
])

@if ($activeImportTrackingKey)
    @switch($selectedDesign)
        @case(1)
            @include('filament.components.progress-indicator-design-1', [
                'activeImportTrackingKey' => $activeImportTrackingKey,
                'importPercent' => $importPercent,
                'importTotal' => $importTotal,
                'importProcessed' => $importProcessed,
                'importFailed' => $importFailed,
                'importStatusLabel' => $importStatusLabel,
                'importStartedAt' => $importStartedAt,
                'pollInterval' => $pollInterval,
            ])
            @break
        
        @case(2)
            @include('filament.components.progress-indicator-design-2', [
                'activeImportTrackingKey' => $activeImportTrackingKey,
                'importPercent' => $importPercent,
                'importTotal' => $importTotal,
                'importProcessed' => $importProcessed,
                'importFailed' => $importFailed,
                'importStatusLabel' => $importStatusLabel,
                'importStartedAt' => $importStartedAt,
                'pollInterval' => $pollInterval,
            ])
            @break
        
        @case(3)
            @include('filament.components.progress-indicator-design-3', [
                'activeImportTrackingKey' => $activeImportTrackingKey,
                'importPercent' => $importPercent,
                'importTotal' => $importTotal,
                'importProcessed' => $importProcessed,
                'importFailed' => $importFailed,
                'importStatusLabel' => $importStatusLabel,
                'importStartedAt' => $importStartedAt,
                'pollInterval' => $pollInterval,
            ])
            @break
        
        @case(4)
            @include('filament.components.progress-indicator-design-4', [
                'activeImportTrackingKey' => $activeImportTrackingKey,
                'importPercent' => $importPercent,
                'importTotal' => $importTotal,
                'importProcessed' => $importProcessed,
                'importFailed' => $importFailed,
                'importStatusLabel' => $importStatusLabel,
                'importStartedAt' => $importStartedAt,
                'pollInterval' => $pollInterval,
            ])
            @break
        
        @default
            @include('filament.components.progress-indicator-design-3', [
                'activeImportTrackingKey' => $activeImportTrackingKey,
                'importPercent' => $importPercent,
                'importTotal' => $importTotal,
                'importProcessed' => $importProcessed,
                'importFailed' => $importFailed,
                'importStatusLabel' => $importStatusLabel,
                'importStartedAt' => $importStartedAt,
                'pollInterval' => $pollInterval,
            ])
            @break
    @endswitch
@endif
