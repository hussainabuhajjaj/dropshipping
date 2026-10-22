<x-filament-widgets::widget>
    <x-filament::section>

        {{-- Header --}}
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    Visitor Geography Map
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Country and city presence from tracked website visits.
                </p>
            </div>

            <div class="flex gap-3">
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-gray-500 dark:text-gray-400">Countries</div>
                    <div class="text-xl font-bold text-gray-950 dark:text-white">
                        {{ number_format((int) ($coverage['countries'] ?? 0)) }}
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-gray-500 dark:text-gray-400">Cities</div>
                    <div class="text-xl font-bold text-gray-950 dark:text-white">
                        {{ number_format((int) ($coverage['cities'] ?? 0)) }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Layout --}}
        <div class="grid gap-5 xl:grid-cols-[1.3fr_0.7fr]">

            {{-- MAP --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-[#07111f] p-4 shadow-sm dark:border-slate-700">

                @if(empty($points))
                    <div class="flex h-[320px] items-center justify-center text-sm text-slate-400">
                        No geographic data available
                    </div>
                @else
                    <svg viewBox="0 0 1000 500"
                         class="h-[320px] w-full rounded-xl bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.22),_rgba(7,17,31,0.96)_48%)]">

                        <defs>
                            <pattern id="geo-grid" width="80" height="40" patternUnits="userSpaceOnUse">
                                <path d="M 80 0 L 0 0 0 40"
                                      fill="none"
                                      stroke="rgba(148,163,184,0.12)"
                                      stroke-width="1" />
                            </pattern>
                        </defs>

                        <rect x="0" y="0" width="1000" height="500" fill="url(#geo-grid)" />

                        @foreach($points as $point)
                            <g>
                                <circle
                                    cx="{{ $point['x'] }}"
                                    cy="{{ $point['y'] }}"
                                    r="{{ $point['r'] }}"
                                    fill="rgba(249,115,22,0.22)"
                                />
                                <circle
                                    cx="{{ $point['x'] }}"
                                    cy="{{ $point['y'] }}"
                                    r="{{ max(3, $point['r'] / 2.4) }}"
                                    fill="#f97316"
                                />
                            </g>
                        @endforeach

                    </svg>
                @endif

                <p class="mt-3 text-xs text-slate-300/80">
                    Hotspots represent visitor sessions based on stored coordinates.
                    Larger dots indicate higher activity.
                </p>
            </div>

            {{-- SIDE PANEL --}}
            <div class="space-y-4">

                {{-- TOP COUNTRIES --}}
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                        Top Countries
                    </h4>

                    @if($topCountries->isEmpty())
                        <p class="mt-3 text-sm text-gray-500">No country data</p>
                    @else
                        @php
                            $maxCountrySessions = max(1, (int) $topCountries->max('sessions'));
                        @endphp

                        <div class="mt-3 space-y-3">
                            @foreach($topCountries as $country)
                                <div>
                                    <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ $country['country'] }}
                                        </span>
                                        <span class="text-gray-500 dark:text-gray-400">
                                            {{ number_format((int) $country['sessions']) }}
                                        </span>
                                    </div>

                                    <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div
                                            class="h-2 rounded-full bg-gradient-to-r from-amber-500 to-orange-500"
                                            style="width: {{
                                                max(10, min(100,
                                                    ((int) $country['sessions'] / $maxCountrySessions) * 100
                                                ))
                                            }}%">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- TOP CITIES --}}
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                        Top Cities
                    </h4>

                    @if($topCities->isEmpty())
                        <p class="mt-3 text-sm text-gray-500">No city data</p>
                    @else
                        <div class="mt-3 space-y-3">
                            @foreach($topCities as $city)
                                <div class="flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2.5 dark:bg-gray-800/70">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $city['city'] }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $city['country'] }}
                                        </div>
                                    </div>

                                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                        {{ number_format((int) $city['sessions']) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>
        </div>

    </x-filament::section>
</x-filament-widgets::widget>
