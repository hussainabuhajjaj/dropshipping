<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-semibold mb-4">Commission Details</h3>
    
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm text-gray-600">Order ID</p>
            <p class="font-medium">#{{ $commission->order_id }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-600">Commission Amount</p>
            <p class="font-medium text-green-600">${{ number_format($commission->commission_amount, 2) }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-600">Commission Rate</p>
            <p class="font-medium">{{ $commission->commission_rate * 100 }}%</p>
        </div>
        <div>
            <p class="text-sm text-gray-600">Status</p>
            <p class="font-medium">
                <span class="px-2 py-1 rounded-full text-xs font-semibold
                    @if($commission->status === 'paid') bg-green-100 text-green-800
                    @elseif($commission->status === 'approved') bg-blue-100 text-blue-800
                    @elseif($commission->status === 'pending') bg-yellow-100 text-yellow-800
                    @else bg-red-100 text-red-800
                    @endif">
                    {{ ucfirst($commission->status) }}
                </span>
            </p>
        </div>
        @if($commission->coupon_code)
        <div class="col-span-2">
            <p class="text-sm text-gray-600">Coupon Used</p>
            <p class="font-medium">{{ $commission->coupon_code }}</p>
        </div>
        @endif
        <div class="col-span-2">
            <p class="text-sm text-gray-600">Date Earned</p>
            <p class="font-medium">{{ $commission->created_at->format('F j, Y g:i A') }}</p>
        </div>
    </div>
</div>
