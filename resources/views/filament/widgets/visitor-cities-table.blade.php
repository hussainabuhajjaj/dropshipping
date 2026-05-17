<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Top Visitor Cities</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Where website sessions are clustering most.</p>
            </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/70">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">City</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Country</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Sessions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-950">
                    @forelse($rows as $row)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $row['city'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $row['country'] }}</td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-200">{{ number_format((int) $row['sessions']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No city-level visitor data yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
