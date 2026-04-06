<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-6">
        <h2 class="mb-4 text-lg font-semibold text-slate-900 sm:text-xl">{{ t('Order Summary') }}</h2>

        <div class="space-y-4" v-if="type === 'cart'">
            <!-- Order Items -->
            <div v-for="item in processedItems" :key="item.id"
                 class="border-b border-slate-100 pb-4 last:border-0">
                <div class="flex items-start gap-3">
                    <!-- Product Image -->
                    <div class="h-14 w-14 flex-shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-50 sm:h-16 sm:w-16">
                        <img
                            v-if="item.image"
                            :src="item.image"
                            :alt="item.name"
                            class="h-full w-full object-cover"
                            @error="handleImageError"
                        >
                        <div v-else class="flex h-full w-full items-center justify-center bg-slate-100">
                            <svg class="h-6 w-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Product Details -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <p class="line-clamp-2 text-sm font-medium leading-5 text-slate-900 sm:text-[0.95rem]">
                                {{ item.name }}
                            </p>

                            <!-- Item Total -->
                            <div class="ml-auto flex-shrink-0 text-right">
                                <span class="whitespace-nowrap text-sm font-semibold text-slate-900 sm:text-base">
                                    {{ formatPrice(item.price * item.quantity) }}
                                </span>
                            </div>
                        </div>

                        <!-- Variant/SKU -->
                        <p v-if="item.variant || item.sku" class="mt-1 text-[11px] text-slate-500 sm:text-xs">
                            <span v-if="item.variant">{{ item.variant }}</span>
                            <span v-if="item.variant && item.sku"> • </span>
                            <span v-if="item.sku" class="font-mono">SKU: {{ item.sku }}</span>
                        </p>

                        <!-- Quantity and Price -->
                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                {{ t('Qty') }}: {{ item.quantity }}
                            </span>
                            <span class="text-[11px] text-slate-500 sm:text-xs">{{ formatPrice(item.price) }} {{ t('each') }}</span>
                        </div>

                        <!-- Stock Warning -->
                        <p v-if="item.stock_on_hand !== undefined && item.stock_on_hand < 5"
                           class="mt-1 text-[11px] text-amber-600 sm:text-xs">
                            {{ t('Only :stock left in stock', {stock: item.stock_on_hand}) }}
                        </p>
                    </div>
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

// Format price with proper currency
const formatPrice = (amount) => {
    if (amount === null || amount === undefined) return ''

    // Display the amount directly in the order/cart currency (no conversion needed)
    return formatCurrency(Number(amount || 0), props.currency || currentCurrency.value)
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
