<template>
  <section class="overflow-hidden rounded-[1.35rem] bg-[#111111] text-white shadow-[0_24px_56px_rgba(15,23,42,0.18)] sm:rounded-[1.7rem]">
    <div class="grid gap-3 px-3 py-3 sm:px-4 sm:py-4 lg:grid-cols-[0.32fr_0.68fr] lg:items-center lg:px-5">
      <div class="space-y-3">
        <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-[0.58rem] font-bold uppercase tracking-[0.18em] text-white/88 sm:text-[0.62rem]">
          <span class="inline-flex h-2.5 w-2.5 rounded-full bg-[#ff6b35]"></span>
          {{ t('Flash sale') }}
        </div>
        <div>
          <h2 class="text-[1.2rem] font-black tracking-[-0.05em] sm:text-[1.5rem]">{{ t('Flash deals') }}</h2>
          <p class="mt-1 max-w-sm text-[0.74rem] leading-4 text-white/72 sm:text-[0.8rem] sm:leading-5">
            {{ t('Coupon-style offers, urgency, and swipeable deals.') }}
          </p>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div class="rounded-[1rem] border border-white/10 bg-white/8 p-2.5 backdrop-blur">
            <p class="text-[0.52rem] uppercase tracking-[0.18em] text-white/55">{{ t('Ends in') }}</p>
            <p class="mt-1 text-[1rem] font-black tracking-[0.04em] text-[#facc15] sm:text-[1.15rem]">{{ countdown }}</p>
          </div>
          <div class="rounded-[1rem] bg-[#ff6b35] p-2.5 text-white">
            <p class="text-[0.52rem] uppercase tracking-[0.18em] text-white/72">{{ t('Deal zone') }}</p>
            <p class="mt-1 text-[0.9rem] font-black">{{ t('Up to 70% off') }}</p>
          </div>
        </div>
        <Link href="/products?promotion_type=flash_sale" class="inline-flex min-h-10 items-center justify-center rounded-full bg-white px-4 text-[0.72rem] font-bold uppercase tracking-[0.12em] text-slate-950 transition hover:bg-[#fff3ed] sm:min-h-11 sm:text-[0.76rem]">
          {{ t('Open flash lane') }}
        </Link>
      </div>

      <div class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:gap-2.5">
        <ProductCard
          v-for="product in deals"
          :key="product.id"
          :product="product"
          :currency="currency"
          class="w-[154px] shrink-0 sm:w-[180px] lg:w-[188px]"
        />
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import ProductCard from '@/Components/homepage/ProductCard.vue'
import { useTranslations } from '@/i18n'
import { usePromoNow, formatCountdown } from '@/composables/usePromoCountdown.js'

const props = defineProps({
  deals: { type: Array, default: () => [] },
  currency: { type: String, default: 'USD' },
  endsAt: { type: String, default: null },
})

const { t } = useTranslations()
const now = usePromoNow()
const countdown = computed(() => formatCountdown(props.endsAt, now.value) || '00:59:59')
</script>
