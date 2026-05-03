<template>
  <StorefrontLayout>
    <div class="space-y-5 pb-24 sm:space-y-8">
      <section class="rounded-[1.8rem] bg-[#111111] px-4 py-5 text-white shadow-[0_20px_48px_rgba(15,23,42,0.16)] sm:px-6">
        <p class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#facc15]">{{ t('Checkout flow') }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <span class="rounded-full bg-white/10 px-3 py-2 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-white">{{ t('1. Address') }}</span>
          <span class="rounded-full bg-white/10 px-3 py-2 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-white">{{ t('2. Payment') }}</span>
          <span class="rounded-full bg-[#ff6b35] px-3 py-2 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-white">{{ t('3. Place order') }}</span>
        </div>
        <h1 class="mt-4 text-[1.95rem] font-black tracking-[-0.04em] sm:text-[2.25rem]">{{ t('Checkout') }}</h1>
        <p class="mt-2 max-w-xl text-sm leading-6 text-white/72">{{ t('Keep the final step light: address, payment, review. No extra browsing friction, no hidden totals.') }}</p>
      </section>

      <div class="grid gap-10 lg:grid-cols-[1.4fr,1fr]">
        <div v-if="!props.user" class="mb-2 rounded-lg bg-slate-50 p-4 text-sm text-slate-700 border border-slate-200 flex items-center gap-2">
          <svg viewBox="0 0 24 24" class="h-5 w-5 text-slate-400 mr-2" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M8 15h8M8 11h8M8 7h8"/></svg>
          <span><strong>{{ t('Continue as guest') }}</strong> &mdash; {{ t('No account required. You can create one after checkout for faster future orders.') }}</span>
        </div>
        <div class="space-y-6">
          <!-- Express Checkout Buttons -->
          <ExpressCheckoutButtons
            :amount="total"
            :currency="currency"
            :display-currency="displayCurrency"
            :stripe-key="stripeKey"
            :paystack-key="paystackKey"
          />
          <PaymentBadges
            class="mt-3"
            :label="t('Accepted payments')"
            :show-stripe="Boolean(stripeKey)"
            :show-paystack="Boolean(paystackKey)"
          />

          <form class="space-y-6" @submit.prevent="submit">
          <p v-if="form.errors.payment" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
            {{ form.errors.payment }}
          </p>
          <Address 
            :user="user"
            :defaultAddress="defaultAddress"
            :userAddresses="addresses"
            :t="t"
            @change-address="handleAddressChange"
          />

          <section class="rounded-[1.6rem] border border-[#eadfce] bg-white p-5 shadow-[0_14px_34px_rgba(15,23,42,0.05)]">
            <h2 class="text-sm font-semibold text-slate-900">{{ t('Payment method') }}</h2>
            <div class="mt-4 grid gap-3 text-sm text-slate-600">
              <label v-if="paystackEnabled" class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2">
                <input v-model="form.payment_method" type="radio" value="card" />
                <span>{{ t('Paystack Card (Visa / Mastercard)') }}</span>
              </label>
              <label v-if="showMobileMoney" class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2">
                <input v-model="form.payment_method" type="radio" value="mobile_money" />
                <span>{{ t('Paystack Mobile Money') }}</span>
              </label>
              <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2">
                <input v-model="form.payment_method" type="radio" value="bank_transfer" />
                <span>{{ t('Bank transfer') }}</span>
              </label>
            </div>
            <div v-if="form.payment_method === 'mobile_money' && showMobileMoneyProviders" class="mt-4 space-y-2">
              <label class="block text-xs font-medium uppercase tracking-wide text-slate-500">{{ t('Mobile money provider') }}</label>
              <select v-model="form.mobile_money_provider" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                <option v-for="provider in mobileMoneyProviders" :key="provider" :value="provider">
                  {{ formatProvider(provider) }}
                </option>
              </select>
            </div>
          </section>

          <section class="rounded-[1.6rem] border border-[#eadfce] bg-white p-5 shadow-[0_14px_34px_rgba(15,23,42,0.05)]">
            <label class="flex items-start gap-3 text-sm text-slate-600">
              <input v-model="form.accept_terms" type="checkbox" />
              <span>
                {{ t('I agree to the') }}
                <a class="font-semibold text-slate-900 hover:text-slate-700" href="/legal/terms-of-service">{{ t('terms') }}</a>
                {{ t('and') }}
                <a class="font-semibold text-slate-900 hover:text-slate-700" href="/legal/refund-policy">{{ t('refund policy') }}</a>.
              </span>
            </label>
          </section>

          <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-full bg-[#111111] px-5 text-sm font-bold uppercase tracking-[0.12em] text-white transition hover:bg-[#262626]">
            {{ t('Place order') }}
          </button>
        </form>
        </div>

        <aside class="sticky top-28 space-y-4 rounded-[1.8rem] border border-[#eadfce] bg-[#fffaf4] p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
          <div class="flex items-center justify-between text-sm">
            <span>{{ t('Subtotal') }}</span>
            <span class="font-semibold text-slate-900">{{ displayAmount(subtotal) }}</span>
          </div>
          <div v-if="discount > 0" class="flex items-center justify-between text-sm text-green-700">
            <span>
              {{ t('Discount') }}
              <span v-if="discount_label" class="text-xs text-slate-500">({{ discount_label }})</span>
            </span>
            <span>- {{ displayAmount(discount) }}</span>
          </div>
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
          <div class="flex items-center justify-between text-sm">
            <span>
              {{ t('Shipping') }} <span class="text-xs text-slate-400">({{ shipping_method }})</span>
            </span>
            <span class="text-slate-600">{{ displayAmount(shipping) }}</span>
          </div>
          <p class="text-[0.65rem] text-slate-500">
            {{ t('Your order may arrive in multiple packages and tracking numbers may update separately.') }}
          </p>
          <p class="text-[0.65rem] text-slate-500">
            {{ t('Shipping costs are estimated until you provide an address; the total will refresh before payment once final rates are fetched.') }}
          </p>
          <div class="flex items-center justify-between text-sm">
            <span>
              {{ tax_label }} <span v-if="tax_included" class="text-xs text-slate-400">({{ t('included') }})</span>
            </span>
            <span class="text-slate-600">{{ displayAmount(tax_total) }}</span>
          </div>
          <div class="flex items-center justify-between border-t border-[#eadfce] pt-4 text-base font-semibold text-slate-900">
            <span>{{ t('Total') }}</span>
            <span>{{ displayAmount(total) }}</span>
          </div>
          <div class="space-y-4 pt-2">
            <DeliveryTimeline compact />
            <TrustBadges compact />
          </div>
          <p class="text-xs text-slate-500">
            {{ t('Delivery estimates and customs details are emailed after payment. Tracking updates within 24 to 48 hours post fulfillment.') }}
          </p>
        </aside>
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { computed, toRefs, watch } from 'vue'
import { usePersistentCart } from '@/composables/usePersistentCart.js'
import { useForm } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ExpressCheckoutButtons from '@/Components/ExpressCheckoutButtons.vue'
import TrustBadges from '@/Components/TrustBadges.vue'
import DeliveryTimeline from '@/Components/DeliveryTimeline.vue'
import PaymentBadges from '@/Components/PaymentBadges.vue'
import Address from '@/Components/payment/Address.vue'
import { useTranslations } from '@/i18n'
import { usePromoNow, formatCountdown } from '@/composables/usePromoCountdown.js'
import { useUserPreferences } from '@/composables/useUserPreferences.js'

const paystackConfig = window.paystackConfig || {}
const props = defineProps({
  subtotal: { type: Number, default: 0 },
  total: { type: Number, default: 0 },
  currency: { type: String, default: 'USD' },
  shipping_method: { type: String, default: 'standard' },
  discount: { type: Number, default: 0 },
  discount_label: { type: String, default: null },
  coupon: { type: Object, default: null },
  appliedPromotions: { type: Array, default: () => [] },
  cartPromotions: { type: Array, default: () => [] },
  tax_total: { type: Number, default: 0 },
  tax_label: { type: String, default: 'Tax' },
  tax_included: { type: Boolean, default: false },
  user: { type: Object, default: null },
  defaultAddress: { type: Object, default: null },
  addresses: { type: Array, default: () => [] },
  shipping: { type: Number, default: 0 },
  stripeKey: { type: String, default: '' },
  paystackKey: { type: String, default: '' },
})

const { t } = useTranslations()
const { subtotal, total, currency, shipping_method, discount, coupon, tax_total, tax_label, tax_included, shipping } = toRefs(props)
const now = usePromoNow()
const promoCountdown = (promo) => formatCountdown(promo?.end_at, now.value)
const displayPromotions = computed(() =>
  props.appliedPromotions?.length ? props.appliedPromotions : props.cartPromotions
)
const { currentCurrency, formatCurrency, convertCurrency } = useUserPreferences()
const displayCurrency = computed(() => currentCurrency.value || currency.value || 'USD')
const paystackEnabled = computed(() => Boolean(paystackConfig.paystackEnabled))
const mobileMoneyEnabled = computed(() => Boolean(paystackConfig.paystackMobileMoneyEnabled))
const mobileMoneyProviders = computed(() => paystackConfig.paystackMobileMoney?.[String(props.currency || '').toUpperCase()] || [])
const showMobileMoney = computed(() => paystackEnabled.value && mobileMoneyEnabled.value && mobileMoneyProviders.value.length > 0)
const showMobileMoneyProviders = computed(() => showMobileMoney.value && mobileMoneyProviders.value.length > 0)
const displayAmount = (amount) =>
  formatCurrency(convertCurrency(Number(amount ?? 0), 'USD', displayCurrency.value), displayCurrency.value)
const formatProvider = (provider) =>
  String(provider)
    .split('_')
    .map((part) => part.toUpperCase() === 'ATL' ? 'AirtelTigo' : part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')

const form = useForm({
  email: props.user?.email || '',
  phone: props.user?.phone || '',
  first_name: props.defaultAddress?.name || props.user?.name || '',
  last_name: '',
  line1: props.defaultAddress?.line1 || '',
  line2: props.defaultAddress?.line2 || '',
  city: props.defaultAddress?.city || '',
  state: props.defaultAddress?.state || '',
  postal_code: props.defaultAddress?.postal_code || '',
  country: props.defaultAddress?.country || 'CI',
  delivery_notes: '',
  payment_method: showMobileMoney.value ? 'mobile_money' : 'card',
  mobile_money_provider: mobileMoneyProviders.value[0] || '',
  accept_terms: false,
})

watch(showMobileMoney, (enabled) => {
  if (!enabled && form.payment_method === 'mobile_money') {
    form.payment_method = 'card'
  }
})

watch(mobileMoneyProviders, (providers) => {
  if (!providers.includes(form.mobile_money_provider)) {
    form.mobile_money_provider = providers[0] || ''
  }
}, { immediate: true })

const { cart: persistentCart } = usePersistentCart()

// Watch for guest email entry and send abandoned cart
watch(
  () => form.email,
  (email) => {
    if (email && !props.user) {
      // Only send for guests
      fetch('/cart/abandon', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ cart: persistentCart.value, email })
      })
    }
  }
)

const submit = () => {
  form.post('/checkout')
}

const handleAddressChange = (addressData) => {
  // Update form with address data from Address component
  form.email = addressData.email
  form.phone = addressData.phone
  form.first_name = addressData.first_name
  form.last_name = addressData.last_name
  form.line1 = addressData.line1
  form.line2 = addressData.line2
  form.city = addressData.city
  form.state = addressData.state
  form.postal_code = addressData.postal_code
  form.country = addressData.country
  form.delivery_notes = addressData.delivery_notes
}
</script>
