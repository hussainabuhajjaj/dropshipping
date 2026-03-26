<template>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-xl font-semibold text-slate-900 mb-4">{{ t('Order Summary') }}</h2>

        <div class="space-y-4" v-if="type === 'cart'">
            <!-- Order Items -->
            <div v-for="item in processedItems" :key="item.id"
                 class="flex justify-between items-start pb-4 border-b border-slate-100 last:border-0">
                <div class="flex items-start gap-3 flex-1">
                    <!-- Product Image -->
                    <div class="w-16 h-16 rounded-lg overflow-hidden border border-slate-200 bg-slate-50 flex-shrink-0">
                        <img
                            v-if="item.image"
                            :src="item.image"
                            :alt="item.name"
                            class="w-full h-full object-cover"
                            @error="handleImageError"
                        >
                        <div v-else class="w-full h-full flex items-center justify-center bg-slate-100">
                            <svg class="h-6 w-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Product Details -->
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-900 break-words">{{ item.name }}</p>

                        <!-- Variant/SKU -->
                        <p v-if="item.variant || item.sku" class="text-xs text-slate-500 mt-1">
                            <span v-if="item.variant">{{ item.variant }}</span>
                            <span v-if="item.variant && item.sku"> • </span>
                            <span v-if="item.sku" class="font-mono">SKU: {{ item.sku }}</span>
                        </p>

                        <!-- Quantity and Price -->
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs text-slate-500">{{ t('Qty') }}: {{ item.quantity }}</span>
                            <span class="text-xs text-slate-300">•</span>
                            <span class="text-xs text-slate-500">{{ formatPrice(item.price) }} {{ t('each') }}</span>
                        </div>

                        <!-- Stock Warning -->
                        <p v-if="item.stock_on_hand !== undefined && item.stock_on_hand < 5"
                           class="text-xs text-amber-600 mt-1">
                            {{ t('Only :stock left in stock', {stock: item.stock_on_hand}) }}
                        </p>
                    </div>
                </div>

                <!-- Item Total -->
                <div class="text-right flex-shrink-0 ml-4">
                    <span class="font-semibold text-slate-900 whitespace-nowrap">
                        {{ formatPrice(item.price * item.quantity) }}
                    </span>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import {computed} from 'vue'
import {useTranslations} from '@/i18n'
import {useUserPreferences} from '@/composables/useUserPreferences.js'

const { t } = useTranslations()
const { formatCurrency, convertCurrency, currentCurrency } = useUserPreferences()

const props = defineProps({
    // Can be either cart items array or summary object
    type: {
        type: String,
        required: false
    },
    items: {
        type: [Array, Object],
        required: true
    },
    // Individual props (used when items is summary object)
    shipping: {
        type: [Number,String],
        default: 0
    },
    tax: {
        type: [Number,String],
        default: 0
    },
    discount: {
        type: [Number,String],
        default: 0
    },
    discountLabel: {
        type: String,
        default: 'Discount'
    },
    taxLabel: {
        type: String,
        default: 'Tax'
    },
    taxIncluded: {
        type: Boolean,
        default: false
    },
    currency: {
        type: String,
        default: 'XOF'
    }
})

const emit = defineEmits(['update'])

// Process items based on type
const processedItems = computed(() => {
    // If items is an array, it's cart items
    if (Array.isArray(props.items)) {
        return props.items.map(item => ({
            id: item.id,
            name: item.name,
            variant: item.variant,
            price: item.price,
            quantity: item.quantity,
            sku: item.sku,
            stock_on_hand: item.stock_on_hand,
            // Handle media array
            image: getItemImage(item)
        }))
    }

    // If items is an object (summary), return empty array (handled by parent)
    return []
})

// Helper to get image from media array
const getItemImage = (item) => {
    if (item.image) return item.image
    if (item.media && Array.isArray(item.media) && item.media.length > 0) {
        // If media is array of strings
        if (typeof item.media[0] === 'string') {
            return item.media[0]
        }
        // If media is array of objects with url/path
        if (item.media[0]?.url) {
            return item.media[0].url
        }
        if (item.media[0]?.path) {
            return `/storage/${item.media[0].path}`
        }
    }
    return null
}

// Calculate subtotal from items
const subtotal = computed(() => {
    if (Array.isArray(props.items)) {
        return props.items.reduce((sum, item) => sum + (item.price * item.quantity), 0)
    }
    // If items is not an array, use props.subtotal from parent
    return props.items?.subtotal || 0
})

// Calculate total
const total = computed(() => {
    if (Array.isArray(props.items)) {
        return subtotal.value + Number(props.shipping) + props.tax - props.discount
    }
    // If items is summary object, use its total
    return props.items?.total || (subtotal.value + Number(props.shipping) + props.tax - props.discount)
})

// Format price with proper currency conversion
const formatPrice = (amount) => {
    if (amount === null || amount === undefined) return ''

    // Convert from USD to user's preferred currency, then format
    const convertedAmount = convertCurrency(Number(amount || 0), 'USD', currentCurrency.value || 'USD')
    return formatCurrency(convertedAmount, currentCurrency.value || 'USD')
}

// Handle image load error
const handleImageError = (event) => {
    event.target.src = '' // Clear broken image
    event.target.classList.add('hidden') // Hide img
    // Show fallback icon
    const parent = event.target.parentElement
    const fallback = document.createElement('div')
    fallback.className = 'w-full h-full flex items-center justify-center bg-slate-100'
    fallback.innerHTML = `<svg class="h-6 w-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
    </svg>`
    parent.appendChild(fallback)
}
</script>
