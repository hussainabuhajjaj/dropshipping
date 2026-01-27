<x-filament::page>
    <div class="space-y-6">

        <x-filament::section>
            <x-slot name="heading">Authentication</x-slot>

            @php
                $token = $this->getToken();
                $isExpired = $token?->isExpired() ?? true;
                $canRefresh = $token?->canRefresh() ?? false;
            @endphp

            @if($token)
                <x-filament::card>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="font-semibold">
                                @if($isExpired)
                                    <span class="text-danger-600">✗ Token Expired</span>
                                @else
                                    <span class="text-success-600">✓ Connected to AliExpress</span>
                                @endif
                            </div>

                            <div class="text-xs text-gray-500 mt-1">
                                @if($token->expires_at)
                                    Expires: {{ $token->expires_at->format('Y-m-d H:i:s') }} ({{ $token->expires_at->diffForHumans() }})
                                @else
                                    Expiration time: Not set
                                @endif
                            </div>

                            @if($canRefresh && $isExpired)
                                <div class="mt-1 text-xs text-primary-600">
                                    ℹ️ You can refresh your token
                                </div>
                            @endif
                        </div>

                        <div class="flex gap-2 flex-wrap">
                            @if($isExpired)
                                @if($canRefresh)
                                    <x-filament::button color="primary" wire:click="refreshToken">
                                        Refresh Token
                                    </x-filament::button>
                                @else
                                    <x-filament::button color="danger" wire:click="authenticateWithAliExpress">
                                        Re-authenticate
                                    </x-filament::button>
                                @endif
                            @else
                                <x-filament::badge color="success">Token active</x-filament::badge>
                            @endif
                        </div>
                    </div>
                </x-filament::card>
            @else
                <x-filament::card>
                    <div class="flex items-center justify-between gap-4">
                        <div class="font-semibold text-warning-700">
                            ⚠ Not authenticated with AliExpress
                        </div>

                        <x-filament::button color="primary" wire:click="authenticateWithAliExpress">
                            Authenticate
                        </x-filament::button>
                    </div>
                </x-filament::card>
            @endif
        </x-filament::section>

        @if($token && ! $isExpired)

            <x-filament::section>
                <x-slot name="heading">Actions</x-slot>

                <div class="flex flex-wrap gap-3">
                    <x-filament::button color="info" wire:click="syncCategories" icon="heroicon-o-arrow-path">
                        Sync Categories
                    </x-filament::button>

                    <x-filament::button color="primary" wire:click="searchProducts" icon="heroicon-o-magnifying-glass">
                        Preview Products
                    </x-filament::button>

                    <x-filament::button
                        color="success"
                        wire:click="importSelectedProducts"
                        icon="heroicon-o-arrow-down-tray"
                        :disabled="empty($selectedProductIds)"
                    >
                        Import Selected ({{ is_countable($selectedProductIds) ? count($selectedProductIds) : 0 }})
                    </x-filament::button>

                    @if($previewed)
                        <x-filament::button
                            color="gray"
                            wire:click="$set('previewed', false)"
                            icon="heroicon-o-x-mark"
                        >
                            Clear Preview
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Filters</x-slot>

                <x-filament::card>
                    {{ $this->form }}
                </x-filament::card>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    Preview Results
                    @if($previewed)
                        <span class="text-xs text-gray-500">
                            ({{ is_countable($searchResults) ? count($searchResults) : 0 }} items)
                        </span>
                    @endif
                </x-slot>

                <x-filament::card>
                    @if($previewed && is_countable($searchResults) && count($searchResults))
                        {{ $this->table }}
                    @elseif($previewed)
                        <div class="text-sm text-gray-600">
                            No results. Adjust filters and preview again.
                        </div>
                    @else
                        <div class="text-sm text-gray-600">
                            Click “Preview Products” to load results into the table.
                        </div>
                    @endif
                </x-filament::card>
            </x-filament::section>

        @elseif($token && $isExpired)
            <x-filament::section>
                <x-slot name="heading">Token Expired</x-slot>
                <x-filament::card>
                    <div class="text-sm text-danger-700">
                        Your AliExpress token has expired. Refresh or re-authenticate to continue.
                    </div>
                </x-filament::card>
            </x-filament::section>
        @endif

    </div>
</x-filament::page>
