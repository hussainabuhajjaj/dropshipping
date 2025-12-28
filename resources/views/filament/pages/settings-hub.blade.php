<x-filament-panels::page>
    @foreach ($this->getSettingsGroups() as $groupKey => $group)
        <x-filament::section
            :heading="$group['label']"
            :icon="$group['icon']"
        >
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($group['items'] as $item)
                    <x-filament::link 
                        :href="$item['url']" 
                        size="lg" 
                        weight="semibold" 
                        color="gray" 
                        class="fi-settings-hub-link"
                    >
                        <div class="fi-settings-hub-link__inner">
                            <div class="fi-settings-hub-link__icon">
                                <x-filament::icon :icon="$item['icon']" class="fi-settings-hub-link__icon-svg" />
                            </div>

                            <div class="fi-settings-hub-link__content">
                                <div class="fi-settings-hub-link__title">
                                    {{ $item['label'] }}
                                </div>
                                <div class="fi-settings-hub-link__desc">
                                    {{ $item['description'] }}
                                </div>
                            </div>
                        </div>
                    </x-filament::link>
                @endforeach
            </div>
        </x-filament::section>
    @endforeach

    <x-filament::section
        heading="Getting Started"
        description="Follow these steps for a smooth store setup."
        icon="heroicon-o-light-bulb"
        collapsible
        collapsed
    >
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600 dark:text-gray-300">
            <li>Upload your logo and favicon under <strong>Store Details</strong></li>
            <li>Configure payment provider credentials under <strong>Payments</strong></li>
            <li>Define shipping zones and rates under <strong>Shipping & Delivery</strong></li>
            <li>Set tax label/rate and whether prices include tax</li>
            <li>Fill in legal policies: refund, privacy, terms</li>
            <li>Connect your fulfillment provider under <strong>Locations</strong></li>
        </ol>
    </x-filament::section>
</x-filament-panels::page>
