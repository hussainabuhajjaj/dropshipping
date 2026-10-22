<template>
    <div class="bg-rose-50 border border-rose-200 rounded-2xl p-6">
        <div class="flex items-center gap-3 text-rose-600 mb-4">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="font-semibold">{{ t('Payment Error') }}</h3>
        </div>

        <p class="text-sm text-slate-600 mb-4">
            {{ errorMessage }}
        </p>

        <div class="bg-white rounded-lg p-3 text-xs font-mono mb-3">
            <p class="break-all">{{ checkoutId }}</p>
        </div>

        <div class="text-xs text-slate-500 space-y-1">
            <p><span class="font-semibold">ndc</span></p>
            <p>timestamp: {{ timestamp }}</p>
        </div>

        <button
            @click="$emit('contact-support')"
            class="w-full mt-4 bg-rose-600 text-white py-2 px-4 rounded-lg text-sm font-semibold hover:bg-rose-700 transition"
        >
            {{ t('Contact Support') }}
        </button>
    </div>
</template>

<script setup>
defineProps({
    errorMessage: {
        type: String,
        required: true
    },
    checkoutId: {
        type: String,
        required: true
    },
    timestamp: {
        type: String,
        required: true
    }
})

defineEmits(['contact-support'])

// Translation function
const t = (key) => {
    const translations = {
        'Payment Error': 'Payment Error',
        'Contact Support': 'Contact Support'
    }
    return translations[key] || key
}
</script>
