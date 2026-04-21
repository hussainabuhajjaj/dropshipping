<template>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-xl font-semibold text-slate-900 mb-4">{{ t('Payment Methods') }}</h2>

        <!-- Payment Method Selection -->
        <div class="space-y-3 mb-6">
            <div
                v-for="method in availableMethods"
                :key="method.id"
                @click="selectedMethod = method.id"
                class="border rounded-xl cursor-pointer transition hover:border-[#f59e0b]"
                :class="{'border-[#f59e0b] bg-amber-50/50': selectedMethod === method.id}"
            >
                <div class="flex items-center p-4">
                    <input
                        type="radio"
                        :value="method.id"
                        v-model="selectedMethod"
                        class="w-4 h-4 text-[#f59e0b]"
                    >
                    <div class="ml-3 flex items-center gap-3 flex-1">
                        <component :is="method.icon" v-if="method.icon" class="h-6 w-6"/>

                        <span class="font-medium">{{ method.name }}</span>

                        <span class="ml-auto text-xs text-slate-500">
                            {{ method.description }}
                        </span>
                    </div>
                </div>

                <div
                    v-if="method.id === 'mobile_money' && selectedMethod === 'mobile_money'"
                    class="border-t border-amber-200 px-4 pb-4 pt-3"
                    @click.stop
                >
                    <slot name="mobile-money-details" />
                </div>
            </div>
        </div>

        <!-- Pay with Paystack Button -->
        <button
            @click="processPayment"
            class="w-full bg-[#00C3F2] text-white py-4 px-6 rounded-xl font-semibold text-lg hover:bg-[#00A8D4] transition disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="!selectedMethod || is_processing"
        >
            <span v-if="is_processing" class="flex items-center justify-center gap-2">
                <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"
                            fill="none"/>
                    <path class="opacity-75" fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                </svg>
                {{ t('Processing...') }}
            </span>
            <span v-else>{{ t('Pay Now with Paystack') }} • {{ formattedAmount }}</span>
        </button>

        <!-- Secure Payment Notice -->
        <p class="text-xs text-center text-slate-500 mt-4">
            <svg class="inline-block h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a3 3 0 003-3v-6a3 3 0 00-3-3H6a3 3 0 00-3 3v6a3 3 0 003 3zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            {{ t('Secure payment powered by Paystack.') }}
        </p>

        <!-- Payment Icons -->

        <div class="flex justify-center gap-4 mt-4">

            <PaymentBadges
                class="mt-3"
                :show-card="false"
                :show-stripe="false"
                :show-mobile-money="true"
            />
        </div>
    </div>
</template>

<script setup>
import {ref, computed, watch} from 'vue'
import {useTranslations} from '@/i18n'
import PaymentBadges from "@/Components/PaymentBadges.vue";

const { t } = useTranslations()

const props = defineProps({
    amount: {
        type: Number,
        required: true
    },
    formattedAmount: {
        type: String,
        required: true
    },
    currency: {
        type: String,
        default: 'XOF'
    },
    is_processing: {
        type: Boolean,
        default: false,
    },
    initialMethod: {
        type: String,
        default: 'card',
    },
})

const emit = defineEmits(['method-change', 'pay-cards'])
const paystackConfig = window.paystackConfig || {}
const mobileMoneyProviders = computed(() => paystackConfig.paystackMobileMoney?.[String(props.currency || '').toUpperCase()] || [])

// Available payment methods (mapped to Paystack channels)
const availableMethods = computed(() => {
    const methods = [];

    if (paystackConfig.paystackEnabled) {
        if (paystackConfig.paystackMobileMoneyEnabled && mobileMoneyProviders.value.length > 0) {
            methods.push({
                id: 'mobile_money',
                name: 'Paystack Mobile Money',
                channel: 'mobile_money',
                description: mobileMoneyProviders.value.map((provider) =>
                    provider.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ')
                ).join(', '),
                icon: null
            });
        }

        methods.push({
            id: 'card',
            name: 'Paystack Card',
            channel: 'card',
            description: 'Visa, Mastercard',
            icon: null
        });
    }

    return methods;
})

const is_processing = computed(() => props.is_processing);

// State
const selectedMethod = ref(props.initialMethod)

const processPayment = () => {
    emit('pay-cards', selectedMethod.value)
}

watch(selectedMethod, (newMethod) => {
    emit('method-change', newMethod)
})

watch(() => props.initialMethod, (method) => {
    if (method && method !== selectedMethod.value) {
        selectedMethod.value = method
    }
})

watch(availableMethods, (methods) => {
    if (!methods.find((method) => method.id === selectedMethod.value)) {
        selectedMethod.value = methods[0]?.id || ''
    }
}, { immediate: true })
</script>
