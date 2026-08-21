<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const props = defineProps({
  popularSearches: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const searches = computed(() => props.popularSearches.slice(0, 10))

const promises = computed(() => [
  {
    title: t('Secure checkout'),
    body: t('Cards and mobile payments are protected.'),
    icon: 'shield',
  },
  {
    title: t('Tracked delivery'),
    body: t('Follow orders from warehouse to your door.'),
    icon: 'truck',
  },
  {
    title: t('Easy support'),
    body: t('Clear help before and after purchase.'),
    icon: 'chat',
  },
])
</script>

<template>
  <section class="grid gap-3 lg:grid-cols-[1fr_0.8fr]">
    <div class="grid gap-2 sm:grid-cols-3">
      <article
        v-for="promise in promises"
        :key="promise.title"
        class="rounded-lg border border-[#eee6da] bg-white p-4 shadow-sm"
      >
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-[#fff4df] text-[#d97706]">
          <svg v-if="promise.icon === 'shield'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-2.8 8.4-7 10-4.2-1.6-7-5.5-7-10V6l7-3z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
          </svg>
          <svg v-else-if="promise.icon === 'truck'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v10H3zM14 10h4l3 3v4h-7z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM18 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4z" />
          </svg>
          <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a8 8 0 0 1-8 8H7l-4 3 1.5-5.2A8 8 0 1 1 21 12z" />
          </svg>
        </span>
        <p class="mt-3 text-sm font-black text-slate-950">{{ promise.title }}</p>
        <p class="mt-1 text-xs font-medium leading-5 text-slate-500">{{ promise.body }}</p>
      </article>
    </div>

    <div class="rounded-lg border border-[#eee6da] bg-white p-4 shadow-sm">
      <p class="text-[0.66rem] font-black uppercase tracking-[0.2em] text-slate-400">{{ t('Trending searches') }}</p>
      <div v-if="searches.length" class="mt-3 flex flex-wrap gap-2">
        <Link
          v-for="search in searches"
          :key="search.query"
          :href="search.href"
          class="rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-bold text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-950 active:scale-95"
        >
          {{ search.query }}
        </Link>
      </div>
      <div v-else class="mt-3 flex flex-wrap gap-2">
        <Link href="/products?sort=trending" class="rounded-full border border-slate-200 px-3.5 py-1.5 text-xs font-bold text-slate-600">
          {{ t('Trending now') }}
        </Link>
        <Link href="/products?sort=newest" class="rounded-full border border-slate-200 px-3.5 py-1.5 text-xs font-bold text-slate-600">
          {{ t('New arrivals') }}
        </Link>
        <Link href="/products?sort=price-asc" class="rounded-full border border-slate-200 px-3.5 py-1.5 text-xs font-bold text-slate-600">
          {{ t('Best value') }}
        </Link>
      </div>
    </div>
  </section>
</template>
