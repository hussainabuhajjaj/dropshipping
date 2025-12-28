@extends('filament-panels::components.layout.index')

@section('content')
    <div>
        @parent
        
        <!-- Translation Progress Tracking -->
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mt-6">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    📡 Translation Progress
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Watch real-time translation status for all products
                </p>
            </div>
            <livewire:translation-progress />
        </div>
    </div>
@endsection

