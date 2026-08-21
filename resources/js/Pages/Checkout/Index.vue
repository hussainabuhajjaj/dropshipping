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
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 class="text-sm font-semibold text-slate-900">{{ t('Payment method') }}</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">{{ t('Choose the option that is easiest for you. Secure card and mobile money payments are processed by Paystack when available.') }}</p>
              </div>
              <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-[0.64rem] font-bold uppercase tracking-[0.16em] text-emerald-700">
                {{ t('Secure') }}
              </span>
            </div>
            <div class="mt-4 grid gap-3 text-sm text-slate-600">
              <label
                v-for="method in paymentOptions"
                :key="method.value"
                class="flex cursor-pointer items-start gap-3 rounded-2xl border px-4 py-3 transition"
                :class="form.payment_method === method.value
                  ? 'border-slate-950 bg-slate-950 text-white shadow-sm'
                  : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'"
              >
                <input v-model="form.payment_method" type="radio" :value="method.value" class="mt-1" />
                <span class="min-w-0">
                  <span class="block font-black" :class="form.payment_method === method.value ? 'text-white' : 'text-slate-950'">
                    {{ method.label }}
                  </span>
                  <span class="mt-0.5 block text-xs leading-5" :class="form.payment_method === method.value ? 'text-white/70' : 'text-slate-500'">
                    {{ method.description }}
                  </span>
                </span>
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
            <h2 class="text-sm font-semibold text-slate-900">{{ t('Gift card') }}</h2>
            <div v-if="!gift_card" class="mt-3">
              <div class="flex gap-2">
                <input v-model="giftCardCode" type="text" placeholder="Gift card code" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                <button type="button" @click="applyGiftCard" :disabled="giftCardApplying || !giftCardCode.trim()" class="shrink-0 rounded-xl bg-purple-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-purple-700 disabled:opacity-50">
                  {{ giftCardApplying ? '...' : t('Apply') }}
                </button>
              </div>
              <p v-if="giftCardError" class="mt-1 text-xs text-rose-600">{{ giftCardError }}</p>
              <p v-if="page.props.flash?.status && !gift_card" class="mt-1 text-xs text-emerald-600">{{ page.props.flash.status }}</p>
            </div>
            <div v-else class="mt-3 flex items-center justify-between text-sm">
              <span class="text-purple-700 font-medium">{{ gift_card.code }} ({{ t('applied') }})</span>
              <button type="button" @click="removeGiftCard" :disabled="giftCardRemoving" class="text-xs text-rose-600 hover:text-rose-700 underline disabled:opacity-50">
                {{ giftCardRemoving ? '...' : t('Remove') }}
              </button>
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

          <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-full bg-[#111111] px-5 text-sm font-bold uppercase tracking-[0.12em] text-white transition hover:bg-[#262626] disabled:opacity-60" :disabled="form.processing">
            <svg v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            {{ form.processing ? t('Processing...') : t('Place order') }}
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
            <div v-if="gift_card_deduction > 0" class="flex items-center justify-between text-sm text-purple-700">
              <span>
                {{ t('Gift card') }}
                <span v-if="gift_card" class="text-xs text-slate-500">({{ gift_card.code }})</span>
              </span>
              <span>- {{ displayAmount(gift_card_deduction) }}</span>
            </div>
            <div class="flex items-center justify-between border-t border-[#eadfce] pt-4 text-base font-semibold text-slate-900">
              <span>{{ t('Total') }}</span>
              <span>{{ displayAmount(total) }}</span>
            </div>
            <div v-if="qualifiesForGiveaway" class="rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 to-yellow-50 px-4 py-3 text-sm">
              <div class="flex items-center gap-2 font-semibold text-amber-800">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l2.3 4.7 5.2.8-3.8 3.7.9 5.2L12 15l-4.6 2.4.9-5.2-3.8-3.7 5.2-.8L12 3z" />
                </svg>
                <span>{{ t("You're officially entered into the draw! Good luck!") }}</span>
              </div>
            </div>
          <div class="space-y-4 pt-2">
            <PaymentBadges :label="t('Payment protected by')" :show-paystack="Boolean(paystackKey)" />
            <DeliveryTimeline compact />
            <TrustBadges compact />
          </div>
          <p class="text-xs text-slate-500">
            {{ t('Delivery estimates and customs details are emailed after payment. Tracking updates within 24 to 48 hours post fulfillment.') }}
          </p>
        </aside>
      </div>
    </div>

    <!-- Processing Overlay -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="form.processing"
          class="fixed inset-0 z-[200] flex flex-col items-center justify-center gap-4 bg-white/80 backdrop-blur-sm"
        >
          <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-900 shadow-lg">
            <svg class="h-8 w-8 animate-spin text-white" viewBox="0 0 24 24" fill="none">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
          </div>
          <p class="text-sm font-semibold text-slate-900">{{ t('Processing your order...') }}</p>
          <p class="text-xs text-slate-500">{{ t('Please do not close this page.') }}</p>
        </div>
      </Transition>
    </Teleport>
  </StorefrontLayout>
</template>

<script setup>
import { computed, ref, toRefs, watch } from 'vue'
import { usePersistentCart } from '@/composables/usePersistentCart.js'
import { router, useForm, usePage } from '@inertiajs/vue3'
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
const page = usePage()
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
  gift_card: { type: Object, default: null },
  gift_card_deduction: { type: Number, default: 0 },
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
const paymentOptions = computed(() => {
  const options = []

  if (showMobileMoney.value) {
    options.push({
      value: 'mobile_money',
      label: t('Mobile Money'),
      description: t('Pay with a supported mobile money provider through Paystack.'),
    })
  }

  if (paystackEnabled.value) {
    options.push({
      value: 'card',
      label: t('Card payment'),
      description: t('Use Visa or Mastercard with secure Paystack processing.'),
    })
  }

  options.push({
    value: 'bank_transfer',
    label: t('Bank transfer'),
    description: t('Place the order and follow the bank transfer instructions.'),
  })

  return options
})
const defaultPaymentMethod = computed(() => paymentOptions.value[0]?.value || 'bank_transfer')
const displayAmount = (amount) =>
  formatCurrency(convertCurrency(Number(amount ?? 0), 'USD', displayCurrency.value), displayCurrency.value)
const qualifiesForGiveaway = computed(() => {
  const campaign = page.props.luckyDraw || null
  if (!campaign || !campaign.accepting_entries) return null
  const thresholdRaw = campaign.min_order_amount_usd ?? campaign.min_order_amount
  const threshold = Number(thresholdRaw || 0)
  if (threshold <= 0 || Number(subtotal.value) < threshold) return null
  return campaign
})

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
  payment_method: defaultPaymentMethod.value,
  mobile_money_provider: mobileMoneyProviders.value[0] || '',
  accept_terms: false,
})

watch(paymentOptions, (options) => {
  if (!options.some((option) => option.value === form.payment_method)) {
    form.payment_method = defaultPaymentMethod.value
  }
}, { immediate: true })

watch(mobileMoneyProviders, (providers) => {
  if (!providers.includes(form.mobile_money_provider)) {
    form.mobile_money_provider = providers[0] || ''
  }
}, { immediate: true })

const { cart: persistentCart } = usePersistentCart()

const giftCardCode = ref('')
const giftCardApplying = ref(false)
const giftCardRemoving = ref(false)
const giftCardError = ref('')

const applyGiftCard = () => {
  if (!giftCardCode.value.trim()) return
  giftCardApplying.value = true
  giftCardError.value = ''
  router.post('/checkout/apply-gift-card', { code: giftCardCode.value.trim() }, {
    preserveScroll: true,
    onSuccess: () => { giftCardApplying.value = false },
    onError: (errors) => {
      giftCardError.value = errors.gift_card || 'Failed to apply gift card.'
      giftCardApplying.value = false
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
