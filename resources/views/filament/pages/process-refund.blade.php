@extends('filament::layouts.page')

@section('content')
<div class="filament-page">
    <div class="space-y-6">
        <!-- Back link -->
        <div>
            <a href="{{ route('filament.admin.resources.orders.index') }}" class="text-blue-600 hover:text-blue-800">
                ← Back to Orders
            </a>
        </div>

        <!-- Header -->
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Process Refund</h1>
            <p class="mt-2 text-gray-600">
                Review and process a refund for order
                <span class="font-mono font-semibold">{{ $this->record->order_number }}</span>
            </p>
        </div>

        <!-- Form -->
        <form wire:submit.prevent="submit" class="space-y-6">
            {{ $this->form }}

            <!-- Action Buttons -->
            <div class="flex gap-3">
                <button
                    type="submit"
                    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 font-medium"
                >
                    ✓ Process Refund
                </button>
                <a
                    href="{{ route('filament.admin.resources.orders.view', $this->record) }}"
                    class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 font-medium"
                >
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
