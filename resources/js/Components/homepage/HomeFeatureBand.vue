<script setup>
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

defineProps({
  newsletterEmail: { type: String, default: '' },
  newsletterSubmitted: { type: Boolean, default: false },
})

const emit = defineEmits(['update:newsletterEmail', 'submit-newsletter'])
const { t } = useTranslations()

const updateNewsletterEmail = (event) => {
  emit('update:newsletterEmail', event.target.value)
}
</script>

<template>
  <section class="mt-8 overflow-hidden rounded-lg bg-[#020617] text-white shadow-sm">
    <div class="grid gap-px bg-white/10 lg:grid-cols-[1.25fr_0.75fr]">
      <div class="bg-[#020617] p-5 sm:p-7">
        <p class="text-[0.62rem] font-black uppercase tracking-[0.24em] text-[#f59e0b]">{{ t('Why Simbazu') }}</p>
        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div class="space-y-2">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-[#f59e0b]">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
            </span>
            <p class="text-sm font-bold">{{ t('Free Shipping') }}</p>
            <p class="text-[0.68rem] leading-snug text-white/60">{{ t('On orders over $50') }}</p>
          </div>
          <div class="space-y-2">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-[#f59e0b]">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0 1 12 2.944a11.955 11.955 0 0 1-8.618 3.04A12.02 12.02 0 0 0 3 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </span>
            <p class="text-sm font-bold">{{ t('Secure Payments') }}</p>
            <p class="text-[0.68rem] leading-snug text-white/60">{{ t('256-bit SSL encryption') }}</p>
          </div>
          <div class="space-y-2">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-[#f59e0b]">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-4-4 4m0 0-4-4m4 4V4" />
              </svg>
            </span>
            <p class="text-sm font-bold">{{ t('Easy Returns') }}</p>
            <p class="text-[0.68rem] leading-snug text-white/60">{{ t('30-day return policy') }}</p>
          </div>
          <div class="space-y-2">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-[#f59e0b]">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
              </svg>
            </span>
            <p class="text-sm font-bold">{{ t('Real-time Tracking') }}</p>
            <p class="text-[0.68rem] leading-snug text-white/60">{{ t('From warehouse to your door') }}</p>
          </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div class="rounded-lg bg-white/[0.08] px-3 py-4">
            <p class="text-xl font-black">10,000+</p>
            <p class="text-[0.58rem] font-bold uppercase tracking-[0.14em] text-white/45">{{ t('Products') }}</p>
          </div>
          <div class="rounded-lg bg-white/[0.08] px-3 py-4">
            <p class="text-xl font-black">5,000+</p>
            <p class="text-[0.58rem] font-bold uppercase tracking-[0.14em] text-white/45">{{ t('Happy Customers') }}</p>
          </div>
          <div class="rounded-lg bg-white/[0.08] px-3 py-4">
            <p class="text-xl font-black">4.8</p>
            <p class="text-[0.58rem] font-bold uppercase tracking-[0.14em] text-white/45">{{ t('Average Rating') }}</p>
          </div>
          <div class="rounded-lg bg-white/[0.08] px-3 py-4">
            <p class="text-xl font-black">50+</p>
            <p class="text-[0.58rem] font-bold uppercase tracking-[0.14em] text-white/45">{{ t('Countries Shipped') }}</p>
          </div>
        </div>
      </div>

      <div class="grid gap-px bg-white/10">
        <div class="bg-[#f0ebe3] p-5 text-slate-950 sm:p-7">
          <p class="text-[0.62rem] font-black uppercase tracking-[0.22em] text-[#d97706]">{{ t('Stay in the loop') }}</p>
          <p class="mt-2 text-sm font-semibold text-slate-800">{{ t('Get exclusive offers and updates') }}</p>
          <p class="mt-1 text-xs leading-relaxed text-slate-600">{{ t('New drops, promos, and delivery updates straight to your inbox.') }}</p>
          <form class="mt-4 flex gap-2" @submit.prevent="emit('submit-newsletter')">
            <input
              :value="newsletterEmail"
              type="email"
              required
              :placeholder="t('Your email address')"
              class="min-w-0 flex-1 rounded-lg border border-[#ded6ca] bg-white px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:border-[#f59e0b] focus:outline-none focus:ring-4 focus:ring-amber-200/50"
              @input="updateNewsletterEmail"
            />
            <button type="submit" class="shrink-0 rounded-lg bg-[#020617] px-4 py-2 text-xs font-bold text-white transition hover:bg-slate-800 active:scale-95">
              {{ newsletterSubmitted ? t('Subscribed!') : t('Subscribe') }}
            </button>
          </form>
        </div>

        <Link
          href="/download"
          class="flex items-center justify-between gap-4 bg-white p-5 text-slate-950 transition hover:bg-[#fffaf3] sm:p-7"
        >
          <div class="flex items-center gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-slate-950 shadow-sm">
              <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-black">{{ t('Get the Simbazu App') }}</p>
              <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ t('Faster checkout, real-time tracking, mobile-only deals') }}</p>
            </div>
          </div>
          <svg class="h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12l-7.5 7.5" />
          </svg>
        </Link>
      </div>
    </div>
  </section>
</template>
