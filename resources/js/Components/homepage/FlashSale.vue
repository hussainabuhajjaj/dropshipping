<template>
  <section class="overflow-hidden rounded-[1.6rem] bg-[#111111] text-white shadow-[0_28px_70px_rgba(15,23,42,0.18)] sm:rounded-[2rem]">
    <div class="grid gap-4 px-3.5 py-4 sm:px-6 sm:py-5 lg:grid-cols-[0.36fr_0.64fr] lg:items-center lg:px-8">
      <div class="space-y-3.5 sm:space-y-4">
        <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-2 text-[0.62rem] font-bold uppercase tracking-[0.18em] text-white/88 sm:text-[0.68rem] sm:tracking-[0.24em]">
          <span class="inline-flex h-2.5 w-2.5 rounded-full bg-[#ff6b35]"></span>
          {{ t('Flash sale') }}
        </div>
        <div>
          <h2 class="text-[1.7rem] font-black tracking-[-0.05em] sm:text-3xl">{{ t('The scroll-stopper deals') }}</h2>
          <p class="mt-2 max-w-sm text-[0.92rem] leading-5 text-white/72 sm:text-sm sm:leading-6">
            {{ t('Anchor discount, low-stock urgency, and fast product exposure in one swipeable lane.') }}
          </p>
        </div>
        <div class="rounded-[1.2rem] border border-white/10 bg-white/8 p-3.5 backdrop-blur sm:rounded-[1.5rem] sm:p-4">
          <p class="text-[0.62rem] uppercase tracking-[0.24em] text-white/55">{{ t('Sale ends in') }}</p>
          <p class="mt-2 text-[1.45rem] font-black tracking-[0.06em] text-[#facc15] sm:text-2xl sm:tracking-[0.08em]">{{ countdown }}</p>
        </div>
        <Link href="/products?promotion_type=flash_sale" class="inline-flex min-h-11 items-center justify-center rounded-full bg-[#ff6b35] px-4 text-[0.82rem] font-bold text-white shadow-[0_12px_28px_rgba(255,107,53,0.38)] transition hover:-translate-y-0.5 hover:bg-[#ff5420] sm:min-h-12 sm:px-5 sm:text-sm">
          {{ t('Open flash lane') }}
        </Link>
      </div>

      <div class="flex gap-2.5 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:gap-3">
        <ProductCard
          v-for="product in deals"
          :key="product.id"
          :product="product"
          :currency="currency"
          class="w-[170px] shrink-0 sm:w-[220px] lg:w-[240px]"
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
