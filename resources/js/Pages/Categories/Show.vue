<template>
  <StorefrontLayout>
    <Head :title="metaTitle">
      <meta name="description" head-key="description" :content="metaDescription" />
    </Head>

    <section
      class="mb-8 overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-amber-50 via-white to-slate-50"
    >
      <div class="grid gap-6 p-8 lg:grid-cols-[1.2fr,0.8fr]">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Category') }}</p>
          <h1 class="mt-2 text-3xl font-semibold text-slate-900">{{ displayTitle }}</h1>
          <p v-if="displaySubtitle" class="mt-3 max-w-2xl text-sm text-slate-600">{{ displaySubtitle }}</p>
          <p v-else-if="metaDescription" class="mt-3 max-w-2xl text-sm text-slate-600">{{ metaDescription }}</p>
          <div class="mt-4 flex flex-wrap gap-2 text-xs text-slate-500">
            <span>{{ t(':count products', { count: productsPager.total ?? 0 }) }}</span>
            <span>{{ t('Tracked delivery') }}</span>
            <span>{{ t('Customs clarity') }}</span>
          </div>
          <Link
            v-if="category.hero_cta_label && category.hero_cta_link"
            :href="category.hero_cta_link"
            class="btn-primary mt-4 inline-flex"
          >
            {{ category.hero_cta_label }}
          </Link>
        </div>
        <div v-if="category.hero_image" class="flex items-center justify-center">
          <img :src="category.hero_image" :alt="category.name" class="h-56 w-full rounded-2xl object-cover shadow-lg" />
        </div>
      </div>
    </section>

    <section v-if="products.length" class="space-y-4">
      <div class="flex flex-col lg:flex-row gap-8">
        <!-- Sidebar Filters -->
        <aside class="card hidden h-fit space-y-4 p-5 lg:block min-w-[260px] max-w-xs">
          <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Filters') }}</p>
            <p class="text-sm text-slate-600">{{ t('Narrow results by category, price, rating, brand, or attributes.') }}</p>
          </div>
          <form class="space-y-4" @submit.prevent="applyFilters">
            <div class="space-y-2">
              <label class="text-xs font-semibold text-slate-600">{{ t('Search') }}</label>
              <input v-model="form.q" type="search" :placeholder="t('Search products')" class="input-base" />
            </div>
            <div class="space-y-2">
              <label class="text-xs font-semibold text-slate-600">{{ t('Price range') }}</label>
              <div class="flex gap-2">
                <input v-model="form.min_price" type="number" min="0" :placeholder="t('Min')" class="input-base" />
                <input v-model="form.max_price" type="number" min="0" :placeholder="t('Max')" class="input-base" />
              </div>
            </div>
            <div class="space-y-2">
              <label class="text-xs font-semibold text-slate-600">{{ t('Rating') }}</label>
              <select v-model="form.rating" class="input-base">
                <option value="">{{ t('Any rating') }}</option>
                <option v-for="r in [5,4,3,2,1]" :key="r" :value="r">{{ t('At least :r stars', { r }) }}</option>
              </select>
            </div>
            <div class="space-y-2">
              <label class="text-xs font-semibold text-slate-600">{{ t('Stock') }}</label>
              <select v-model="form.in_stock" class="input-base">
                <option value="">{{ t('All') }}</option>
                <option value="1">{{ t('In stock only') }}</option>
              </select>
            </div>
            <div class="space-y-2">
              <label class="text-xs font-semibold text-slate-600">{{ t('Brand') }}</label>
              <select v-model="form.brand" class="input-base">
                <option value="">{{ t('All brands') }}</option>
                <option v-for="brand in brands" :key="brand" :value="brand">{{ brand }}</option>
              </select>
            </div>
            <!-- Dynamic attribute filters (example) -->
            <div v-for="attr in attributes" :key="attr.key" class="space-y-2">
              <label class="text-xs font-semibold text-slate-600">{{ attr.label }}</label>
              <select v-model="form[attr.key]" class="input-base">
                <option value="">{{ t('Any') }}</option>
                <option v-for="option in attr.options" :key="option" :value="option">{{ option }}</option>
              </select>
            </div>
            <div class="flex gap-2">
              <button type="submit" class="btn-secondary flex-1">{{ t('Apply') }}</button>
              <button type="button" class="btn-ghost flex-1" @click="resetFilters">{{ t('Reset') }}</button>
            </div>
          </form>
        </aside>

        <div class="flex-1 space-y-4">
          <!-- Topbar: Sort & Filters (mobile) -->
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
              <span>{{ t(':count items', { count: products.length }) }}</span>
              <button type="button" class="btn-secondary px-3 py-2 text-xs lg:hidden" @click="filtersOpen = true">
                {{ t('Filters') }}
              </button>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-semibold text-slate-600">{{ t('Sort by') }}</label>
              <select v-model="form.sort" class="input-base" @change="applyFilters">
                <option value="">{{ t('Relevance') }}</option>
                <option value="price_asc">{{ t('Price: Low to High') }}</option>
                <option value="price_desc">{{ t('Price: High to Low') }}</option>
                <option value="newest">{{ t('Newest') }}</option>
                <option value="rating">{{ t('Rating') }}</option>
                <option value="popularity">{{ t('Popularity') }}</option>
              </select>
            </div>
          </div>

          <!-- Active filters chips -->
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

          <!-- Product grid -->
          <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <ProductCard v-for="product in products" :key="product.id" :product="product" :currency="currency" />
          </div>

          <!-- Pagination -->
          <div class="flex items-center justify-between border-t border-slate-100 pt-4 text-xs text-slate-500">
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="btn-ghost px-3 py-2 text-xs"
                :disabled="productsPager.current_page <= 1"
                @click="goToPage((productsPager.current_page ?? 1) - 1)"
              >
                {{ t('Previous slide') }}
              </button>
              <button
                type="button"
                class="btn-ghost px-3 py-2 text-xs"
                :disabled="! hasMore"
                @click="goToPage((productsPager.current_page ?? 1) + 1)"
              >
                {{ t('Next slide') }}
              </button>
            </div>
            <span>
              {{ t('Page') }} {{ productsPager.current_page ?? 1 }} / {{ productsPager.last_page ?? 1 }}
            </span>
          </div>
        </div>
      </div>

      <!-- Mobile Filters Modal -->
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
              <label class="text-xs font-semibold text-slate-600">{{ t('Price range') }}</label>
              <div class="flex gap-2">
                <input v-model="form.min_price" type="number" min="0" :placeholder="t('Min')" class="input-base" />
                <input v-model="form.max_price" type="number" min="0" :placeholder="t('Max')" class="input-base" />
              </div>
            </div>
            <div class="space-y-2">
              <label class="text-xs font-semibold text-slate-600">{{ t('Rating') }}</label>
              <select v-model="form.rating" class="input-base">
                <option value="">{{ t('Any rating') }}</option>
                <option v-for="r in [5,4,3,2,1]" :key="r" :value="r">{{ t('At least :r stars', { r }) }}</option>
              </select>
            </div>
            <div class="space-y-2">
              <label class="text-xs font-semibold text-slate-600">{{ t('Stock') }}</label>
              <select v-model="form.in_stock" class="input-base">
                <option value="">{{ t('All') }}</option>
                <option value="1">{{ t('In stock only') }}</option>
              </select>
            </div>
            <div class="space-y-2">
              <label class="text-xs font-semibold text-slate-600">{{ t('Brand') }}</label>
              <select v-model="form.brand" class="input-base">
                <option value="">{{ t('All brands') }}</option>
                <option v-for="brand in brands" :key="brand" :value="brand">{{ brand }}</option>
              </select>
            </div>
            <div v-for="attr in attributes" :key="attr.key" class="space-y-2">
              <label class="text-xs font-semibold text-slate-600">{{ attr.label }}</label>
              <select v-model="form[attr.key]" class="input-base">
                <option value="">{{ t('Any') }}</option>
                <option v-for="option in attr.options" :key="option" :value="option">{{ option }}</option>
              </select>
            </div>
            <div class="flex gap-2">
              <button type="submit" class="btn-secondary flex-1" @click="filtersOpen = false">{{ t('Apply') }}</button>
              <button type="button" class="btn-ghost flex-1" @click="resetFilters">{{ t('Reset') }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </section>
    <EmptyState
      v-else
      :eyebrow="t('Category')"
      :title="t('No products here yet')"
      :message="t('This collection is getting curated. Browse other categories or check back soon.')"
    >
      <template #actions>
        <Link href="/products" class="btn-primary">{{ t('Browse catalog') }}</Link>
        <Link href="/support" class="btn-ghost">{{ t('Request a product') }}</Link>
      </template>
    </EmptyState>
  </StorefrontLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ProductCard from '@/Components/ProductCard.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  category: { type: Object, required: true },
  products: { type: Object, default: () => ({ data: [] }) },
  currency: { type: String, default: 'USD' },
  filters: { type: Object, default: () => ({}) },
  brands: { type: Array, default: () => [] },
  attributes: { type: Array, default: () => [] },
})

const { t } = useTranslations()

const form = reactive({
  q: props.filters.q ?? '',
  min_price: props.filters.min_price ?? '',
  max_price: props.filters.max_price ?? '',
  rating: props.filters.rating ?? '',
  in_stock: props.filters.in_stock ?? '',
  brand: props.filters.brand ?? '',
  sort: props.filters.sort ?? '',
  page: props.filters.page ?? 1,
  // Dynamic attributes
  ...Object.fromEntries((props.attributes || []).map(attr => [attr.key, props.filters[attr.key] ?? ''])),
})


const filtersOpen = ref(false)

const activeFilters = computed(() => {
  const items = []
  if (form.q) items.push({ key: 'q', label: t('Search: :value', { value: form.q }) })
  if (form.min_price) items.push({ key: 'min_price', label: t('Min: :value', { value: form.min_price }) })
  if (form.max_price) items.push({ key: 'max_price', label: t('Max: :value', { value: form.max_price }) })
  if (form.rating) items.push({ key: 'rating', label: t('Rating: :value+', { value: form.rating }) })
  if (form.in_stock) items.push({ key: 'in_stock', label: t('In stock only') })
  if (form.brand) items.push({ key: 'brand', label: t('Brand: :value', { value: form.brand }) })
  (props.attributes || []).forEach(attr => {
    if (form[attr.key]) items.push({ key: attr.key, label: t(attr.label + ': :value', { value: form[attr.key] }) })
  })
  return items
})

const applyFilters = () => {
  filtersOpen.value = false
  form.page = 1
  router.get(`/categories/${props.category.slug}`, { ...form }, { preserveState: true, replace: true })
}

const resetFilters = () => {
  form.q = ''
  form.min_price = ''
  form.max_price = ''
  form.rating = ''
  form.in_stock = ''
  form.brand = ''
  form.sort = ''
  ;(props.attributes || []).forEach(attr => {
    form[attr.key] = ''
  })
  filtersOpen.value = false
  form.page = 1
  applyFilters()
}

const clearFilter = (key) => {
  form[key] = ''
  form.page = 1
  applyFilters()
}

const metaTitle = computed(() => props.category.meta_title || `${props.category.name} | Azura`)
const metaDescription = computed(() => props.category.meta_description || '')
const displayTitle = computed(() => props.category.hero_title || props.category.name)
const displaySubtitle = computed(() => props.category.hero_subtitle || props.category.description || '')

const productsPager = computed(() => props.products ?? { data: [] })
const products = computed(() => productsPager.value.data ?? [])
const hasMore = computed(() => (productsPager.value.current_page ?? 1) < (productsPager.value.last_page ?? 1))

const goToPage = (page) => {
  if (page < 1 || page > (productsPager.value.last_page ?? 1)) {
    return
  }
  form.page = page
  router.get(`/categories/${props.category.slug}`, { ...form }, { preserveState: true, replace: true })
}
</script>
