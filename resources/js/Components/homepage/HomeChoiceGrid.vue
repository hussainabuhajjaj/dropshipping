<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  season: { type: Object, required: true },
})

const { t } = useTranslations()
const visibleCategories = computed(() => props.categories.slice(0, 12))
</script>

<template>
  <section v-if="visibleCategories.length" class="rounded-lg border border-[#eee6da] bg-white p-4 shadow-sm sm:p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-[0.66rem] font-black uppercase tracking-[0.2em] text-[#d97706]">{{ t('Departments') }}</p>
        <h2 class="mt-1 text-xl font-black text-slate-950 sm:text-2xl">{{ t('Shop by category') }}</h2>
        <p class="mt-1 max-w-2xl text-sm font-medium leading-6 text-slate-600">
          {{ t('Jump into the most useful departments for today, this season, and everyday essentials.') }}
        </p>
      </div>
      <Link href="/products" class="inline-flex h-10 items-center rounded-md bg-slate-950 px-4 text-sm font-bold text-white transition hover:bg-slate-800 active:scale-95">
        {{ t('All products') }}
      </Link>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-12">
      <Link
        v-for="category in visibleCategories"
        :key="category.id || category.slug || category.name"
        :href="category.href"
        class="group rounded-lg border border-[#eee6da] bg-[#faf7f2] p-2 transition hover:border-[#f3c56e] hover:bg-white hover:shadow-sm active:scale-[0.99]"
      >
        <div class="overflow-hidden rounded-md bg-white">
          <img
            v-if="category.image"
            :src="category.image"
            :alt="category.name"
            class="aspect-square w-full object-cover transition duration-300 group-hover:scale-105"
            loading="lazy"
          />
          <div v-else class="flex aspect-square items-center justify-center bg-[#fff4df] text-xl font-black text-[#d97706]">
            {{ category.short }}
          </div>
        </div>
        <p class="mt-2 line-clamp-2 min-h-9 text-sm font-black leading-tight text-slate-900">{{ category.name }}</p>
        <p class="mt-1 text-[0.68rem] font-bold uppercase tracking-[0.12em] text-slate-400">{{ t(season.badge) }}</p>
      </Link>
    </div>
  </section>
</template>
