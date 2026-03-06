<template>
    <StorefrontLayout>
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-slate-900">{{ t('Payment') }}</h1>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column - Order Summary & Payment Methods -->
                <div class="lg:col-span-2 space-y-6">

                    <OrderSummary
                        :type="type"
                        :items="items"
                        :shipping="displayAmount(20)"
                        :tax="displayAmount(6.01)"
                        :discount="displayAmount(discount)"
                        :discount-label="discountLabel"
                        tax-label="VAT"
                        :currency="displayCurrency"
                    />

                    <!-- Promotions Section -->
                    <div v-if="displayPromotions.length" class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs text-slate-700">
                        <div class="mb-1 font-semibold text-amber-700">{{ t('Promotions applied:') }}</div>
                        <ul class="space-y-1">
                            <li v-for="promo in displayPromotions" :key="promo.id">
                                <span class="font-semibold">{{ promo.name }}</span>
                                <span class="ml-1">({{ promo.type === 'flash_sale' ? t('Flash Sale') : t('Auto Discount') }})</span>
                                <span class="ml-2" v-if="promo.value_type === 'percentage'">-{{ promo.value }}%</span>
                                <span class="ml-2" v-else-if="promo.value_type === 'fixed'">-{{ displayAmount(Number(promo.value ?? 0)) }}</span>
                                <span v-if="promoCountdown(promo)" class="ml-2 text-[10px] font-semibold text-amber-700">
                                    {{ t('Ends in') }} {{ promoCountdown(promo) }}
                                </span>
                            </li>
                        </ul>
                    </div>


                    <Address v-if="type === 'cart'"
                             :user="customer"
                             :defaultAddress="defaultAddress"
                             :userAddresses="userAddresses"
                             @change-address="changeAddress"
                    />
                    <!-- Payment Methods Component -->
                    <PaymentMethods
                        :amount="final_total"
                        :formatted-amount="displayAmount(final_total)"
                        :currency="displayCurrency"
                        :initial-method="selectedMethod"
                        :is_processing="is_processing"
                        @method-change="handleMethodChange"
                        @pay-cards="payWithKorapay"
                    />

                </div>

                <!-- Right Column - Payment Summary & Error -->
                <div class="lg:col-span-1 space-y-6">
                    <PaymentSummary
                        :summary-data="summery"
                        :currency="displayCurrency"
                        :item-count="totalItems"
                        :estimated-delivery="estimatedDelivery"
                        :selected-method="selectedMethod"
                        :method-name="selectedMethodName"
                    />


                    <!-- Error Card Component (if payment fails) -->
                    <!--                    <ErrorCard-->
                    <!--                        v-if="paymentError"-->
                    <!--                        :error-message="errorMessage"-->
                    <!--                        :checkout-id="checkoutId"-->
                    <!--                        :timestamp="errorTimestamp"-->
                    <!--                        @contact-support="contactSupport"-->
                    <!--                    />-->
                </div>
            </div>
        </div>


    </StorefrontLayout>
</template>

<script setup>
import {ref, computed, onMounted} from 'vue'
import {useTranslations} from '@/i18n'
import {useUserPreferences} from '@/composables/useUserPreferences.js'
import {usePromoNow, formatCountdown} from '@/composables/usePromoCountdown.js'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import OrderSummary from '@/Components/payment/OrderSummary.vue'
import PaymentMethods from '@/Components/payment/PaymentMethods.vue'
import PaymentSummary from '@/Components/payment/PaymentSummary.vue'
import {toastAlert} from "@/utils/toast.js";


import {usePage} from '@inertiajs/vue3'

const page = usePage();
import axios from "axios";
import Address from "@/Components/payment/Address.vue";

const {t} = useTranslations()
const {
  currentCurrency,
  formatCurrency,
  convertCurrency
} = useUserPreferences()

const now = usePromoNow()
const promoCountdown = (promo) => formatCountdown(promo?.end_at, now.value)

// Extract discount and promotion data from summery
const discount = computed(() => summery?.discount || 0)
const discountLabel = computed(() => summery?.discount_label || '')
const displayPromotions = computed(() =>
  summery?.appliedPromotions?.length ? summery.appliedPromotions : []
)


const type = page.props.type
const id = page.props.id
const summery = page.props.summery
const final_total = page.props.final_total
const customer = page.props.customer
const defaultAddress = page.props.defaultAddress
const userAddresses = page.props.addresses || []
const items = computed(() => page.props.items)

const displayCurrency = computed(() => currentCurrency.value || 'USD')
const is_processing = ref(false)
const address = ref(null);

// Currency conversion helper
const displayAmount = (amount) => {
  return formatCurrency(convertCurrency(Number(amount || 0), 'USD', displayCurrency.value), displayCurrency.value)
}

const totalItems = computed(() => {
    return items.value.reduce((sum, item) => sum + item.quantity, 0)
})

// Payment state
const selectedMethod = ref('card')
const selectedMethodName = ref('')
const estimatedDelivery = ref('7-21 business days')

onMounted(()=>{
    // console.log(page.props)
})
// Error state
// const paymentError = ref(true) // Set to true to show error, false to hide
// const errorMessage = ref('Payment cannot be completed. Please contact support with following information:')
// const checkoutId = ref('IDBB407068825B84C90ABF544205412F.uato1-vm-tx02 is invalid.')
// const errorTimestamp = ref('Fri, 27 Feb 2026 18:51:38 GMT')

// Methods
const handleMethodChange = (method) => {
    selectedMethod.value = method
    const methodNames = {
        card: 'Visa , MasterCard',
        mobile_money: 'Mobile Payment',
    }
    selectedMethodName.value = methodNames[method] || method
}
const payWithKorapay = async (method) => {
    is_processing.value = true;
    try {
        axios.post(`/pay/${type}/${id}/checkout`, {
            "method": method,
            ...address.value
        }).then(({data}) => {
            is_processing.value = false;
            console.log(data)
            if (data.status && data?.data?.redirect) {
                console.log(123)
                window.location = data?.data?.redirect;
            } else {
                // error
            }
        }).catch(({response}) => {
            is_processing.value = false;

            if (response?.data?.message) {
                toastAlert('error', response?.data?.message);
            }
            // console.error('Payment failed:')
            console.log(response.data)

        });
    } catch (error) {
        // emit('payment-failed', {
        //     message: error.message || 'Payment failed',
        //     provider: 'korapay'
        // })
    }
}

onMounted(() => {
    handleMethodChange(selectedMethod.value)
})

const changeAddress = (income_address) => {
    address.value = income_address.value;
}
</script>
