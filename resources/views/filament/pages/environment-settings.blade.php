<x-filament-panels::page>
    <form wire:submit.prevent="save" class="space-y-6">
        @foreach ($groups as $groupName => $group)
            <x-filament::section>
                <x-slot name="heading">{{ $groupName }}</x-slot>
                @if (! empty($group['description']))
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $group['description'] }}</p>
                @endif

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @foreach ($group['fields'] as $key => $field)
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $field['label'] ?? $key }}
                            </label>

                            @php($type = $field['type'] ?? 'text')

                            @if ($type === 'select')
                                <select
                                    wire:model.defer="values.{{ $key }}"
                                    class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                >
                                    @foreach ($field['options'] ?? [] as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            @elseif ($type === 'textarea')
                                <textarea
                                    wire:model.defer="values.{{ $key }}"
                                    rows="3"
                                    class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                ></textarea>
                            @else
                                <input
                                    wire:model.defer="values.{{ $key }}"
                                    type="{{ $type }}"
                                    class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                >
                            @endif

                            @if (! empty($field['help']))
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $field['help'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-filament::actions>
                <x-filament::button type="submit">Save environment</x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="saveAndClearCache">
                    Save & clear cache
                </x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="clearConfigCache">
                    Clear config cache
                </x-filament::button>
            </x-filament::actions>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Changes update the .env file. Clear config cache if values do not apply.
            </p>
        </div>
    </form>
</x-filament-panels::page>
