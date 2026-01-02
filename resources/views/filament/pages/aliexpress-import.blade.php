<div class="fi-page">
    <div class="fi-page-header space-y-6 px-6 py-8">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                AliExpress Integration
            </h1>
        </div>
    </div>

    <div class="fi-page-content space-y-6 px-6 pb-8">
        <!-- Status Card -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Authentication Status</h2>
            @if($this->getToken())
                @php
                    $token = $this->getToken();
                    $isExpired = $token->isExpired();
                    $canRefresh = $token->canRefresh();
                @endphp
                <div class="rounded-md @if($isExpired) bg-red-50 dark:bg-red-900/20 @else bg-green-50 dark:bg-green-900/20 @endif p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm @if($isExpired) text-red-800 dark:text-red-200 @else text-green-800 dark:text-green-200 @endif">
                                @if($isExpired)
                                    ✗ Token Expired
                                @else
                                    ✓ Connected to AliExpress
                                @endif
                            </p>
                            <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                @if($token->expires_at)
                                    Expires: {{ $token->expires_at->format('Y-m-d H:i:s') }} ({{ $token->expires_at->diffForHumans() }})
                                @else
                                    Expiration time: Not set
                                @endif
                            </p>
                            @if($canRefresh && $isExpired)
                                <p class="mt-1 text-xs text-blue-600 dark:text-blue-400">
                                    ℹ️ You can refresh your token
                                </p>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            @if($isExpired)
                                @if($canRefresh)
                                    <button
                                        wire:click="refreshToken"
                                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700"
                                    >
                                        Refresh Token
                                    </button>
                                @else
                                    <button
                                        wire:click="authenticateWithAliExpress"
                                        class="rounded-lg bg-red-600 px-3 py-2 text-sm text-white hover:bg-red-700"
                                    >
                                        Re-authenticate
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-md bg-yellow-50 p-4 dark:bg-yellow-900/20">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        ⚠ Not authenticated with AliExpress
                    </p>
                </div>
                <button
                    wire:click="authenticateWithAliExpress"
                    class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                    Authenticate with AliExpress
                </button>
            @endif
        </div>

        <!-- Import Options -->
        @if($this->getToken() && !$this->getToken()->isExpired())
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Sync Categories -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">📂 Sync Categories</h3>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        Import all product categories from AliExpress to organize your catalog
                    </p>
                    <button
                        wire:click="syncCategories"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        Sync Categories
                    </button>
                </div>

                <!-- Import Products -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">🛍️ Import Products</h3>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        Search and import products from AliExpress (up to 20 at a time)
                    </p>
                    <button
                        wire:click="importProducts"
                        class="w-full rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700 disabled:opacity-50"
                    >
                        Import Products
                    </button>
                </div>
            </div>
        @elseif($this->getToken() && $this->getToken()->isExpired())
            <div class="rounded-lg border border-red-200 bg-red-50 p-6 dark:border-red-900/30 dark:bg-red-900/20">
                <p class="text-sm text-red-800 dark:text-red-200">
                    ⚠️ Import options are disabled because your token has expired. Please refresh or re-authenticate.
                </p>
            </div>
        @endif

        <!-- Info Section -->
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-6 dark:border-blue-900/30 dark:bg-blue-900/20">
            <h3 class="mb-3 font-semibold text-blue-900 dark:text-blue-200">ℹ️ About AliExpress Integration</h3>
            <ul class="space-y-2 text-sm text-blue-800 dark:text-blue-300">
                <li>• <strong>Sync Categories:</strong> Pulls all AliExpress product categories into your system</li>
                <li>• <strong>Import Products:</strong> Currently searches for "electronics" - customize in Filament page code</li>
                <li>• <strong>Token Refresh:</strong> Automatically refreshed when expired, or manually refresh above</li>
                <li>• <strong>Logs:</strong> Check <code class="bg-blue-100 px-2 py-1 dark:bg-blue-900">storage/logs/laravel.log</code> for details</li>
            </ul>
        </div>

        <!-- Testing Command -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">🧪 Test Integration (Terminal)</h3>
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                Run these commands in your terminal to test the integration:
            </p>
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
        </div>
    </div>
</div>
