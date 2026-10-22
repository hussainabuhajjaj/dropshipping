<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Welcome back, {{ $affiliate_name ?? 'Affiliate' }}!</h2>
            <p class="text-gray-600">Here’s your affiliate dashboard overview.</p>
        </div>

        <div class="grid gap-4 grid-cols-1 sm:grid-cols-3">
            <div class="bg-white rounded-lg shadow p-5 border border-gray-100">
                <p class="text-sm uppercase tracking-wide text-gray-500">Available balance</p>
                <p class="text-3xl font-semibold text-emerald-600">${{ number_format($available_balance ?? 0, 2) }}</p>
                <p class="text-sm text-gray-500">Ready to withdraw</p>
            </div>
            <div class="bg-white rounded-lg shadow p-5 border border-gray-100">
                <p class="text-sm uppercase tracking-wide text-gray-500">Pending balance</p>
                <p class="text-3xl font-semibold text-yellow-600">${{ number_format($pending_balance ?? 0, 2) }}</p>
                <p class="text-sm text-gray-500">Waiting for approval</p>
            </div>
            <div class="bg-white rounded-lg shadow p-5 border border-gray-100">
                <p class="text-sm uppercase tracking-wide text-gray-500">Total earned</p>
                <p class="text-3xl font-semibold text-indigo-600">${{ number_format($total_earned ?? 0, 2) }}</p>
                <p class="text-sm text-gray-500">All time revenue</p>
            </div>
        </div>

        @if(isset($stats))
            <div class="bg-white rounded-lg shadow overflow-hidden border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Performance snapshot</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 px-6 py-5">
                    <div>
                        <p class="text-xs uppercase text-gray-500">Total commissions</p>
                        <p class="text-2xl font-semibold text-indigo-600">${{ number_format($stats['total_commissions'] ?? 0, 2) }}</p>
                        <p class="text-sm text-gray-500">
                            {{ $stats['pending_commissions'] ?? 0 }} pending •
                            {{ $stats['approved_commissions'] ?? 0 }} approved
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Withdrawals</p>
                        <p class="text-2xl font-semibold text-emerald-600">${{ number_format($stats['total_withdrawn'] ?? 0, 2) }}</p>
                        <p class="text-sm text-gray-500">
                            {{ $stats['pending_withdrawals'] ?? 0 }} pending
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Referrals</p>
                        <p class="text-2xl font-semibold text-amber-600">{{ $stats['referral_count'] ?? 0 }}</p>
                        <p class="text-sm text-gray-500">
                            {{ $stats['converted_referrals'] ?? 0 }} converted
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div class="bg-white rounded-lg shadow p-5 border border-gray-100 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Referral link</h3>
                <p class="text-sm text-gray-500">
                    Share this link and earn on every qualifying order.
                </p>
                <div class="space-y-2">
                    <input
                        type="text"
                        value="{{ $referral_link }}"
                        readonly
                        class="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-sm"
                        x-data
                        x-on:click="$el.select()"
                    >
                    <button
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition"
                        type="button"
                        x-on:click="navigator.clipboard.writeText('{{ $referral_link }}'); $el.textContent = 'Copied'; setTimeout(() => $el.textContent = 'Copy', 1500);"
                    >
                        Copy link
                    </button>
                </div>
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <span>Referral code</span>
                    <span class="font-mono">{{ $referral_code }}</span>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-5 border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Recent referrals</h3>
                <ul class="space-y-2 mt-4 text-sm text-gray-700">
                    @if(isset($referral_details) && $referral_details->isNotEmpty())
                        @foreach($referral_details as $referral)
                            <li class="flex justify-between">
                                <span>{{ $referral->visitor_token }}</span>
                                <span class="text-xs text-emerald-600 uppercase">{{ $referral->user_id ? 'converted' : 'active' }}</span>
                            </li>
                        @endforeach
                    @else
                        <li>No referrals yet</li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div class="bg-white rounded-lg shadow p-5 border border-gray-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Recent commissions</h3>
                    <span class="text-xs uppercase text-gray-500">Last {{ $recent_commissions?->count() ?: 0 }}</span>
                </div>
                <ul class="mt-4 space-y-3 text-sm text-gray-700">
                    @if(isset($recent_commissions) && $recent_commissions->isNotEmpty())
                        @foreach($recent_commissions as $commission)
                            <li class="border border-gray-100 rounded-lg p-3">
                                <div class="flex justify-between">
                                    <span class="font-medium">Order {{ $commission->order_id }}</span>
                                    <span class="text-emerald-600 font-semibold">${{ number_format($commission->commission_amount, 2) }}</span>
                                </div>
                                <p class="text-xs text-gray-500">
                                    {{ ucfirst($commission->status) }}
                                    • {{ $commission->created_at->diffForHumans() }}
                                </p>
                            </li>
                        @endforeach
                    @else
                        <li class="text-gray-500">No commissions yet</li>
                    @endif
                </ul>
            </div>

            <div class="bg-white rounded-lg shadow p-5 border border-gray-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Recent withdrawals</h3>
                    <span class="text-xs uppercase text-gray-500">Last {{ $recent_withdrawals?->count() ?: 0 }}</span>
                </div>
                <ul class="mt-4 space-y-3 text-sm text-gray-700">
                    @if(isset($recent_withdrawals) && $recent_withdrawals->isNotEmpty())
                        @foreach($recent_withdrawals as $withdrawal)
                            <li class="border border-gray-100 rounded-lg p-3">
                                <div class="flex justify-between">
                                    <span class="font-medium">${{ number_format($withdrawal->amount, 2) }}</span>
                                    <span class="text-xs uppercase text-gray-500">{{ $withdrawal->status }}</span>
                                </div>
                                <p class="text-xs text-gray-500">
                                    Requested {{ $withdrawal->created_at->diffForHumans() }}
                                </p>
                            </li>
                        @endforeach
                    @else
                        <li class="text-gray-500">No withdrawals requested yet</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</x-filament-panels::page>
