<template>
    <StorefrontLayout>
        <div class="space-y-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ t('Your cart') }}</h1>
                <Link href="/products" class="btn-ghost">{{ t('Continue shopping') }}</Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1.6fr,1fr]">
                <div v-if="lines.length" class="space-y-3">
                    <CartLineItem
                        v-for="line in lines"
                        :key="line.id"
                        :line="line"
                        :currency="currency"
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

                <aside class="card-muted space-y-4 p-5">
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
                        <div v-if="appliedPromotions && appliedPromotions.length" class="applied-promotions">
                            <div class="text-xs font-semibold text-green-700 mb-1">{{ t('Promotions applied:') }}</div>
                            <ul class="space-y-1">
                                <li v-for="promo in appliedPromotions" :key="promo.id" class="text-xs text-slate-700">
                                    <span class="font-semibold">{{ promo.name }}</span>
                                    <span class="ml-1">({{
                                            promo.type === 'flash_sale' ? t('Flash Sale') : t('Auto Discount')
                                        }})</span>
                                    <span class="ml-2" v-if="promo.value_type === 'percent'">-{{ promo.value }}%</span>
                                    <span class="ml-2" v-else-if="promo.value_type === 'amount'">-{{
                                            displayPrice(promo.value)
                                        }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <!--// Accept appliedPromotions as a prop (array of applied promotions)-->
                    <!--const appliedPromotions = computed(() => Array.isArray(props.appliedPromotions) ? props.appliedPromotions : [])-->

                    <div class="flex items-center justify-between text-sm">
                        <span>{{ t('Subtotal') }}</span>
                        <span class="font-semibold text-slate-900">{{ displayPrice(subtotal) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm text-green-700" v-if="discount > 0">
                        <span>{{ t('Discount') }}</span>
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
                    <button
                        :disabled="lines.length === 0"
                        class="btn-primary mt-4 w-full"
                        :class="{ 'cursor-not-allowed opacity-60': lines.length === 0 }"
                        @click="$inertia.visit('/checkout')"
                    >
                        {{ t('Proceed to checkout') }}
                    </button>
                    <p class="text-xs text-slate-500">
                        {{
                            t("Delivery to Cote d'Ivoire with transparent customs. Expect tracking within 24 to 48 hours after fulfillment.")
                        }}
                    </p>
                </aside>
            </div>
        </div>
    </StorefrontLayout>
</template>

<script setup>
import {convertCurrency, formatCurrency} from '@/utils/currency.js'

// Helper to display price in selected currency
function displayPrice(amount) {
    return formatCurrency(convertCurrency(amount, 'USD', props.currency), props.currency)
}

import {Link, router} from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import CartLineItem from '@/Components/CartLineItem.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {ref, computed} from 'vue'
import {usePersistentCart} from '@/composables/usePersistentCart.js'
import {useTranslations} from '@/i18n'

const props = defineProps({
    lines: {type: Array, required: true},
    currency: {type: String, default: 'USD'},
    subtotal: {type: Number, default: 0},
    shipping: {type: Number, default: 0},
    discount: {type: Number, default: 0},
    coupon: {type: Object, default: null},
    user: {type: Object, default: null},
})

const {t} = useTranslations()

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
</script>
