<template>
  <StorefrontLayout>
    <section class="space-y-8">
      <div class="space-y-3">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Collection') }}</p>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900">{{ t('Shop the catalog') }}</h1>
          <p class="max-w-xl text-sm text-slate-500">
            {{ t("Curated picks with reliable delivery to Cote d'Ivoire. Duties and customs are disclosed before payment.") }}
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
          <span>{{ t(':count items', { count: products.length }) }}</span>
          <button
            type="button"
            class="btn-secondary px-3 py-2 text-xs lg:hidden"
            @click="filtersOpen = true"
          >
            {{ t('Filters') }}
          </button>
        </div>
        <div v-if="activeFilters.length" class="flex flex-wrap gap-2">
          <button
            v-for="filter in activeFilters"
            :key="filter.key"
            type="button"
            class="chip"
            @click="clearFilter(filter.key)"
          >
            {{ filter.label }}
            <span class="text-slate-400">x</span>
          </button>
          <button type="button" class="btn-ghost text-xs" @click="resetFilters">
            {{ t('Clear all') }}
          </button>
        </div>
      </div>

      <div class="grid gap-8 lg:grid-cols-[260px,1fr]">
        <aside class="card hidden h-fit space-y-4 p-5 lg:block">
          <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Filters') }}</p>
            <p class="text-sm text-slate-600">{{ t('Narrow results by category or price.') }}</p>
          </div>
          <form class="space-y-4" @submit.prevent="applyFilters">
            <div class="space-y-2">
              <label class="text-xs font-semibold text-slate-600">{{ t('Search') }}</label>
              <input v-model="form.q" type="search" :placeholder="t('Search products')" class="input-base" />
            </div>
            <div class="space-y-2">
              <label class="text-xs font-semibold text-slate-600">{{ t('Category') }}</label>
              <select v-model="form.category" class="input-base">
                <option value="">{{ t('All categories') }}</option>
                <option v-for="category in categories" :key="category.slug || category.name" :value="category.slug || category.name">
                  {{ category.name || category }}
                </option>
              </select>
            </div>
            <div class="space-y-2">
              <label class="text-xs font-semibold text-slate-600">{{ t('Price range') }}</label>
              <div class="flex gap-2">
                <input v-model="form.min_price" type="number" min="0" :placeholder="t('Min')" class="input-base" />
                <input v-model="form.max_price" type="number" min="0" :placeholder="t('Max')" class="input-base" />
              </div>
            </div>
            <div class="flex gap-2">
              <button type="submit" class="btn-secondary flex-1">{{ t('Apply') }}</button>
              <button type="button" class="btn-ghost flex-1" @click="resetFilters">{{ t('Reset') }}</button>
            </div>
          </form>
        </aside>

        <div v-if="products.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <ProductCard
            v-for="product in products"
            :key="product.id"
            :product="product"
            :currency="currency"
            :promotions="(page && page.props && page.props.homepagePromotions) ? page.props.homepagePromotions : []"
          />
        </div>
        <EmptyState
          v-else
          :eyebrow="t('Catalog')"
          :title="t('No products match these filters')"
          :message="t('Clear a few filters or jump back to all categories to keep exploring.')"
        >
          <template #actions>
            <button type="button" class="btn-primary" @click="resetFilters">{{ t('Clear filters') }}</button>
            <Link href="/products" class="btn-ghost">{{ t('View all products') }}</Link>
          </template>
        </EmptyState>
        <div v-if="products.length" class="flex items-center justify-between border-t border-slate-100 pt-4 text-xs text-slate-500">
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="btn-ghost px-3 py-2 text-xs"
              :disabled="productsPaginator.current_page <= 1"
              @click="goToPage((productsPaginator.current_page ?? 1) - 1)"
            >
              {{ t('Previous slide') }}
            </button>
            <button
              type="button"
              class="btn-ghost px-3 py-2 text-xs"
              :disabled="! hasMore"
              @click="goToPage((productsPaginator.current_page ?? 1) + 1)"
            >
              {{ t('Next slide') }}
            </button>
          </div>
          <span>
            {{ t('Page') }} {{ productsPaginator.current_page ?? 1 }} / {{ productsPaginator.last_page ?? 1 }}
          </span>
        </div>
      </div>
    </section>

    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="filtersOpen" class="fixed inset-0 z-[60] lg:hidden">
        <div class="absolute inset-0 bg-slate-900/20" @click="filtersOpen = false" />
      </div>
    </Transition>

    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-4 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-4 opacity-0"
    >
      <div v-if="filtersOpen" class="fixed inset-x-0 bottom-0 z-[70] rounded-t-3xl border-t border-slate-200 bg-white p-6 lg:hidden">
        <div class="flex items-center justify-between">
          <p class="text-sm font-semibold text-slate-900">{{ t('Filters') }}</p>
          <button type="button" class="btn-ghost text-xs" @click="filtersOpen = false">{{ t('Close') }}</button>
        </div>
        <form class="mt-4 space-y-4" @submit.prevent="applyFilters">
          <div class="space-y-2">
            <label class="text-xs font-semibold text-slate-600">{{ t('Search') }}</label>
            <input v-model="form.q" type="search" :placeholder="t('Search products')" class="input-base" />
          </div>
          <div class="space-y-2">
            <label class="text-xs font-semibold text-slate-600">{{ t('Category') }}</label>
            <select v-model="form.category" class="input-base">
              <option value="">{{ t('All categories') }}</option>
                <option v-for="category in categories" :key="category.slug || category.name" :value="category.slug || category.name">
                  {{ category.name || category }}
                </option>
            </select>
          </div>
          <div class="space-y-2">
            <label class="text-xs font-semibold text-slate-600">{{ t('Price range') }}</label>
            <div class="flex gap-2">
              <input v-model="form.min_price" type="number" min="0" :placeholder="t('Min')" class="input-base" />
              <input v-model="form.max_price" type="number" min="0" :placeholder="t('Max')" class="input-base" />
            </div>
          </div>
          <div class="flex gap-2">
            <button type="submit" class="btn-secondary flex-1" @click="filtersOpen = false">{{ t('Apply') }}</button>
            <button type="button" class="btn-ghost flex-1" @click="resetFilters">{{ t('Reset') }}</button>
          </div>
        </form>
      </div>
    </Transition>
  </StorefrontLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ProductCard from '@/Components/ProductCard.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  products: { type: Array, required: true },
  currency: { type: String, default: 'USD' },
  categories: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const page = usePage ? usePage() : null;

const { t } = useTranslations()

const form = reactive({
  q: props.filters.q ?? '',
  category: props.filters.category ?? '',
  min_price: props.filters.min_price ?? '',
  max_price: props.filters.max_price ?? '',
  page: props.filters.page ?? 1,
})

const filtersOpen = ref(false)

const activeFilters = computed(() => {
  const items = []
  if (form.q) {
    items.push({ key: 'q', label: t('Search: :value', { value: form.q }) })
  }
  if (form.category) {
    const match = props.categories.find((category) => (category.slug || category.name || category) === form.category)
    const label = match?.name ?? form.category
    items.push({ key: 'category', label: t('Category: :value', { value: label }) })
  }
  if (form.min_price) {
    items.push({ key: 'min_price', label: t('Min: :value', { value: form.min_price }) })
  }
  if (form.max_price) {
    items.push({ key: 'max_price', label: t('Max: :value', { value: form.max_price }) })
  }
  return items
})

const applyFilters = () => {
  filtersOpen.value = false
  form.page = 1
  router.get('/products', { ...form }, { preserveState: true, replace: true })
}

const resetFilters = () => {
  form.q = ''
  form.category = ''
  form.min_price = ''
  form.max_price = ''
  filtersOpen.value = false
  form.page = 1
  applyFilters()
}

const clearFilter = (key) => {
  form[key] = ''
  form.page = 1
  applyFilters()
}

const productsPaginator = computed(() => props.products ?? { data: [] })
const products = computed(() => productsPaginator.value.data ?? [])
const hasMore = computed(() => (productsPaginator.value.current_page ?? 1) < (productsPaginator.value.last_page ?? 1))

const goToPage = (page) => {
  const last = productsPaginator.value.last_page ?? 1
  if (page < 1 || page > last) {
    return
  }
  form.page = page
  router.get('/products', { ...form }, { preserveState: true })
}
</script>
