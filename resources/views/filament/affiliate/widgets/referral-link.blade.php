<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-semibold mb-4">Your Referral Link</h3>
    
    <div class="space-y-4">
        <div>
            <p class="text-sm text-gray-600 mb-2">Share this link with your audience:</p>
            <div class="flex items-center space-x-2">
                <input 
                    type="text" 
                    value="{{ $referralUrl }}" 
                    readonly 
                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50"
                    x-data
                    x-init="$el.select()"
                >
                <button 
                    @click="navigator.clipboard.writeText('{{ $referralUrl }}'); $el.textContent = 'Copied!'; setTimeout(() => $el.textContent = 'Copy', 2000)"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                >
                    Copy
                </button>
            </div>
        </div>
        
        <div>
            <p class="text-sm text-gray-600 mb-2">Your referral code:</p>
            <div class="flex items-center space-x-2">
                <span class="px-3 py-2 bg-gray-100 rounded-md font-mono text-lg">{{ $affiliate->referral_code }}</span>
                <button 
                    @click="navigator.clipboard.writeText('{{ $affiliate->referral_code }}'); $el.textContent = 'Copied!'; setTimeout(() => $el.textContent = 'Copy', 2000)"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                >
                    Copy
                </button>
            </div>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
            <h4 class="font-medium text-blue-900 mb-2">How it works:</h4>
            <ul class="text-sm text-blue-800 space-y-1">
                <li>• Share your referral link or code</li>
                <li>• People click and make purchases</li>
                <li>• You earn commissions on qualifying sales</li>
                <li>• Track your earnings in this dashboard</li>
            </ul>
        </div>
    </div>
</div>
