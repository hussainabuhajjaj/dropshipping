<template>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sticky top-24">
        <h2 class="text-lg font-semibold text-slate-900 mb-4">{{ t('Payment Summary') }}</h2>

        <!-- Minimum Cart Requirement Alert -->
        <div v-if="summary.requirements && !summary.requirements.passes"
             class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
            <p class="text-xs text-amber-800">
                {{
                    summary.requirements.message || t('Add :amount more to meet minimum requirement', {
                        amount: summary.requirements.formatted_remaining
                    })
                }}
            </p>
        </div>

        <!-- Free Shipping Alert -->
        <div v-if="freeShippingEligible"
             class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-xs text-green-800 flex items-center gap-1">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ t('Congratulations! You qualify for free shipping!') }}
            </p>
        </div>

        <!-- Selected Method Summary -->
        <div v-if="selectedMethod" class="mb-4 p-3 bg-slate-50 rounded-lg">
            <p class="text-xs text-slate-500 mb-1">{{ t('Payment Method') }}</p>
            <div class="flex items-center gap-2">
                <span class="font-medium text-slate-900">{{ methodName }}</span>
            </div>
        </div>

        <!-- Amount Breakdown -->
        <div class="space-y-2 text-sm">
            <div v-for="item in convertedSummary.items.filter(i => i.type !== 'total')"
                 :key="item.id"
                 class="flex justify-between"
                 :class="{
                     'text-slate-600': item.type !== 'discount',
                     'text-green-600': item.type === 'discount'
                 }"
            >
                <span>{{ item.label }}</span>
                <span>{{ item.formatted }}</span>
            </div>

            <!-- Tax Note if included -->
            <div v-if="summary.tax.included" class="text-xs text-slate-500 italic">
                {{ t('Tax included in prices') }}
            </div>

            <div class="flex justify-between font-semibold text-slate-900 pt-2 border-t border-slate-200 mt-2">
                <span>{{ t('Total') }}</span>
                <span class="text-lg text-[#f59e0b]">{{ convertedSummary.formatted.total }}</span>
            </div>
        </div>

        <!-- Applied Promotions -->
        <div v-if="summary.discount.appliedPromotions.length > 0" class="mt-4 pt-4 border-t border-slate-200">
            <p class="text-xs font-semibold text-slate-500 mb-2">{{ t('Applied Promotions') }}</p>
            <div class="space-y-1">
                <div v-for="promo in summary.discount.appliedPromotions"
                     :key="promo.id"
                     class="text-xs text-green-600 flex items-center gap-1"
                >
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ promo.name || promo.code }}
                </div>
            </div>
        </div>

        <!-- Coupon Applied -->
        <div v-if="summary.discount.coupon" class="mt-2 text-xs text-green-600 flex items-center gap-1">
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
            </svg>
            {{ t('Coupon applied: :code', {code: summary.discount.coupon}) }}
        </div>

        <!-- Order Details -->
        <div class="mt-4 pt-4 border-t border-slate-200">
            <p class="text-xs text-slate-500 mb-2">{{ t('Order Details') }}</p>
            <ul class="space-y-1 text-xs text-slate-600">
                <li class="flex justify-between">
                    <span>{{ t('Items') }}</span>
                    <span>{{ itemCount }}</span>
                </li>
<!--                <li class="flex justify-between">-->
<!--                    <span>{{ t('Shipping Method') }}</span>-->
<!--                    <span>{{ summary.labels.shipping_method }}</span>-->
<!--                </li>-->
<!--                <li class="flex justify-between">-->
<!--                    <span>{{ t('Payment Status') }}</span>-->
<!--                    <span :class="paymentStatusClass">{{ paymentStatus }}</span>-->
<!--                </li>-->
                <li v-if="estimatedDelivery" class="flex justify-between">
                    <span>{{ t('Estimated Delivery') }}</span>
                    <span>{{ estimatedDelivery }}</span>
                </li>
            </ul>
        </div>



        <div class="space-y-4 pt-2 mt-5">
            <DeliveryTimeline compact/>
            <TrustBadges/>
        </div>

    </div>



</template>

<script setup>
import {computed} from 'vue'
import {useTranslations} from '@/i18n'
import {useUserPreferences} from '@/composables/useUserPreferences.js'
import {usePaymentSummary} from '@/composables/usePaymentSummary'
import TrustBadges from "@/Components/TrustBadges.vue";
import DeliveryTimeline from "@/Components/DeliveryTimeline.vue";

const { t } = useTranslations()
const { formatCurrency, convertCurrency, currentCurrency } = useUserPreferences()

const props = defineProps({
    summaryData: {
        type: Object,
        required: true
    },
    selectedMethod: {
        type: String,
        default: null
    },
    methodName: {
        type: String,
        default: ''
    },
    itemCount: {
        type: Number,
        default: 1
    },
    estimatedDelivery: {
        type: String,
        default: ''
    },
    paymentStatus: {
        type: String,
        default: 'Pending'
    }
})

// Use the payment summary composable
const {paymentSummary, checkFreeShippingEligibility} = usePaymentSummary(computed(() => props.summaryData))

const summary = computed(() => paymentSummary.value || {})

const freeShippingEligible = computed(() => {
    return checkFreeShippingEligibility(summary.value)
})

// Convert amounts to user's preferred currency
const convertedSummary = computed(() => {
    if (!summary.value?.items) return summary.value

    const convertedItems = summary.value.items.map(item => ({
        ...item,
        formatted: formatCurrency(
            convertCurrency(Number(item.amount || 0), 'USD', currentCurrency.value || 'USD'),
            currentCurrency.value || 'USD'
        )
    }))
    //
    return {
        ...summary.value,
        items: convertedItems,
        formatted: {
            ...summary.value.formatted,
            total: formatCurrency(
                convertCurrency(Number(summary.value.raw?.total || 0), 'USD', currentCurrency.value || 'USD'),
                currentCurrency.value || 'USD'
            )
        }
    }
})

const paymentStatusClass = computed(() => {
    return {
        'text-amber-600': props.paymentStatus.toLowerCase() === 'pending',
        'text-green-600': props.paymentStatus.toLowerCase() === 'completed',
        'text-rose-600': props.paymentStatus.toLowerCase() === 'failed',
        'text-blue-600': props.paymentStatus.toLowerCase() === 'processing'
    }
})
</script>
