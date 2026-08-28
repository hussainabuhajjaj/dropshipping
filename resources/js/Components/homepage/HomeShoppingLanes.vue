<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const props = defineProps({
  lanes: { type: Array, default: () => [] },
  season: { type: Object, required: true },
})

const { t } = useTranslations()
const visibleLanes = computed(() => props.lanes.slice(0, 18))
</script>

<template>
  <section class="rounded-lg border border-[#eee6da] bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-[#f1e8dc] px-3 py-2.5 sm:px-4">
      <span class="shrink-0 rounded-md bg-slate-950 px-3 py-1.5 text-[0.66rem] font-black uppercase tracking-[0.16em] text-white">
        {{ t('Quick shop') }}
      </span>
      <div class="flex min-w-0 flex-1 gap-2 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <Link
          v-for="lane in visibleLanes"
          :key="lane.key || lane.href || lane.label"
          :href="lane.href"
          class="inline-flex min-h-11 shrink-0 items-center rounded-full border px-3.5 py-2 text-[0.72rem] font-black transition active:scale-95"
          :class="lane.tone === 'sale'
            ? 'border-[#f59e0b] bg-[#fff4df] text-[#9a5b00] hover:bg-[#ffe8ba]'
            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-950'"
        >
          {{ t(lane.label) }}
        </Link>
      </div>
    </div>

    <div class="grid gap-2 p-3 sm:grid-cols-3 sm:p-4">
      <Link
        href="/quick-shop/new-in"
        class="block min-h-11 rounded-md bg-[#f6efe4] px-4 py-3 transition hover:bg-[#fff4df] active:scale-[0.99]"
      >
        <p class="text-[0.64rem] font-black uppercase tracking-[0.18em] text-[#d97706]">{{ t('New In') }}</p>
        <p class="mt-1 text-sm font-black text-slate-950">{{ t('Fresh drops added for fast browsing') }}</p>
      </Link>
      <Link
        href="/quick-shop/sale"
        class="block min-h-11 rounded-md bg-slate-950 px-4 py-3 text-white transition hover:bg-slate-800 active:scale-[0.99]"
      >
        <p class="text-[0.64rem] font-black uppercase tracking-[0.18em] text-[#fbbf24]">{{ t('Sale') }}</p>
        <p class="mt-1 text-sm font-black">{{ t('Flash deals and limited offers') }}</p>
      </Link>
      <Link
        :href="season.href"
        class="block min-h-11 rounded-md bg-white px-4 py-3 ring-1 ring-[#eee6da] transition hover:bg-[#faf7f2] active:scale-[0.99]"
      >
        <p class="text-[0.64rem] font-black uppercase tracking-[0.18em] text-slate-400">{{ t(season.badge) }}</p>
        <p class="mt-1 text-sm font-black text-slate-950">{{ t('Seasonal picks ready to shop') }}</p>
      </Link>
    </div>
  </section>
</template>
