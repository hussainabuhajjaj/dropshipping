<x-filament::page>
    <x-filament::section label="AliExpress Integration">
        <x-filament::card>
            <x-slot name="header">
                <span class="font-semibold">Authentication Status</span>
            </x-slot>
            @if($this->getToken())
                @php
                    $token = $this->getToken();
                    $isExpired = $token->isExpired();
                    $canRefresh = $token->canRefresh();
                @endphp
                <div class="rounded-md p-4 mb-2 @if($isExpired) bg-red-50 dark:bg-red-900/20 @else bg-green-50 dark:bg-green-900/20 @endif">
                    <div class="font-semibold @if($isExpired) text-red-800 dark:text-red-200 @else text-green-800 dark:text-green-200 @endif">
                        @if($isExpired)
                            ✗ Token Expired
                        @else
                            ✓ Connected to AliExpress
                        @endif
                    </div>
                    <div class="text-xs mt-1">
                        @if($token->expires_at)
                            Expires: {{ $token->expires_at->format('Y-m-d H:i:s') }} ({{ $token->expires_at->diffForHumans() }})
                        @else
                            Expiration time: Not set
                        @endif
                    </div>
                    @if($canRefresh && $isExpired)
                        <div class="mt-1 text-xs text-blue-600 dark:text-blue-400">
                            ℹ️ You can refresh your token
                        </div>
                    @endif
                    <div class="mt-4 flex gap-2">
                        @if($isExpired)
                            @if($canRefresh)
                                <x-filament::button color="primary" wire:click="refreshToken">Refresh Token</x-filament::button>
                            @else
                                <x-filament::button color="danger" wire:click="authenticateWithAliExpress">Re-authenticate</x-filament::button>
                            @endif
                        @endif
                    </div>
                </div>
            @else
                <div class="rounded-md bg-yellow-50 p-4 dark:bg-yellow-900/20">
                    <span class="font-semibold text-yellow-800 dark:text-yellow-200">⚠ Not authenticated with AliExpress</span>
                </div>
                <div class="mt-4">
                    <x-filament::button color="primary" wire:click="authenticateWithAliExpress">Authenticate with AliExpress</x-filament::button>
                </div>
            @endif
        </x-filament::card>

        @if($this->getToken() && !$this->getToken()->isExpired())
            <div class="grid gap-6 md:grid-cols-2">
                <x-filament::card>
                    <x-slot name="header">📂 Sync Categories</x-slot>
                    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        Import all product categories from AliExpress to organize your catalog
                    </div>
                    <x-filament::button color="info" wire:click="syncCategories" class="w-full">Sync Categories</x-filament::button>
                </x-filament::card>
                <x-filament::card>
                    <x-slot name="header">🛍️ Import Products</x-slot>
                    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        Search and import products from AliExpress (up to 20 at a time)
                    </div>
                    <x-filament::button color="success" wire:click="importProducts" class="w-full">Import Products</x-filament::button>
                </x-filament::card>
            </div>
        @elseif($this->getToken() && $this->getToken()->isExpired())
            <div class="rounded-md bg-red-50 p-4 dark:bg-red-900/20">
                <span class="font-semibold text-red-800 dark:text-red-200">⚠️ Import options are disabled because your token has expired. Please refresh or re-authenticate.</span>
            </div>
        @endif

        <x-filament::section label="ℹ️ About AliExpress Integration">
            <ul class="space-y-2 text-sm text-blue-800 dark:text-blue-300">
                <li>• <strong>Sync Categories:</strong> Pulls all AliExpress product categories into your system</li>
                <li>• <strong>Import Products:</strong> Currently searches for "electronics" - customize in Filament page code</li>
                <li>• <strong>Token Refresh:</strong> Automatically refreshed when expired, or manually refresh above</li>
                <li>• <strong>Logs:</strong> Check <code class="bg-blue-100 px-2 py-1 dark:bg-blue-900">storage/logs/laravel.log</code> for details</li>
            </ul>
        </x-filament::section>

        <x-filament::card>
            <x-slot name="header">🧪 Test Integration (Terminal)</x-slot>
            <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                Run these commands in your terminal to test the integration:
            </div>
            <div class="space-y-2 bg-gray-100 p-4 font-mono text-xs dark:bg-gray-900">
                <div class="flex items-center justify-between text-gray-800 dark:text-gray-200">
                    <code>php artisan aliexpress:test --action=token</code>
                    <span class="text-gray-500">Check token status</span>
                </div>
                <div class="flex items-center justify-between text-gray-800 dark:text-gray-200">
                    <code>php artisan aliexpress:test --action=categories</code>
                    <span class="text-gray-500">Test category sync</span>
                </div>
                <div class="flex items-center justify-between text-gray-800 dark:text-gray-200">
                    <code>php artisan aliexpress:test --action=products</code>
                    <span class="text-gray-500">Test product import</span>
                </div>
                <div class="flex items-center justify-between text-gray-800 dark:text-gray-200">
                    <code>php artisan aliexpress:test --action=full</code>
                    <span class="text-gray-500">Run all tests</span>
                </div>
            </div>
        </x-filament::card>
    </x-filament::section>
</x-filament::page>
