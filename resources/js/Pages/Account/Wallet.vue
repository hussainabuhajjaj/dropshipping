<template>
  <StorefrontLayout>
    <div class="space-y-8">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Account</p>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Wallet</h1>
          <p class="text-sm text-slate-500">Manage gift cards and coupons like Noon.</p>
        </div>
        <Link href="/account" class="btn-ghost text-sm">Back to profile</Link>
      </div>

      <section class="grid gap-6 lg:grid-cols-2">
        <div class="card space-y-6 p-6">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Gift cards</h2>
            <p class="text-sm text-slate-500">Redeem and track your balances.</p>
          </div>

          <div v-if="giftCards.length" class="space-y-3">
            <div v-for="card in giftCards" :key="card.id" class="rounded-xl border border-slate-100 p-4 text-sm">
              <p class="font-semibold text-slate-900">{{ card.code }}</p>
              <p class="text-slate-500">
                Balance: {{ displayCardBalance(card) }}
              </p>
              <p class="text-slate-500">Status: {{ card.status }}</p>
              <p v-if="card.expires_at" class="text-xs text-slate-400">
                Expires {{ formatDate(card.expires_at) }}
              </p>
            </div>
          </div>
          <EmptyState
            v-else
            variant="compact"
            eyebrow="Gift cards"
            title="No gift cards yet"
            message="Redeem a gift card to see your balance here."
          />

          <form class="flex flex-col gap-3" @submit.prevent="redeemGiftCard">
            <input v-model="giftCardForm.code" type="text" placeholder="Gift card code" class="input-base" />
            <button type="submit" class="btn-secondary" :disabled="giftCardForm.processing">
              {{ giftCardForm.processing ? 'Redeeming...' : 'Redeem gift card' }}
            </button>
          </form>
        </div>

        <div class="card space-y-6 p-6">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Coupons</h2>
            <p class="text-sm text-slate-500">Save coupons to use at checkout.</p>
          </div>

          <div v-if="savedCoupons.length" class="space-y-3">
            <div v-for="coupon in savedCoupons" :key="coupon.id" class="rounded-xl border border-slate-100 p-4 text-sm">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p class="font-semibold text-slate-900">{{ coupon.coupon?.code }}</p>
                  <p class="text-slate-500">{{ coupon.coupon?.description || 'Saved coupon' }}</p>
                  <p class="text-slate-500">
                    {{ formatCoupon(coupon.coupon) }}
                  </p>
                </div>
                <button type="button" class="btn-ghost text-xs" @click="removeCoupon(coupon.id)">Remove</button>
              </div>
            </div>
          </div>
          <EmptyState
            v-else
            variant="compact"
            eyebrow="Coupons"
            title="No saved coupons yet"
            message="Save a coupon to apply it quickly at checkout."
          />

          <div v-if="availableCoupons.length" class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Available</p>
            <div v-for="coupon in availableCoupons" :key="coupon.id" class="rounded-xl border border-slate-100 p-4 text-sm">
              <p class="font-semibold text-slate-900">{{ coupon.code }}</p>
              <p class="text-slate-500">{{ coupon.description || 'Limited time offer.' }}</p>
              <p class="text-slate-500">{{ formatCoupon(coupon) }}</p>
            </div>
          </div>

          <form class="flex flex-col gap-3" @submit.prevent="saveCoupon">
            <input v-model="couponForm.code" type="text" placeholder="Coupon code" class="input-base" />
            <button type="submit" class="btn-secondary" :disabled="couponForm.processing">
              {{ couponForm.processing ? 'Saving...' : 'Save coupon' }}
            </button>
          </form>
        </div>
      </section>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { convertCurrency, formatCurrency } from '@/utils/currency.js'

function displayCardBalance(card) {
  return formatCurrency(convertCurrency(Number(card.balance ?? 0), 'USD', card.currency), card.currency)
}
import { Link, router, useForm } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'

const props = defineProps({
  giftCards: { type: Array, default: () => [] },
  savedCoupons: { type: Array, default: () => [] },
  availableCoupons: { type: Array, default: () => [] },
})

const giftCardForm = useForm({
  code: '',
})

const couponForm = useForm({
  code: '',
})

const redeemGiftCard = () => {
  giftCardForm.post('/account/gift-cards/redeem', {
    preserveScroll: true,
    onSuccess: () => giftCardForm.reset(),
  })
}

const saveCoupon = () => {
  couponForm.post('/account/coupons/save', {
    preserveScroll: true,
    onSuccess: () => couponForm.reset(),
  })
}

const removeCoupon = (id) => {
  router.delete(`/account/coupons/${id}`, { preserveScroll: true })
}

const formatDate = (value) => {
  if (! value) {
    return '-'
  }
  return new Date(value).toLocaleDateString()
}

const formatCoupon = (coupon) => {
  if (! coupon) {
    return ''
  }
  if (coupon.type === 'fixed') {
    return `Save ${coupon.amount}`
  }
  return `Save ${Number(coupon.amount).toFixed(0)}%`
}
</script>
