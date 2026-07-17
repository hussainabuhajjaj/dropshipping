<template>
    <StorefrontLayout>
        <div class="space-y-5 pb-24 sm:space-y-8">
            <section class="rounded-[1.8rem] bg-[#111111] px-4 py-5 text-white shadow-[0_20px_48px_rgba(15,23,42,0.16)] sm:px-6">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#facc15]">{{ t('Bag review') }}</p>
                        <h1 class="mt-2 text-[1.9rem] font-black tracking-[-0.04em] sm:text-[2.2rem]">{{ t('Your cart') }}</h1>
                        <p class="mt-2 max-w-xl text-sm leading-6 text-white/72">{{ t('Keep the momentum. Prices, deal tags, and low-stock signals stay visible so shoppers move from bag to checkout without second-guessing.') }}</p>
                    </div>
                    <Link href="/products" class="inline-flex min-h-11 items-center justify-center rounded-full border border-white/15 bg-white/8 px-4 text-sm font-semibold text-white transition hover:bg-white/12">
                        {{ t('Continue shopping') }}
                    </Link>
                </div>
            </section>

            <div class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <div class="shrink-0 rounded-full bg-[#fff4e8] px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-[#c55b24]">{{ t(':count items', { count: itemCount }) }}</div>
                <div v-if="savingsTotal > 0" class="shrink-0 rounded-full bg-emerald-50 px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-emerald-700">{{ t('Saving :amount', { amount: displayPrice(savingsTotal) }) }}</div>
                <div class="shrink-0 rounded-full bg-white px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-slate-500 ring-1 ring-[#eadfce]">{{ t('Fast checkout') }}</div>
            </div>

            <!-- Free Shipping Progress Bar -->
            <div v-if="lines.length" class="rounded-2xl border border-slate-100 bg-white px-4 py-3 shadow-sm">
              <div class="flex items-center justify-between text-xs">
                <span class="font-medium text-slate-700">
                  <template v-if="freeShippingRemaining > 0">
                    {{ t('Add :amount more for free shipping', { amount: displayPrice(freeShippingRemaining) }) }}
                  </template>
                  <template v-else>
                    <span class="flex items-center gap-1.5 text-emerald-700">
                      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                      </svg>
                      {{ t('You qualify for free shipping!') }}
                    </span>
                  </template>
                </span>
                <span class="font-semibold text-slate-500">{{ displayPrice(subtotal) }}</span>
              </div>
              <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div
                  class="h-full rounded-full transition-all duration-700 ease-out"
                  :class="freeShippingRemaining <= 0 ? 'bg-emerald-500' : 'bg-slate-900'"
                  :style="{ width: freeShippingPercent + '%' }"
                />
              </div>
              <p class="mt-1 text-right text-[0.55rem] font-semibold uppercase tracking-wider text-slate-400">
                {{ t('Free shipping on orders over :amount', { amount: displayPrice(freeShippingThreshold) }) }}
              </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1.6fr,1fr]">
                <div v-if="lines.length" class="space-y-3">
                    <CartLineItem
                        v-for="line in lines"
                        :key="line.id"
                        :line="line"
                        :currency="displayCurrency"
                        :promotions="displayPromotions"
                        @remove="removeLine(line.id)"
                        @update="updateQty"
                    />
                </div>
                <EmptyState
                    v-else
                    :eyebrow="t('Cart')"
                    :title="t('Your cart is waiting')"
                    :message="t('Add a few Simbazu finds and we will hold them here. Prices update automatically before checkout.')"
                >
                    <template #actions>
                        <Link href="/products" class="btn-primary">{{ t('Browse products') }}</Link>
                        <Link href="/orders/track" class="btn-ghost">{{ t('Track existing order') }}</Link>
                    </template>
                </EmptyState>
                <section v-if="!lines.length && recentlyViewed.length" class="mt-6 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">{{ t('Recently Viewed') }}</h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <CompactProductCard
                            v-for="(product, idx) in recentlyViewed"
                            :key="product.id"
                            :product="product"
                            :currency="currency"
                        />
                    </div>
                </section>

                <aside class="sticky top-28 space-y-4 rounded-[1.8rem] border border-[#eadfce] bg-[#fffaf4] p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
                    <div class="space-y-3">
                        <form class="flex flex-col gap-3 sm:flex-row sm:items-center" @submit.prevent="applyCoupon">
                            <input v-model="couponCode" type="text" :placeholder="t('Coupon code')"
                                   class="input-base flex-1"/>
                            <div class="flex gap-2">
                                <button type="submit" class="btn-secondary">{{ t('Apply') }}</button>
                                <button v-if="coupon" type="button" class="btn-ghost text-xs" @click="removeCoupon">
                                    {{ t('Remove') }}
                                </button>
                            </div>
                        </form>
                        <p v-if="coupon" class="text-xs text-slate-600">
                            {{ t('Applied:') }} <span class="font-semibold text-slate-900">{{ coupon.code }}</span>
                            <span v-if="discount"> ({{ displayPrice(discount) }} {{ t('off') }})</span>
                        </p>

                        <!--             Applied Promotions (not just coupon) -->
                        <div v-if="displayPromotions && displayPromotions.length" class="applied-promotions">
                            <div class="text-xs font-semibold text-green-700 mb-1">{{ t('Promotions applied:') }}</div>
                            <ul class="space-y-1">
                                <li v-for="promo in displayPromotions" :key="promo.id" class="text-xs text-slate-700">
                                    <span class="font-semibold">{{ promo.name }}</span>
                                    <span class="ml-1">({{
                                            promo.type === 'flash_sale' ? t('Flash Sale') : t('Auto Discount')
                                        }})</span>
                                    <span class="ml-2" v-if="promo.value_type === 'percentage'">-{{ promo.value }}%</span>
                                    <span class="ml-2" v-else-if="promo.value_type === 'fixed'">-{{
                                            displayPrice(promo.value)
                                        }}</span>
                                    <span v-if="promoCountdown(promo)" class="ml-2 text-[10px] font-semibold text-amber-700">
                                        {{ t('Ends in') }} {{ promoCountdown(promo) }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div v-if="savingsTotal > 0" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <span class="font-semibold">{{ t('You are saving :amount', { amount: displayPrice(savingsTotal) }) }}</span>
                        <span class="ml-1 text-emerald-700">{{ t('with discounts and item deals.') }}</span>
                    </div>

                    <div v-if="qualifiesForGiveaway" class="rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 to-yellow-50 px-4 py-3 text-sm">
                        <div class="flex items-center gap-2 font-semibold text-amber-800">
                            <span>🎉</span>
                            <span>{{ t('Your cart qualifies for the giveaway!') }}</span>
                        </div>
                        <a href="/promotions/iphone-giveaway" class="mt-1 block text-xs text-amber-600 hover:text-amber-500 underline underline-offset-2">
                            {{ t('See details') }}
                        </a>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span>{{ t('Subtotal') }}</span>
                        <span class="font-semibold text-slate-900">{{ displayPrice(subtotal) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm text-green-700" v-if="discount > 0">
                        <span>
                            {{ t('Discount') }}
                            <span v-if="discountLabel" class="text-xs text-slate-500">({{ discountLabel }})</span>
                        </span>
                        <span>- {{ displayPrice(discount) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm text-slate-600">
                        <span>{{ t('Shipping') }}</span>
                        <span>{{ displayPrice(shipping) }}</span>
                        <!--            <span>{{ t('Shown at checkout') }}</span>-->
                    </div>
                    <div class="flex items-center justify-between text-sm text-slate-600">
                        <span>{{ t('Duties & VAT') }}</span>
                        <span>{{ t('Calculated at checkout') }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-200 pt-4 text-base">
                        <span class="font-semibold text-slate-900">{{ t('Estimated total') }}</span>
                        <span class="text-lg font-bold text-slate-900">{{ displayPrice(estimatedTotal) }}</span>
                    </div>
                    <p v-if="minimumMessage && !canCheckout" class="text-sm text-rose-600">
                        {{ minimumMessage }}
                    </p>
                    <button
                        :disabled="!canCheckout"
                        class="mt-4 inline-flex min-h-12 w-full items-center justify-center rounded-full bg-[#111111] px-5 text-sm font-bold uppercase tracking-[0.12em] text-white transition hover:bg-[#262626]"
                        :class="{ 'cursor-not-allowed opacity-60': !canCheckout }"
                        @click="$inertia.visit('/checkout')"
                    >
                        {{ t('Secure checkout') }}
                    </button>
                    <button
                        :disabled="creatingIntent || !lines.length"
                        class="inline-flex min-h-12 w-full items-center justify-center rounded-full border border-[#25D366]/30 bg-[#25D366]/10 px-5 text-sm font-bold text-[#128C49] transition hover:bg-[#25D366]/15"
                        :class="{ 'cursor-not-allowed opacity-60': creatingIntent || !lines.length }"
                        @click="sendCartViaWhatsApp"
                    >
                        {{ creatingIntent ? t('Preparing...') : t('Send cart via WhatsApp') }}
                    </button>
                    <p class="text-xs text-slate-500">
                        {{
                            t("Delivery to Cote d'Ivoire with transparent customs. Expect tracking within 24 to 48 hours after fulfillment.")
                        }}
                    </p>
                    <div class="space-y-4 border-t border-slate-200 pt-4">
                        <PaymentBadges :label="t('Accepted payments')" />
                        <TrustBadges compact />
                        <DeliveryTimeline compact />
                    </div>
                </aside>
            </div>
        </div>

        <div v-if="itemCount > 0" class="fixed inset-x-0 bottom-0 z-[120] border-t border-slate-200 bg-white/95 backdrop-blur lg:hidden">
            <div class="container-base pb-[max(0.875rem,env(safe-area-inset-bottom))] pt-3">
                <div class="flex items-center justify-between gap-4 rounded-[1.5rem] border border-[#eadfce] bg-white px-4 py-3 shadow-[0_-14px_40px_rgba(15,23,42,0.12)]">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Cart') }}</p>
                        <p class="text-sm font-semibold text-slate-900">{{ t(':count items', { count: itemCount }) }}</p>
                        <p class="text-lg font-bold text-slate-900">{{ displayPrice(estimatedTotal) }}</p>
                    </div>
                    <button
                        :disabled="!canCheckout"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-full bg-[#111111] px-5 text-sm font-bold uppercase tracking-[0.12em] text-white"
                        :class="{ 'cursor-not-allowed opacity-60': !canCheckout }"
                        @click="$inertia.visit('/checkout')"
                    >
                        {{ t('Checkout') }}
                    </button>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>

<script setup>
import { useUserPreferences } from '@/composables/useUserPreferences.js'

// Helper to display price in selected currency
const { formatCurrency, convertCurrency } = useUserPreferences()
function displayPrice(amount) {
    return formatCurrency(convertCurrency(amount, 'USD', displayCurrency.value), displayCurrency.value)
}

import { Link, router } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import CartLineItem from '@/Components/CartLineItem.vue'
import CompactProductCard from '@/Components/homepage/CompactProductCard.vue'
import EmptyState from '@/Components/EmptyState.vue'
import TrustBadges from '@/Components/TrustBadges.vue'
import DeliveryTimeline from '@/Components/DeliveryTimeline.vue'
import PaymentBadges from '@/Components/PaymentBadges.vue'
import { ref, computed } from 'vue'
import {usePersistentCart} from '@/composables/usePersistentCart.js'
import {useTranslations} from '@/i18n'
import { usePromoNow, formatCountdown } from '@/composables/usePromoCountdown.js'
import { useWhatsAppCheckout } from '@/composables/useWhatsAppCheckout.js'
import { useRecentlyViewed } from '@/composables/useRecentlyViewed.js'

const props = defineProps({
    lines: {type: Array, required: true},
    currency: {type: String, default: 'USD'},
    subtotal: {type: Number, default: 0},
    shipping: {type: Number, default: 0},
    discount: {type: Number, default: 0},
    estimated_total: {type: Number, default: 0},
    item_count: {type: Number, default: 0},
    savings_total: {type: Number, default: 0},
    discount_label: {type: String, default: null},
    coupon: {type: Object, default: null},
    appliedPromotions: {type: Array, default: () => []},
    cartPromotions: {type: Array, default: () => []},
    user: {type: Object, default: null},
    minimum_cart_requirement: {type: Object, default: null},
})

const {t} = useTranslations()
const { creatingIntent, startWhatsAppCheckout } = useWhatsAppCheckout({ t })
const now = usePromoNow()
const discountLabel = computed(() => props.discount_label)
const promoCountdown = (promo) => formatCountdown(promo?.end_at, now.value)
const displayPromotions = computed(() =>
    props.appliedPromotions?.length ? props.appliedPromotions : props.cartPromotions
)
const estimatedTotal = computed(() => props.estimated_total || Math.max(0, props.subtotal - props.discount + props.shipping))
const itemCount = computed(() => props.item_count || props.lines.reduce((sum, line) => sum + Number(line.quantity || 0), 0))
const savingsTotal = computed(() => props.savings_total || 0)
const qualifiesForGiveaway = computed(() => {
  const threshold = props.currency === 'USD' ? 50 : 30000
  return Number(props.subtotal) >= threshold
})
const { currentCurrency } = useUserPreferences()
const displayCurrency = computed(() => currentCurrency.value || props.currency)

const couponCode = ref('')

const {cart, removeLine: removeLineLocal, updateLine: updateLineLocal} = usePersistentCart()

const isLoggedIn = computed(() => !!props.user)

const removeLine = (id) => {
    // if (isLoggedIn.value) {
    router.delete(`/cart/${id}`, {
        preserveScroll: true,
    })
    // } else {
    //   removeLineLocal(id)
    // }
}

const updateQty = (id, quantity) => {
    // if (isLoggedIn.value) {
    router.patch(
        `/cart/${id}`,
        {quantity},
        {preserveScroll: true}
    )

    // } else {
    //     updateLineLocal(id, quantity)
    // }
}

const sendCartViaWhatsApp = async () => {
    await startWhatsAppCheckout({
        mode: 'cart',
        channel: 'web',
    })
}

const applyCoupon = () => {
    router.post(
        '/cart/coupon',
        {code: couponCode.value},
        {preserveScroll: true}
    )
}

const removeCoupon = () => {
    router.delete('/cart/coupon', {preserveScroll: true})
}

const minimumRequirement = computed(() => props.minimum_cart_requirement)
const minimumMessage = computed(() => {
    if (!minimumRequirement.value) {
        return null
    }
    if (minimumRequirement.value.message) {
        return minimumRequirement.value.message
    }
    if (minimumRequirement.value.threshold) {
        return t('Add at least {amount} after discounts to continue.', {
            amount: displayPrice(minimumRequirement.value.threshold),
        })
    }
    return null
})
const canCheckout = computed(() => (!minimumRequirement.value || minimumRequirement.value.passes) && props.lines.length > 0)

const freeShippingThreshold = 50
const freeShippingRemaining = computed(() => Math.max(0, freeShippingThreshold - props.subtotal))
const freeShippingPercent = computed(() => Math.min(100, (props.subtotal / freeShippingThreshold) * 100))

const { recentlyViewed } = useRecentlyViewed()
</script>
