<template>
  <StorefrontLayout>
    <section class="space-y-6">
  <div class="space-y-2">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Search') }}</p>
    <h1 class="text-3xl font-semibold tracking-tight text-slate-900">
      {{ t('Results for ":query"', { query: query || t('All products') }) }}
    </h1>
    <p class="text-sm text-slate-600">
      {{ t(':count items found', { count: resultsPager.total ?? 0 }) }}
    </p>
  </div>

  <div v-if="results.length" class="space-y-4">
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <ProductCard
        v-for="product in results"
        :key="product.id"
        :product="product"
        :currency="currency"
      />
    </div>
    <div class="pager-bar">
      <div class="pager-meta">
        <p class="pager-strong">
          {{ t('Showing :from–:to of :total', {
            from: resultsPager.from ?? 1,
            to: resultsPager.to ?? results.length,
            total: resultsPager.total ?? results.length,
          }) }}
        </p>
        <p class="pager-muted">
          {{ t('Page :page of :pages', { page: resultsPager.current_page ?? 1, pages: resultsPager.last_page ?? 1 }) }}
        </p>
      </div>
      <div class="pager-actions">
        <button
          type="button"
          class="pager-button"
          :disabled="resultsPager.current_page <= 1"
          @click="goToPage((resultsPager.current_page ?? 1) - 1)"
        >
          ‹ {{ t('Prev') }}
        </button>

        <div class="pager-pill">
          <label class="sr-only" :for="`search-page-select`">{{ t('Go to page') }}</label>
          <select
            :id="`search-page-select`"
            :value="resultsPager.current_page ?? 1"
            @change="goToPage(Number($event.target.value))"
          >
            <option v-for="pageNumber in resultsPager.last_page ?? 1" :key="`page-${pageNumber}`" :value="pageNumber">
              {{ t('Page :page', { page: pageNumber }) }}
            </option>
          </select>
        </div>

        <button
          type="button"
          class="pager-button"
          :disabled="! hasMore"
          @click="goToPage((resultsPager.current_page ?? 1) + 1)"
        >
          {{ t('Next') }} ›
        </button>
      </div>
    </div>
  </div>
      <EmptyState
        v-else
        :eyebrow="t('Search')"
        :title="t('Nothing matched that search')"
        :message="t('Try a different keyword or browse curated collections instead.')"
      >
        <template #actions>
          <Link href="/products" class="btn-primary">{{ t('Browse catalog') }}</Link>
          <Link href="/support" class="btn-ghost">{{ t('Ask for help') }}</Link>
        </template>
      </EmptyState>
    </section>
  </StorefrontLayout>
</template>

<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ProductCard from '@/Components/ProductCard.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useTranslations } from '@/i18n'

const { t } = useTranslations()

const props = defineProps({
  results: { type: Object, default: () => ({ data: [] }) },
  query: { type: String, default: '' },
  currency: { type: String, default: 'USD' },
})

const query = computed(() => props.query ?? '')
const resultsPager = computed(() => props.results ?? { data: [] })
const results = computed(() => resultsPager.value.data ?? [])
const hasMore = computed(() => (resultsPager.value.current_page ?? 1) < (resultsPager.value.last_page ?? 1))

const goToPage = (page) => {
  if (page < 1 || page > (resultsPager.value.last_page ?? 1)) {
    return
  }
  router.get('/search', { q: props.query, page }, { preserveState: true })
}
</script>
