<template>
    <StorefrontLayout>
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-slate-900">{{ t('Payment') }}</h1>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
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

                    <Address
                        v-if="type === 'cart'"
                        :user="customer"
                        :defaultAddress="defaultAddress"
                        :userAddresses="userAddresses"
                        @change-address="changeAddress"
                        @address-selected="changeAddress"
                    />

                    <PaymentMethods
                        :amount="final_total"
                        :formatted-amount="displayAmount(final_total)"
                        :currency="displayCurrency"
                        :initial-method="selectedMethod"
                        :is_processing="isProcessing"
                        @method-change="handleMethodChange"
                        @pay-cards="payWithPaystack"
                    >
                        <template #mobile-money-details>
                            <div class="mb-3">
                                <p class="text-sm font-semibold text-slate-900">{{ t('Payment details') }}</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ t('You will be redirected to Paystack to choose your provider and enter your mobile money number securely.') }}
                                </p>
                            </div>
                        </template>
                    </PaymentMethods>
                </div>

                <div class="lg:col-span-1 space-y-6">
                    <PaymentSummary
                        :summary-data="summery"
                        :currency="displayCurrency"
                        :item-count="totalItems"
                        :estimated-delivery="estimatedDelivery"
                        :selected-method="selectedMethod"
                        :method-name="selectedMethodName"
                    />
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>

<script setup>
import {computed, onMounted, ref} from 'vue'
import {usePage} from '@inertiajs/vue3'
import axios from 'axios'
import {useTranslations} from '@/i18n'
import {useUserPreferences} from '@/composables/useUserPreferences.js'
import {usePromoNow, formatCountdown} from '@/composables/usePromoCountdown.js'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import OrderSummary from '@/Components/payment/OrderSummary.vue'
import PaymentMethods from '@/Components/payment/PaymentMethods.vue'
import PaymentSummary from '@/Components/payment/PaymentSummary.vue'
import Address from '@/Components/payment/Address.vue'
import {toastAlert} from '@/utils/toast.js'

const page = usePage()
const paystackConfig = window.paystackConfig || {}
const {t} = useTranslations()
const {formatCurrency, convertCurrency} = useUserPreferences()

const now = usePromoNow()
const promoCountdown = (promo) => formatCountdown(promo?.end_at, now.value)

const type = page.props.type
const summery = page.props.summery
const final_total = Number(page.props.final_total || 0)
const customer = page.props.customer
const defaultAddress = page.props.defaultAddress
const userAddresses = page.props.addresses || []
const items = computed(() => page.props.items || [])

const discount = computed(() => summery?.discount || 0)
const discountLabel = computed(() => summery?.discount_label || '')
const displayPromotions = computed(() => summery?.appliedPromotions?.length ? summery.appliedPromotions : [])
const displayCurrency = computed(() => 'XOF')
const totalItems = computed(() => items.value.reduce((sum, item) => sum + Number(item.quantity || 0), 0))
const mobileMoneyProviders = computed(() => paystackConfig.paystackMobileMoney?.XOF || ['orange', 'wave', 'mtn'])

const selectedMethod = ref(mobileMoneyProviders.value.length ? 'mobile_money' : 'card')
const selectedMethodName = ref('')
const estimatedDelivery = ref('7-21 business days')
const address = ref(buildInitialAddress())
const isProcessing = ref(false)

const displayAmount = (amount) => {
    return formatCurrency(convertCurrency(Number(amount || 0), 'USD', displayCurrency.value), displayCurrency.value)
}

function buildInitialAddress() {
    return {
        email: customer?.email || '',
        phone: customer?.phone || '',
        first_name: defaultAddress?.name?.split(' ')?.[0] || customer?.name?.split(' ')?.[0] || '',
        last_name: defaultAddress?.name?.split(' ')?.slice(1).join(' ') || customer?.name?.split(' ')?.slice(1).join(' ') || '',
        line1: defaultAddress?.line1 || '',
        line2: defaultAddress?.line2 || '',
        city: defaultAddress?.city || '',
        state: defaultAddress?.state || '',
        postal_code: defaultAddress?.postal_code || '',
        country: defaultAddress?.country || 'CI',
        delivery_notes: '',
    }
}

function csrfHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    const xsrfToken = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]

    return {
        ...(token ? {'X-CSRF-TOKEN': token} : {}),
        ...(xsrfToken ? {'X-XSRF-TOKEN': decodeURIComponent(xsrfToken)} : {}),
    }
}

function handleMethodChange(method) {
    selectedMethod.value = method
    selectedMethodName.value = method === 'mobile_money' ? 'Mobile Money' : 'Card'
}

async function payWithPaystack(method) {
    if (!address.value?.email) {
        toastAlert('error', 'Email is required')
        return
    }

    const identityName = [address.value.first_name, address.value.last_name].filter(Boolean).join(' ').trim() || customer?.name || ''

    isProcessing.value = true

    try {
        // Get the exact converted total that PaymentSummary displays
        const convertedTotal = convertCurrency(Number(final_total), 'USD', displayCurrency.value)
        const amountToSend = Math.round(convertedTotal)

        const response = await axios.post('/paystack/initialize', {
            payment_method: method,
            email: address.value.email,
            customer_name: identityName,
            grand_total: amountToSend,
            currency: 'XOF',
        }, {
            headers: csrfHeaders(),
        })

        const data = response.data?.data || {}

        if (!/^https:\/\/checkout\.paystack\.com\//.test(data.authorization_url || '')) {
            throw new Error(response.data?.message || 'Paystack did not return an authorization URL.')
        }

        window.location.href = data.authorization_url
    } catch (error) {
        toastAlert('error', error?.response?.data?.message || error?.message || 'Payment failed')
    } finally {
        isProcessing.value = false
    }
}

function changeAddress(payload) {
    if (!payload) {
        return
    }

    const value = payload?.value ?? payload
    const newPhone = value.phone || customer?.phone || address.value?.phone || ''

    address.value = {
        ...address.value,
        ...value,
        phone: newPhone,
    }
}

onMounted(() => {
    handleMethodChange(selectedMethod.value)
})
</script>
