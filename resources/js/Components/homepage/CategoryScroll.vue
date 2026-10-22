<template>
  <section class="space-y-2.5 sm:space-y-3">
    <div class="flex items-end justify-between gap-3">
      <div>
        <p class="text-[0.62rem] font-bold uppercase tracking-[0.22em] text-[#ff6b35]">{{ t('Shop by category') }}</p>
        <h2 class="text-[1rem] font-black tracking-[-0.03em] text-slate-950 sm:text-[1.1rem]">{{ t('Popular departments') }}</h2>
      </div>
      <Link href="/products" class="shrink-0 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-slate-500 transition hover:text-slate-950 sm:text-xs sm:tracking-[0.18em]">
        {{ t('See all') }}
      </Link>
    </div>

    <div class="grid grid-flow-col auto-cols-[82px] gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:auto-cols-[92px] sm:gap-2.5">
      <Link
        v-for="category in categories"
        :key="category.id || category.slug || category.name"
        :href="category.href"
        class="group shrink-0 rounded-[1.1rem] border border-[#efe7da] bg-white p-1.5 shadow-[0_10px_24px_rgba(15,23,42,0.05)] transition hover:-translate-y-1 hover:border-[#ffd9cb] sm:rounded-[1.25rem]"
      >
        <div class="overflow-hidden rounded-[0.9rem] bg-[#f7efe4] sm:rounded-[1rem]">
          <img
            v-if="category.image"
            :src="category.image"
            :alt="category.name"
            class="aspect-square w-full object-cover transition duration-300 group-hover:scale-105"
            loading="lazy"
          />
          <div v-else class="flex aspect-square items-center justify-center bg-gradient-to-br from-[#ffe6c8] via-[#fff6ea] to-[#ffe9f0] text-base font-black text-[#ff6b35] sm:text-lg">
            {{ category.short }}
          </div>
        </div>
        <div class="px-0.5 pb-0.5 pt-2">
          <p class="line-clamp-2 text-[0.7rem] font-bold leading-4 text-slate-900 sm:text-[0.78rem]">{{ category.name }}</p>
          <p class="mt-0.5 text-[0.62rem] font-medium text-slate-500">{{ category.meta }}</p>
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
