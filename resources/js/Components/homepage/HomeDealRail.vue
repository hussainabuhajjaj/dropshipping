<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import CompactProductCard from '@/Components/homepage/CompactProductCard.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  deals: { type: Array, default: () => [] },
  countdown: { type: String, default: '' },
  viewAllHref: { type: String, default: '/promotions/flash-sales' },
  currency: { type: String, default: 'USD' },
})

const emit = defineEmits(['quick-add'])
const { t } = useTranslations()
const visibleDeals = computed(() => props.deals.slice(0, 8))
</script>

<template>
  <section v-if="visibleDeals.length" class="overflow-hidden rounded-lg bg-slate-950 text-white shadow-sm">
    <div class="grid gap-4 p-4 lg:grid-cols-[18rem_minmax(0,1fr)] lg:p-5">
      <div class="flex flex-col justify-between gap-5">
        <div>
          <p class="inline-flex rounded-full bg-[#f59e0b] px-3 py-1 text-[0.66rem] font-black uppercase tracking-[0.18em] text-slate-950">
            {{ t('Flash deals') }}
          </p>
          <h2 class="mt-3 text-2xl font-black leading-tight">{{ t('Limited-time savings') }}</h2>
          <p class="mt-2 text-sm font-medium leading-6 text-white/68">
            {{ t('Short-time offers, useful markdowns, and quick picks before the day ends.') }}
          </p>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div class="rounded-lg border border-white/10 bg-white/8 p-3">
            <p class="text-[0.62rem] font-bold uppercase tracking-[0.16em] text-white/50">{{ t('Timer') }}</p>
            <p class="mt-1 text-sm font-black text-[#fbbf24]">{{ countdown || t('Today only') }}</p>
          </div>
          <Link
            :href="viewAllHref"
            class="rounded-lg bg-white p-3 text-slate-950 transition hover:bg-[#fff4df] active:scale-[0.99]"
          >
            <p class="text-[0.62rem] font-bold uppercase tracking-[0.16em] text-slate-400">{{ t('Deal zone') }}</p>
            <p class="mt-1 text-sm font-black">{{ t('View all') }}</p>
          </Link>
        </div>
      </div>

      <div class="grid grid-flow-col auto-cols-[10rem] gap-2 overflow-x-auto pb-1 sm:auto-cols-[12rem] lg:auto-cols-[13rem] [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <CompactProductCard
          v-for="product in visibleDeals"
          :key="product.id"
          :product="product"
          :currency="currency"
          @quick-add="emit('quick-add', $event)"
        />
      </div>
    </div>
  </section>
</template>
