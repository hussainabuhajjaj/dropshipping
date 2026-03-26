<template>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-xl font-semibold text-slate-900 mb-4">{{ t('Payment Methods') }}</h2>

        <!-- Payment Method Selection -->
        <div class="space-y-3 mb-6">
            <div
                v-for="method in availableMethods"
                :key="method.id"
                @click="selectedMethod = method.id"
                class="flex items-center p-4 border rounded-xl cursor-pointer transition hover:border-[#f59e0b]"
                :class="{'border-[#f59e0b] bg-amber-50/50': selectedMethod === method.id}"
            >
                <input
                    type="radio"
                    :value="method.id"
                    v-model="selectedMethod"
                    class="w-4 h-4 text-[#f59e0b]"
                >
                <div class="ml-3 flex items-center gap-3 flex-1">
                    <!-- Method Icon -->
                    <component :is="method.icon" v-if="method.icon" class="h-6 w-6"/>

                    <span class="font-medium">{{ method.name }}</span>

                    <!-- Available Badge -->
                    <span class="ml-auto text-xs text-slate-500">
                        {{ method.description }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Pay with Korapay Button -->
        <button
            @click="processPayment"
            class="w-full bg-[#f59e0b] text-white py-4 px-6 rounded-xl font-semibold text-lg hover:bg-[#d97706] transition disabled:opacity-50 disabled:cursor-not-allowed"
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
            <span v-else>{{ t('Pay with Korapay') }} • {{ formattedAmount }}</span>
        </button>

        <!-- Secure Payment Notice -->
        <p class="text-xs text-center text-slate-500 mt-4">
            <svg class="inline-block h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a3 3 0 003-3v-6a3 3 0 00-3-3H6a3 3 0 00-3 3v6a3 3 0 003 3zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            {{ t('Secure XOF mobile money checkout with Korapay.') }}
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
        default: 'USD'
    },
    is_processing: {
        type: Boolean,
        default: false,
    }
})

// Available payment methods (mapped to Korapay channels)
const availableMethods = ref([
    {
        id: 'mobile_money',
        name: 'Mobile Money',
        channel: 'mobile_money',
        description: 'Wave, Orange Money',
        icon: null
    }
])

const is_processing = computed(() => props.is_processing);
const emit = defineEmits([ 'method-change', 'pay-cards'])

// State
const selectedMethod = ref('mobile_money')

const processPayment = () => {
    emit('pay-cards' , selectedMethod.value)
}

watch(selectedMethod, (newMethod) => {
    emit('method-change', newMethod)
})
</script>
