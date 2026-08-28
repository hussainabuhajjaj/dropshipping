<template>
  <StorefrontLayout>
    <div class="space-y-5 pb-10 sm:space-y-8">
      <section class="rounded-[1.8rem] bg-[#111111] px-5 py-5 text-white shadow-[0_20px_48px_rgba(15,23,42,0.16)]">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#facc15]">Account</p>
            <h1 class="mt-2 text-[1.95rem] font-black tracking-[-0.04em] sm:text-[2.2rem]">Wallet</h1>
            <p class="mt-2 max-w-xl text-sm leading-6 text-white/72">Coupons and gift cards stay visible so savings feel collectible, not buried in checkout.</p>
          </div>
          <Link href="/account" class="inline-flex min-h-11 items-center justify-center rounded-full border border-white/15 bg-white/8 px-4 text-sm font-semibold text-white transition hover:bg-white/12">Back to profile</Link>
        </div>
      </section>

      <div class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <div class="shrink-0 rounded-full bg-[#fff4e8] px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-[#c55b24]">{{ giftCards.length }} gift cards</div>
        <div class="shrink-0 rounded-full bg-white px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-slate-500 ring-1 ring-[#eadfce]">{{ savedCoupons.length }} saved coupons</div>
        <div class="shrink-0 rounded-full bg-white px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-slate-500 ring-1 ring-[#eadfce]">{{ availableCoupons.length }} available offers</div>
      </div>

      <section class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-[1.8rem] border border-[#eadfce] bg-white p-5 shadow-[0_16px_38px_rgba(15,23,42,0.05)] sm:p-6">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Gift cards</h2>
            <p class="text-sm text-slate-500">Redeem and track your balances.</p>
          </div>

          <div v-if="giftCards.length" class="space-y-3">
            <div v-for="card in giftCards" :key="card.id" class="rounded-[1.4rem] border border-[#eadfce] bg-[#fffaf4] p-4 text-sm">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                  <p class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-slate-400">Gift card</p>
                  <p class="font-semibold text-slate-900">{{ card.code }}</p>
                  <p class="text-base font-black tracking-[-0.02em] text-slate-900">{{ displayCardBalance(card) }}</p>
                  <p class="text-slate-500">Status: {{ card.status }}</p>
                </div>
                <span class="rounded-full bg-[#111111] px-3 py-1 text-[0.64rem] font-bold uppercase tracking-[0.16em] text-white">Stored value</span>
              </div>
              <p v-if="card.expires_at" class="mt-2 text-xs text-slate-400">
                Expires {{ formatDate(card.expires_at) }}
              </p>
            </div>
          </div>
          <EmptyState
            v-else
            variant="compact"
            :eyebrow="t('Gift cards')"
            :title="t('No gift cards yet')"
            :message="t('Redeem a gift card to see your balance here.')"
          />

          <form class="space-y-3 rounded-[1.4rem] border border-dashed border-[#eadfce] bg-[#fffaf4] p-4" @submit.prevent="redeemGiftCard">
            <input v-model.trim="giftCardForm.code" type="text" placeholder="Gift card code" class="input-base" />
            <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-full bg-[#111111] px-5 text-sm font-bold uppercase tracking-[0.12em] text-white transition hover:bg-[#262626]" :disabled="giftCardForm.processing">
              {{ giftCardForm.processing ? 'Redeeming...' : 'Redeem gift card' }}
            </button>
          </form>
        </div>

        <div class="rounded-[1.8rem] border border-[#eadfce] bg-[#fffaf4] p-5 shadow-[0_16px_38px_rgba(15,23,42,0.05)] sm:p-6">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Coupons</h2>
            <p class="text-sm text-slate-500">Save coupons to use at checkout.</p>
          </div>

          <div v-if="savedCoupons.length" class="space-y-3">
            <div v-for="coupon in savedCoupons" :key="coupon.id" class="rounded-[1.4rem] border border-[#eadfce] bg-white p-4 text-sm">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-slate-400">Saved coupon</p>
                  <p class="mt-1 font-semibold text-slate-900">{{ coupon.coupon?.code }}</p>
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
            <div v-for="coupon in availableCoupons" :key="coupon.id" class="rounded-[1.4rem] border border-[#f7c16d] bg-[#fff8ee] p-4 text-sm">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="font-semibold text-slate-900">{{ coupon.code }}</p>
                  <p class="text-slate-500">{{ coupon.description || 'Limited time offer.' }}</p>
                  <p class="text-slate-500">{{ formatCoupon(coupon) }}</p>
                </div>
                <span class="rounded-full bg-[#111111] px-3 py-1 text-[0.64rem] font-bold uppercase tracking-[0.16em] text-white">Available now</span>
              </div>
            </div>
          </div>

          <form class="space-y-3 rounded-[1.4rem] border border-dashed border-[#eadfce] bg-white p-4" @submit.prevent="saveCoupon">
            <input v-model.trim="couponForm.code" type="text" :placeholder="t('Coupon code')" class="input-base" />
            <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-full bg-[#ff6b35] px-5 text-sm font-bold uppercase tracking-[0.12em] text-white transition hover:bg-[#ea5b26]" :disabled="couponForm.processing">
              {{ couponForm.processing ? t('Saving...') : t('Save coupon') }}
            </button>
          </form>
        </div>
      </section>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { useUserPreferences } from '@/composables/useUserPreferences.js'
import { useTranslations } from '@/i18n'

const { formatCurrency, convertCurrency } = useUserPreferences()
const { t } = useTranslations()
function displayCardBalance(card) {
  return formatCurrency(convertCurrency(Number(card.balance ?? 0), card.currency, 'XOF'), 'XOF')
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
