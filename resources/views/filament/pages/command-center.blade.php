@php
    $params = $lastParameters ? json_encode($lastParameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;
    $exitColor = $lastExitCode === null ? 'gray' : ($lastExitCode === 0 ? 'success' : 'danger');
@endphp

<x-filament-panels::page>
    <div class="space-y-8">
        <x-filament::section
            heading="Last command output"
            description="Review the most recent command execution and output."
            icon="heroicon-o-command-line"
        >
            <div class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <x-filament::badge color="gray">Command: {{ $lastCommand ?? '--' }}</x-filament::badge>
                    <x-filament::badge :color="$exitColor">Exit: {{ $lastExitCode ?? '--' }}</x-filament::badge>
                    <x-filament::badge color="gray">Ran at: {{ $lastRanAt ?? '--' }}</x-filament::badge>
                </div>

                @if ($params)
                    <x-filament::fieldset label="Parameters">
                        <pre class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 overflow-auto dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ $params }}</pre>
                    </x-filament::fieldset>
                @endif

                @if ($lastOutput)
                    <x-filament::fieldset label="Output">
                        <pre class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 overflow-auto dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ $lastOutput }}</pre>
                    </x-filament::fieldset>
                @else
                    <p class="text-sm text-gray-500">No commands run yet.</p>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section
            heading="CJ tokens and settings"
            description="Manage access tokens and fetch CJ settings."
            icon="heroicon-o-key"
        >
            <x-filament::actions>
                <x-filament::button type="button" wire:click="runCjToken">
                    Get access token
                </x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="runCjSettings">
                    Fetch settings
                </x-filament::button>
                <x-filament::button type="button" color="danger" wire:click="runCjLogout">
                    Logout
                </x-filament::button>
            </x-filament::actions>
        </x-filament::section>

        <x-filament::section
            heading="CJ account"
            description="Store the CJ account name and email used for sync."
            icon="heroicon-o-user-circle"
        >
            <form wire:submit.prevent="runCjSetAccount" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Open name</label>
                        <x-filament::input
                            wire:model.defer="accountName"
                            type="text"
                            class="w-full"
                            placeholder="Brand display name"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Open email</label>
                        <x-filament::input
                            wire:model.defer="accountEmail"
                            type="email"
                            class="w-full"
                            placeholder="email@example.com"
                        />
                    </div>
                </div>
                <x-filament::actions>
                    <x-filament::button type="submit">Update account</x-filament::button>
                </x-filament::actions>
            </form>
        </x-filament::section>

        <x-filament::section
            heading="CJ product lookup"
            description="Fetch product details and variants by PID."
            icon="heroicon-o-magnifying-glass"
        >
            <div class="space-y-6">
                <form wire:submit.prevent="runCjProduct" class="space-y-3">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Product ID (pid)</label>
                        <x-filament::input
                            wire:model.defer="productPid"
                            type="text"
                            class="w-full"
                            placeholder="PID from CJ"
                            required
                        />
                    </div>
                    <x-filament::actions>
                        <x-filament::button type="submit">Fetch product</x-filament::button>
                    </x-filament::actions>
                </form>

                <form wire:submit.prevent="runCjVariants" class="space-y-3">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Product ID (pid)</label>
                        <x-filament::input
                            wire:model.defer="variantsPid"
                            type="text"
                            class="w-full"
                            placeholder="PID from CJ"
                            required
                        />
                    </div>
                    <x-filament::actions>
                        <x-filament::button type="submit" color="gray">Fetch variants</x-filament::button>
                    </x-filament::actions>
                </form>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="CJ stock"
            description="Check availability for a specific variant."
            icon="heroicon-o-archive-box"
        >
            <form wire:submit.prevent="runCjVariantStock" class="space-y-3">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Variant ID (vid)</label>
                    <x-filament::input
                        wire:model.defer="stockVid"
                        type="text"
                        class="w-full"
                        placeholder="Variant ID"
                        required
                    />
                </div>
                <x-filament::actions>
                    <x-filament::button type="submit">Fetch stock</x-filament::button>
                </x-filament::actions>
            </form>
        </x-filament::section>

        <x-filament::section
            heading="CJ catalog extras"
            description="Variant lookups, reviews, categories, and warehouses."
            icon="heroicon-o-rectangle-stack"
        >
            <div class="space-y-6">
                <form wire:submit.prevent="runCjVariantByVid" class="space-y-3">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Variant ID (vid)</label>
                        <x-filament::input
                            wire:model.defer="variantVid"
                            type="text"
                            class="w-full"
                            placeholder="Variant ID"
                            required
                        />
                    </div>
                    <x-filament::actions>
                        <x-filament::button type="submit">Fetch variant</x-filament::button>
                    </x-filament::actions>
                </form>

                <form wire:submit.prevent="runCjProductReviews" class="space-y-3">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Product ID (pid)</label>
                            <x-filament::input
                                wire:model.defer="reviewPid"
                                type="text"
                                class="w-full"
                                placeholder="PID from CJ"
                                required
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Page</label>
                            <x-filament::input
                                wire:model.defer="reviewPageNum"
                                type="number"
                                min="1"
                                class="w-full"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Page size</label>
                            <x-filament::input
                                wire:model.defer="reviewPageSize"
                                type="number"
                                min="1"
                                max="200"
                                class="w-full"
                            />
                        </div>
                    </div>
                    <x-filament::actions>
                        <x-filament::button type="submit" color="gray">Fetch reviews</x-filament::button>
                    </x-filament::actions>
                </form>

                <div class="grid gap-4 md:grid-cols-2">
                    <form wire:submit.prevent="runCjWarehouseDetail" class="space-y-3">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Warehouse ID</label>
                            <x-filament::input
                                wire:model.defer="warehouseId"
                                type="text"
                                class="w-full"
                                placeholder="Warehouse ID"
                                required
                            />
                        </div>
                        <x-filament::actions>
                            <x-filament::button type="submit">Warehouse detail</x-filament::button>
                        </x-filament::actions>
                    </form>

                    <div class="space-y-3">
                        <p class="text-sm text-gray-500">Catalog lists</p>
                        <x-filament::actions>
                            <x-filament::button type="button" wire:click="runCjCategories">
                                List categories
                            </x-filament::button>
                            <x-filament::button type="button" color="gray" wire:click="runCjGlobalWarehouses">
                                List global warehouses
                            </x-filament::button>
                        </x-filament::actions>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="CJ open API actions"
            description="Run signed CJ APIs for orders, disputes, logistics, and more."
            icon="heroicon-o-command-line"
        >
            <form wire:submit.prevent="runCjOpenApiAction" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-1 md:col-span-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Action</label>
                        <x-filament::input.select
                            wire:model.defer="openAction"
                            class="w-full"
                        >
                            <optgroup label="Products">
                                <option value="searchProducts">Search products</option>
                                <option value="productDetail">Product detail</option>
                            </optgroup>
                            <optgroup label="Orders">
                                <option value="listOrders">List orders</option>
                                <option value="getOrderDetail">Get order detail</option>
                                <option value="orderStatus">Order status</option>
                                <option value="orderDetail">Order detail (legacy)</option>
                                <option value="confirmOrder">Confirm order</option>
                                <option value="deleteOrder">Delete order</option>
                                <option value="changeWarehouse">Change warehouse</option>
                                <option value="addCart">Add cart</option>
                                <option value="addCartConfirm">Confirm cart</option>
                                <option value="saveGenerateParentOrder">Generate parent order</option>
                                <option value="createOrderV2">Create order v2</option>
                                <option value="createOrderV3">Create order v3</option>
                                <option value="createOrder">Create order (legacy)</option>
                                <option value="getBalance">Get balance</option>
                                <option value="payBalance">Pay balance</option>
                                <option value="payBalanceV2">Pay balance v2</option>
                                <option value="uploadWaybillInfo">Upload waybill info</option>
                                <option value="updateWaybillInfo">Update waybill info</option>
                            </optgroup>
                            <optgroup label="Logistics">
                                <option value="track">Track (legacy)</option>
                                <option value="trackInfo">Track info</option>
                                <option value="getTrackInfo">Track info (legacy)</option>
                                <option value="freightQuote">Freight quote</option>
                                <option value="freightCalculate">Freight calculate</option>
                                <option value="freightCalculateTip">Freight calculate tip</option>
                            </optgroup>
                            <optgroup label="Disputes">
                                <option value="disputeProducts">Dispute products</option>
                                <option value="disputeConfirmInfo">Dispute confirm info</option>
                                <option value="createDispute">Create dispute</option>
                                <option value="cancelDispute">Cancel dispute</option>
                                <option value="getDisputeList">Dispute list</option>
                            </optgroup>
                            <optgroup label="Other">
                                <option value="setWebhook">Set webhook</option>
                                <option value="warehouseDetail">Warehouse detail</option>
                            </optgroup>
                        </x-filament::input.select>
                        <p class="text-xs text-gray-500">
                            Actions marked “legacy” are kept for reference and may not be supported in the current flow.
                        </p>
                    </div>
                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Payload JSON</label>
                        <textarea
                            wire:model.defer="openPayload"
                            rows="6"
                            class="fi-input w-full"
                            placeholder='{"orderId":"123"}'
                        ></textarea>
                        <p class="text-xs text-gray-500">
                            Use JSON objects only. Use {} when no payload is needed. For file uploads, provide a file
                            path on disk in the payload.
                        </p>
                    </div>
                </div>
                <x-filament::actions>
                    <x-filament::button type="submit">Run action</x-filament::button>
                </x-filament::actions>
            </form>
        </x-filament::section>

        <x-filament::section
            heading="CJ sync snapshots"
            description="Sync CJ catalog data into local snapshots."
            icon="heroicon-o-arrow-path"
        >
            <form wire:submit.prevent="runCjSyncProducts" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Start page</label>
                        <x-filament::input
                            wire:model.defer="syncStartPage"
                            type="number"
                            min="1"
                            class="w-full"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Pages</label>
                        <x-filament::input
                            wire:model.defer="syncPages"
                            type="number"
                            min="1"
                            class="w-full"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Page size</label>
                        <x-filament::input
                            wire:model.defer="syncPageSize"
                            type="number"
                            min="1"
                            max="200"
                            class="w-full"
                        />
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <x-filament::input.checkbox
                            wire:model.defer="syncQueue"
                            id="syncQueue"
                        />
                        <label for="syncQueue" class="text-sm text-gray-700 dark:text-gray-200">Queue jobs</label>
                    </div>
                </div>
                <x-filament::actions>
                    <x-filament::button type="submit">Sync snapshots</x-filament::button>
                </x-filament::actions>
            </form>
        </x-filament::section>

        <x-filament::section
            heading="CJ sync my products"
            description="Refresh the CJ products mapped to this account."
            icon="heroicon-o-squares-2x2"
        >
            <form wire:submit.prevent="runCjSyncMyProducts" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Start page</label>
                        <x-filament::input
                            wire:model.defer="myStartPage"
                            type="number"
                            min="1"
                            class="w-full"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Page size</label>
                        <x-filament::input
                            wire:model.defer="myPageSize"
                            type="number"
                            min="1"
                            max="200"
                            class="w-full"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Max pages</label>
                        <x-filament::input
                            wire:model.defer="myMaxPages"
                            type="number"
                            min="1"
                            max="200"
                            class="w-full"
                        />
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <x-filament::input.checkbox
                            wire:model.defer="myForceUpdate"
                            id="myForceUpdate"
                        />
                        <label for="myForceUpdate" class="text-sm text-gray-700 dark:text-gray-200">Force update</label>
                    </div>
                </div>
                <x-filament::actions>
                    <x-filament::button type="submit">Sync my products</x-filament::button>
                </x-filament::actions>
            </form>
        </x-filament::section>

        <x-filament::section
            heading="Import snapshots"
            description="Pull a limited batch of CJ snapshots."
            icon="heroicon-o-arrow-down-tray"
        >
            <form wire:submit.prevent="runCjImportSnapshots" class="space-y-3">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Limit</label>
                        <x-filament::input
                            wire:model.defer="snapshotLimit"
                            type="number"
                            min="1"
                            max="2000"
                            class="w-full"
                        />
                    </div>
                </div>
                <x-filament::actions>
                    <x-filament::button type="submit">Import snapshots</x-filament::button>
                </x-filament::actions>
            </form>
        </x-filament::section>

        <x-filament::section
            heading="Reviews"
            description="Run housekeeping tasks on customer reviews."
            icon="heroicon-o-star"
        >
            <x-filament::actions>
                <x-filament::button type="button" wire:click="runReviewsAutoApprove">
                    Run auto-approve
                </x-filament::button>
            </x-filament::actions>
        </x-filament::section>

        <x-filament::section
            heading="Customer cleanup"
            description="Remove stale customer records safely."
            icon="heroicon-o-trash"
        >
            <form wire:submit.prevent="runCleanupCustomers" class="space-y-3">
                <div class="flex items-center gap-2">
                    <x-filament::input.checkbox
                        wire:model.defer="cleanupDryRun"
                        id="cleanupDryRun"
                    />
                    <label for="cleanupDryRun" class="text-sm text-gray-700 dark:text-gray-200">Dry run</label>
                </div>
                <x-filament::actions>
                    <x-filament::button type="submit" color="warning">Run cleanup</x-filament::button>
                </x-filament::actions>
            </form>
        </x-filament::section>

        <x-filament::section
            heading="Misc"
            description="Quick utilities and testing commands."
            icon="heroicon-o-sparkles"
        >
            <x-filament::actions>
                <x-filament::button type="button" color="gray" wire:click="runInspire">
                    Inspire
                </x-filament::button>
            </x-filament::actions>
        </x-filament::section>
    </div>
</x-filament-panels::page>
