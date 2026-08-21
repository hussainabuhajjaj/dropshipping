<template>
  <StorefrontLayout>
    <div class="min-h-screen bg-[#f7f4ef] pb-24 sm:pb-28">
      <div class="mx-auto max-w-7xl space-y-4 px-3 pt-4 sm:space-y-5 sm:px-4 sm:pt-5 lg:px-6">

        <!-- Header -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 class="text-xl font-black tracking-[-0.03em] text-slate-900 sm:text-2xl">
              {{ query ? t(':query', { query }) : t('All products') }}
            </h1>
            <p class="mt-0.5 text-sm text-slate-500">
              {{ t(':count results', { count: resultsPager.total ?? 0 }) }}
            </p>
          </div>
          <div v-if="suggestion" class="flex items-center gap-1.5 rounded-full bg-[#fff4e5] px-4 py-1.5 text-sm">
            <span class="text-[#92400e]">{{ t('Did you mean') }}</span>
            <Link :href="`/search?q=${encodeURIComponent(suggestion)}`" class="font-bold text-[#d97706] underline transition hover:text-slate-950">
              {{ suggestion }}
            </Link>
            <span class="text-[#d97706]">?</span>
          </div>
        </div>

        <!-- Toolbar -->
        <BrowseToolbar
          :total-count="resultsPager.total ?? 0"
          :search="query"
          :sort="currentSort"
          :sort-options="sortOptions"
          :show-filter-button="false"
          search-placeholder="Search products..."
          @update:search="onSearchChange"
          @update:sort="onSortChange"
          @submit-search="onSubmitSearch"
        />

        <!-- Product grid -->
        <div v-if="results.length">
          <div class="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-3 xl:grid-cols-4">
            <ProductCard
              v-for="product in results"
              :key="product.id"
              :product="product"
              :currency="currency"
            />
          </div>

          <!-- Pagination -->
          <div class="mt-6 flex flex-col items-center gap-4 rounded-lg border border-[#e7ded1] bg-white px-4 py-4 shadow-sm sm:flex-row sm:justify-between">
            <p class="text-xs text-slate-500">
              {{ t('Showing :from–:to of :total', {
                from: resultsPager.from ?? 1,
                to: resultsPager.to ?? results.length,
                total: resultsPager.total ?? results.length,
              }) }}
            </p>

            <div class="flex items-center gap-2">
              <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[#e7ded1] text-slate-600 transition hover:bg-[#fff4e5] hover:text-slate-900 disabled:opacity-30 disabled:cursor-not-allowed"
                :disabled="(resultsPager.current_page ?? 1) <= 1"
                @click="goToPage((resultsPager.current_page ?? 1) - 1)"
              >
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
              </button>

              <div class="flex items-center gap-1">
                <button
                  v-for="p in visiblePages"
                  :key="p"
                  type="button"
                  class="inline-flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold transition"
                  :class="p === (resultsPager.current_page ?? 1)
                    ? 'bg-[#f59e0b] text-slate-950'
                    : 'text-slate-600 hover:bg-[#fff4e5] hover:text-slate-900'"
                  @click="goToPage(p)"
                >
                  {{ p }}
                </button>
              </div>

              <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[#e7ded1] text-slate-600 transition hover:bg-[#fff4e5] hover:text-slate-900 disabled:opacity-30 disabled:cursor-not-allowed"
                :disabled="(resultsPager.current_page ?? 1) >= (resultsPager.last_page ?? 1)"
                @click="goToPage((resultsPager.current_page ?? 1) + 1)"
              >
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <EmptyState
          v-else
          :eyebrow="t('Search')"
          :title="t('Nothing matched that search')"
          :message="t('Try a different keyword or browse curated collections instead.')"
          class="rounded-lg border border-[#e7ded1] bg-white p-8 shadow-sm"
        >
          <template #actions>
            <Link href="/products" class="inline-flex min-h-11 items-center rounded-lg bg-[#f59e0b] px-6 text-sm font-bold text-slate-950 transition hover:bg-[#d97706]">
              {{ t('Browse catalog') }}
            </Link>
            <Link href="/support" class="inline-flex min-h-11 items-center rounded-lg border border-[#e7ded1] bg-[#fffaf4] px-6 text-sm font-bold text-slate-700 transition hover:border-slate-300">
              {{ t('Ask for help') }}
            </Link>
          </template>
        </EmptyState>
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ProductCard from '@/Components/ProductCard.vue'
import EmptyState from '@/Components/EmptyState.vue'
import BrowseToolbar from '@/Components/storefront/BrowseToolbar.vue'
import { Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useTranslations } from '@/i18n'

const { t } = useTranslations()

const props = defineProps({
  results: { type: Object, default: () => ({ data: [] }) },
  query: { type: String, default: '' },
  currency: { type: String, default: 'USD' },
  suggestion: { type: String, default: null },
})

const currentSort = ref('relevance')

const sortOptions = [
  { value: 'relevance', label: t('Relevance') },
  { value: 'price_asc', label: t('Price: Low to High') },
  { value: 'price_desc', label: t('Price: High to Low') },
  { value: 'newest', label: t('Newest') },
]

const query = computed(() => props.query ?? '')
const resultsPager = computed(() => props.results ?? { data: [] })
const results = computed(() => resultsPager.value.data ?? [])

const visiblePages = computed(() => {
  const current = resultsPager.value.current_page ?? 1
  const last = resultsPager.value.last_page ?? 1
  const pages = []
  const start = Math.max(1, current - 2)
  const end = Math.min(last, current + 2)
  for (let i = start; i <= end; i++) {
    pages.push(i)
  }
  return pages
})

const goToPage = (page) => {
  if (page < 1 || page > (resultsPager.value.last_page ?? 1)) return
  router.get('/search', { q: props.query, page, sort: currentSort.value }, { preserveState: true, preserveScroll: true })
}

const onSearchChange = (val) => {
  router.get('/search', { q: val, sort: currentSort.value }, { preserveState: true, preserveScroll: true })
}

const onSortChange = (val) => {
  currentSort.value = val
  router.get('/search', { q: props.query, sort: val }, { preserveState: true, preserveScroll: true })
}

const onSubmitSearch = () => {
  router.get('/search', { q: props.query, sort: currentSort.value }, { preserveScroll: true })
}
</script>
