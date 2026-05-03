<template>
  <section class="space-y-3 sm:space-y-4">
    <div class="flex items-end justify-between gap-3">
      <div>
        <p class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#ff6b35]">{{ t('Thumb-friendly discovery') }}</p>
        <h2 class="text-lg font-black tracking-[-0.03em] text-slate-950 sm:text-xl">{{ t('Shop by mood, not by menu') }}</h2>
      </div>
      <Link href="/products" class="shrink-0 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-slate-500 transition hover:text-slate-950 sm:text-xs sm:tracking-[0.18em]">
        {{ t('See all') }}
      </Link>
    </div>

    <div class="flex snap-x gap-2.5 overflow-x-auto pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:gap-3">
      <Link
        v-for="category in categories"
        :key="category.id || category.slug || category.name"
        :href="category.href"
        class="group w-[92px] shrink-0 snap-start rounded-[1.3rem] border border-[#efe7da] bg-white p-1.5 shadow-[0_12px_30px_rgba(15,23,42,0.06)] transition hover:-translate-y-1 hover:border-[#ffd9cb] sm:w-[104px] sm:rounded-[1.7rem] sm:p-2"
      >
        <div class="overflow-hidden rounded-[1rem] bg-[#f7efe4] sm:rounded-[1.3rem]">
          <img
            v-if="category.image"
            :src="category.image"
            :alt="category.name"
            class="aspect-square w-full object-cover transition duration-300 group-hover:scale-105"
            loading="lazy"
          />
          <div v-else class="flex aspect-square items-center justify-center bg-gradient-to-br from-[#ffe6c8] via-[#fff6ea] to-[#ffe9f0] text-lg font-black text-[#ff6b35] sm:text-xl">
            {{ category.short }}
          </div>
        </div>
        <div class="px-1 pb-1 pt-2.5 sm:pt-3">
          <p class="line-clamp-2 text-[0.78rem] font-bold leading-4 text-slate-900 sm:text-sm">{{ category.name }}</p>
          <p class="mt-1 text-[0.68rem] font-medium text-slate-500">{{ category.meta }}</p>
        </div>
      </Link>
    </div>
  </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

defineProps({
  categories: { type: Array, default: () => [] },
})

const { t } = useTranslations()
</script>
