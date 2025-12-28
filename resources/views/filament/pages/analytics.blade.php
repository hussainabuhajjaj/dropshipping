<x-filament-panels::page>
    <div class="space-y-6">
        {{-- KPI Cards --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @php
                $analytics = $this->getAnalyticsSummary();
            @endphp

            {{-- Today's Orders --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Today's Orders</p>
                            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $analytics['today_orders'] }}</p>
                        </div>
                        <div class="rounded-full {{ $analytics['today_order_change'] >= 0 ? 'bg-success-100 dark:bg-success-900/30' : 'bg-danger-100 dark:bg-danger-900/30' }} p-3">
                            <x-filament::icon icon="{{ $analytics['today_order_change'] >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down' }}" class="h-6 w-6 {{ $analytics['today_order_change'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}" />
                        </div>
                    </div>
                    <p class="mt-2 text-xs {{ $analytics['today_order_change'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ $analytics['today_order_change'] >= 0 ? '+' : '' }}{{ $analytics['today_order_change'] }}% vs yesterday
                    </p>
                </div>
            </div>

            {{-- Today's Revenue --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Today's Revenue</p>
                            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">${{ number_format($analytics['today_revenue'], 2) }}</p>
                        </div>
                        <div class="rounded-full {{ $analytics['today_revenue_change'] >= 0 ? 'bg-success-100 dark:bg-success-900/30' : 'bg-danger-100 dark:bg-danger-900/30' }} p-3">
                            <x-filament::icon icon="{{ $analytics['today_revenue_change'] >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down' }}" class="h-6 w-6 {{ $analytics['today_revenue_change'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}" />
                        </div>
                    </div>
                    <p class="mt-2 text-xs {{ $analytics['today_revenue_change'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ $analytics['today_revenue_change'] >= 0 ? '+' : '' }}{{ $analytics['today_revenue_change'] }}% vs yesterday
                    </p>
                </div>
            </div>

            {{-- This Month AOV --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Average Order Value</p>
                            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">${{ number_format($analytics['month_aov'], 2) }}</p>
                        </div>
                        <div class="rounded-full bg-primary-100 p-3 dark:bg-primary-900/30">
                            <x-filament::icon icon="heroicon-o-banknotes" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ $analytics['month_orders'] }} orders this month
                    </p>
                </div>
            </div>

            {{-- Payment Success Rate --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Payment Success Rate</p>
                            <p class="mt-2 text-3xl font-bold {{ $analytics['payment_success_rate'] >= 95 ? 'text-success-600' : ($analytics['payment_success_rate'] >= 80 ? 'text-warning-600' : 'text-danger-600') }}">{{ $analytics['payment_success_rate'] }}%</p>
                        </div>
                        <div class="rounded-full {{ $analytics['payment_success_rate'] >= 95 ? 'bg-success-100 dark:bg-success-900/30' : ($analytics['payment_success_rate'] >= 80 ? 'bg-warning-100 dark:bg-warning-900/30' : 'bg-danger-100 dark:bg-danger-900/30') }} p-3">
                            <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 {{ $analytics['payment_success_rate'] >= 95 ? 'text-success-600 dark:text-success-400' : ($analytics['payment_success_rate'] >= 80 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400') }}" />
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ $analytics['payment_failed'] }} failed payments
                    </p>
                </div>
            </div>
        </div>

        {{-- Fulfillment Stats --}}
        <x-filament::section
            heading="Fulfillment"
            description="Shipping and delivery performance"
            icon="heroicon-o-truck"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Average Lead Time</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $analytics['fulfillment_lead_time_days'] }} days</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">From order to shipment</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Month Summary --}}
        <x-filament::section
            heading="This Month"
            description="Performance metrics for {{ now()->format('F Y') }}"
            icon="heroicon-o-calendar"
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Orders</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $analytics['month_orders'] }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Revenue</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">${{ number_format($analytics['month_revenue'], 2) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">AOV</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">${{ number_format($analytics['month_aov'], 2) }}</p>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
