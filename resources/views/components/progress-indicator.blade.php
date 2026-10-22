{{-- Progress Indicator Selector Component --}}
@props([
    'activeImportTrackingKey' => null,
    'importPercent' => 0,
    'importTotal' => 0,
    'importProcessed' => 0,
    'importFailed' => 0,
    'importStatusLabel' => 'processing',
    'importStartedAt' => null,
    'pollInterval' => 3,
    'selectedDesign' => 1
])

@if ($activeImportTrackingKey)
    @switch($selectedDesign)
        @case(1)
            <x-progress-indicator-design-1 
                :activeImportTrackingKey="$activeImportTrackingKey"
                :importPercent="$importPercent"
                :importTotal="$importTotal"
                :importProcessed="$importProcessed"
                :importFailed="$importFailed"
                :importStatusLabel="$importStatusLabel"
                :importStartedAt="$importStartedAt"
                :pollInterval="$pollInterval" />
            @break
        
        @case(2)
            <x-progress-indicator-design-2 
                :activeImportTrackingKey="$activeImportTrackingKey"
                :importPercent="$importPercent"
                :importTotal="$importTotal"
                :importProcessed="$importProcessed"
                :importFailed="$importFailed"
                :importStatusLabel="$importStatusLabel"
                :importStartedAt="$importStartedAt"
                :pollInterval="$pollInterval" />
            @break
        
        @case(3)
            <x-progress-indicator-design-3 
                :activeImportTrackingKey="$activeImportTrackingKey"
                :importPercent="$importPercent"
                :importTotal="$importTotal"
                :importProcessed="$importProcessed"
                :importFailed="$importFailed"
                :importStatusLabel="$importStatusLabel"
                :importStartedAt="$importStartedAt"
                :pollInterval="$pollInterval" />
            @break
        
        @case(4)
            <x-progress-indicator-design-4 
                :activeImportTrackingKey="$activeImportTrackingKey"
                :importPercent="$importPercent"
                :importTotal="$importTotal"
                :importProcessed="$importProcessed"
                :importFailed="$importFailed"
                :importStatusLabel="$importStatusLabel"
                :importStartedAt="$importStartedAt"
                :pollInterval="$pollInterval" />
            @break
        
        @default
            <x-progress-indicator-design-3 
                :activeImportTrackingKey="$activeImportTrackingKey"
                :importPercent="$importPercent"
                :importTotal="$importTotal"
                :importProcessed="$importProcessed"
                :importFailed="$importFailed"
                :importStatusLabel="$importStatusLabel"
                :importStartedAt="$importStartedAt"
                :pollInterval="$pollInterval" />
            @break
    @endswitch
@endif
