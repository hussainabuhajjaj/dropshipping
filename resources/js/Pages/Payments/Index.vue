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

                    <div class="rounded-xl border border-[#eadfce] bg-white p-4">
                        <h3 class="text-sm font-semibold text-slate-900">{{ t('Voucher / Gift card') }}</h3>
                        <div v-if="!giftCard && !couponApplied" class="mt-3">
                            <div class="flex gap-2">
                                <input v-model="voucherCode" type="text" placeholder="Code promo ou carte cadeau" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                <button type="button" @click="applyVoucher" :disabled="voucherApplying || !voucherCode.trim()" class="shrink-0 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700 disabled:opacity-50">
                                    {{ voucherApplying ? t('Vérification...') : t('Appliquer') }}
                                </button>
                            </div>
                            <p v-if="voucherError" class="mt-1 text-xs text-rose-600">{{ voucherError }}</p>
                            <p v-if="voucherSuccess" class="mt-1 text-xs text-emerald-600">{{ voucherSuccess }}</p>
                        </div>
                        <div v-if="giftCard" class="mt-3 flex items-center justify-between text-sm">
                            <span class="text-purple-700 font-medium">{{ t('Carte cadeau') }}: {{ giftCard.code }}</span>
                            <button type="button" @click="removeGiftCard" :disabled="giftCardRemoving" class="text-xs text-rose-600 hover:text-rose-700 underline disabled:opacity-50">
                                {{ giftCardRemoving ? '...' : t('Retirer') }}
                            </button>
                        </div>
                        <div v-if="couponApplied" class="mt-3 flex items-center justify-between text-sm">
                            <span class="text-green-700 font-medium">{{ t('Code promo') }}: {{ couponCode }}</span>
                        </div>
                    </div>

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
import {router, usePage} from '@inertiajs/vue3'
import axios from 'axios'
import {useTranslations} from '@/i18n'
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

const now = usePromoNow()
const promoCountdown = (promo) => formatCountdown(promo?.end_at, now.value)

const type = page.props.type
const summery = page.props.summery
const final_total = Number(page.props.final_total || 0)
const giftCard = page.props.gift_card || null
const giftCardDeduction = Number(page.props.gift_card_deduction || 0)
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

const couponApplied = computed(() => discount.value > 0)
const couponCode = computed(() => summery?.coupon?.code || '')

const voucherCode = ref('')
const voucherApplying = ref(false)
const voucherError = ref('')
const voucherSuccess = ref('')
const giftCardRemoving = ref(false)

const applyVoucher = () => {
    if (!voucherCode.value.trim()) return
    voucherApplying.value = true
    voucherError.value = ''
    voucherSuccess.value = ''

    router.post('/checkout/apply-gift-card', { code: voucherCode.value.trim() }, {
        preserveScroll: true,
        onSuccess: () => {
            voucherApplying.value = false
            voucherCode.value = ''
        },
        onError: (errors) => {
            if (errors.gift_card) {
                router.post(route('cart.coupon.apply'), { code: voucherCode.value.trim() }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        voucherApplying.value = false
                        voucherCode.value = ''
                        voucherSuccess.value = 'Code promo appliqué !'
                    },
                    onError: (couponErrors) => {
                        voucherError.value = couponErrors?.code || 'Code invalide.'
                        voucherApplying.value = false
                    },
                })
            } else {
                voucherError.value = errors.gift_card || 'Code invalide.'
                voucherApplying.value = false
            }
        },
    })
}

const removeGiftCard = () => {
    giftCardRemoving.value = true
    router.post('/checkout/remove-gift-card', {}, {
        preserveScroll: true,
        onSuccess: () => { giftCardRemoving.value = false },
        onError: () => { giftCardRemoving.value = false },
    })
}

const selectedMethod = ref(mobileMoneyProviders.value.length ? 'mobile_money' : 'card')
const selectedMethodName = ref('')
const estimatedDelivery = ref('7-21 business days')
const address = ref(buildInitialAddress())
const isProcessing = ref(false)

const displayAmount = (amount) => {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: displayCurrency.value,
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(amount || 0))
}

function buildInitialAddress() {
    return {
        address_id: defaultAddress?.id || null,
        email: customer?.email || '',
        phone: defaultAddress?.phone || customer?.phone || '',
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

function hasValidAddress() {
    if (address.value?.address_id) {
        return true
    }

    return Boolean(
        address.value?.first_name &&
        address.value?.phone &&
        address.value?.line1 &&
        address.value?.city &&
        address.value?.country
    )
}

async function payWithPaystack(method) {
    if (!address.value?.email) {
        toastAlert('error', 'Email is required')
        return
    }

    if (!hasValidAddress()) {
        toastAlert('error', 'Please select or enter a valid shipping address before payment')
        return
    }

    const identityName = [address.value.first_name, address.value.last_name].filter(Boolean).join(' ').trim() || customer?.name || ''

    isProcessing.value = true

    try {
        const amountToSend = Number(page.props.summery?.raw?.total || final_total || 0)

        const response = await axios.post('/paystack/initialize', {
            payment_method: method,
            address_id: address.value.address_id || null,
            email: address.value.email,
            phone: address.value.phone,
            first_name: address.value.first_name,
            last_name: address.value.last_name,
            line1: address.value.line1,
            line2: address.value.line2,
            city: address.value.city,
            state: address.value.state,
            postal_code: address.value.postal_code,
            country: address.value.country,
            delivery_notes: address.value.delivery_notes,
            customer_name: identityName,
            grand_total: amountToSend,
            currency: 'XOF',
        }, {
            headers: csrfHeaders(),
        })

        const data = response.data?.data || {}
        const authUrl = data.authorization_url || ''

        const isValidPaystackUrl = /^https:\/\/checkout\.paystack\.(co|com)\//.test(authUrl)

        if (!isValidPaystackUrl) {
            throw new Error(response.data?.message || 'Paystack did not return a valid authorization URL.')
        }

        window.location.href = authUrl
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
    const resolvedAddressId = value.address_id || value.id || null
    const newPhone = value.phone || customer?.phone || address.value?.phone || ''

    address.value = {
        ...address.value,
        ...value,
        address_id: resolvedAddressId,
        phone: newPhone,
    }
}

onMounted(() => {
    handleMethodChange(selectedMethod.value)
})
</script>
