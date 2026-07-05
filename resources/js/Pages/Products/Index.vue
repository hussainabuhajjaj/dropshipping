<template>
  <StorefrontLayout>
    <Head>
      <meta name="robots" head-key="robots" :content="shouldNoindex ? 'noindex, nofollow' : 'index, follow'" />
      <link v-if="prevPageUrl" rel="prev" :href="prevPageUrl" />
      <link v-if="nextPageUrl" rel="next" :href="nextPageUrl" />
    </Head>

    <div class="min-h-screen bg-white pb-28">
      <Breadcrumbs v-if="breadcrumbs.length" :items="breadcrumbs" class="px-4" />

      <div class="mx-auto mt-4 max-w-7xl px-4">
        <div class="flex items-center justify-between">
          <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">{{ heroTitle }}</h1>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 lg:hidden"
            @click="filtersOpen = true"
          >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            {{ t('Filters') }}
            <span v-if="activeFilters.length" class="ml-0.5 rounded-full bg-red-500 px-1.5 text-[0.55rem] font-bold text-white">{{ activeFilters.length }}</span>
          </button>
        </div>
      </div>

      <div v-if="products.length" class="mx-auto mt-6 max-w-7xl px-4">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
          <!-- Desktop sidebar filters -->
          <aside class="hidden w-60 shrink-0 lg:block">
            <div class="sticky top-28 space-y-5">
              <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h3 class="text-sm font-bold text-slate-900">{{ t('Filters') }}</h3>
                <button type="button" class="text-xs font-semibold text-red-500 hover:text-red-600" @click="resetFilters">{{ t('Reset') }}</button>
              </div>

              <div>
                <button type="button" class="flex w-full items-center justify-between text-xs font-bold uppercase tracking-wide text-slate-500" @click="togglePanel('category')">
                  {{ t('Category') }}
                  <svg class="h-3.5 w-3.5 transition" :class="openPanels.category ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div v-if="openPanels.category" class="mt-3">
                  <select v-model="form.category" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" @change="applyFilters">
                    <option value="">{{ t('All categories') }}</option>
                    <option v-for="cat in categoryOptions" :key="cat.slug || cat.name" :value="cat.slug || cat.name">{{ cat.label }}</option>
                  </select>
                </div>
              </div>

              <div>
                <button type="button" class="flex w-full items-center justify-between text-xs font-bold uppercase tracking-wide text-slate-500" @click="togglePanel('price')">
                  {{ t('Price') }}
                  <svg class="h-3.5 w-3.5 transition" :class="openPanels.price ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div v-if="openPanels.price" class="mt-3">
                  <div class="flex items-center gap-2">
                    <input v-model="form.min_price" type="number" min="0" :placeholder="t('Min')" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                    <span class="text-xs text-slate-400">—</span>
                    <input v-model="form.max_price" type="number" min="0" :placeholder="t('Max')" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                  </div>
                </div>
              </div>

              <div>
                <button type="button" class="flex w-full items-center justify-between text-xs font-bold uppercase tracking-wide text-slate-500" @click="togglePanel('rating')">
                  {{ t('Rating') }}
                  <svg class="h-3.5 w-3.5 transition" :class="openPanels.rating ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div v-if="openPanels.rating" class="mt-3">
                  <select v-model="form.rating" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" @change="applyFilters">
                    <option value="">{{ t('Any rating') }}</option>
                    <option value="4">4+ {{ t('stars') }}</option>
                    <option value="3">3+ {{ t('stars') }}</option>
                    <option value="2">2+ {{ t('stars') }}</option>
                  </select>
                </div>
              </div>

              <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-700">
                <input v-model="form.in_stock" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-0" @change="applyFilters" />
                {{ t('In stock only') }}
              </label>

              <button type="button" class="btn-red w-full" @click="applyFilters">{{ t('Apply') }}</button>
            </div>
          </aside>

          <div class="min-w-0 flex-1">
            <!-- Sort + count toolbar -->
            <div class="flex items-center justify-between gap-4">
              <p class="text-xs text-slate-500">{{ productsPaginator.total ?? products.length }} {{ t('products') }}</p>
              <select v-model="form.sort" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-600 focus:border-slate-400 focus:outline-none" @change="handleSortChange($event.target.value)">
                <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>

            <!-- Active filter chips -->
            <div v-if="activeFilters.length" class="mt-3 flex flex-wrap gap-2">
              <button v-for="filter in activeFilters" :key="filter.key" type="button" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-[0.65rem] font-semibold text-slate-600 transition hover:bg-slate-100" @click="clearFilter(filter.key)">
                {{ filter.label }}
                <svg class="h-3 w-3 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 6l12 12M18 6l-12 12"/></svg>
              </button>
              <button type="button" class="text-xs font-semibold text-red-500 hover:text-red-600" @click="resetFilters">{{ t('Clear all') }}</button>
            </div>

            <!-- Product grid -->
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
              <ProductCard v-for="product in products" :key="product.id" :product="product" :currency="currency" :promotions="promotionList" />
            </div>

            <!-- Pagination -->
            <nav v-if="hasMore || (productsPaginator.current_page ?? 1) > 1" class="mt-8 flex items-center justify-center gap-1">
              <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-sm text-slate-500 transition hover:bg-slate-100 disabled:opacity-30 disabled:pointer-events-none" :disabled="(productsPaginator.current_page ?? 1) <= 1" @click="goToPage((productsPaginator.current_page ?? 1) - 1)">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 19l-7-7 7-7"/></svg>
              </button>
              <template v-for="(p, i) in pageNumbers" :key="i">
                <span v-if="p === '...'" class="px-1 text-xs text-slate-400">...</span>
                <button v-else type="button" class="flex h-8 min-w-[2rem] items-center justify-center rounded-lg px-2 text-xs font-semibold transition"
                  :class="p === (productsPaginator.current_page ?? 1) ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="goToPage(p)">{{ p }}</button>
              </template>
              <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-sm text-slate-500 transition hover:bg-slate-100 disabled:opacity-30 disabled:pointer-events-none" :disabled="(productsPaginator.current_page ?? 1) >= (productsPaginator.last_page ?? 1)" @click="goToPage((productsPaginator.current_page ?? 1) + 1)">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"/></svg>
              </button>
            </nav>
          </div>
        </div>
      </div>

      <EmptyState v-else :eyebrow="t('Catalog')" :title="t('No products match these filters')" :message="t('Clear a few filters or jump back to all categories.')">
        <template #actions>
          <button type="button" class="btn-primary" @click="resetFilters">{{ t('Clear filters') }}</button>
          <Link href="/products" class="btn-ghost">{{ t('View all products') }}</Link>
        </template>
      </EmptyState>
    </div>

    <!-- Mobile Filters Bottom Sheet -->
    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="filtersOpen" class="fixed inset-0 z-[60] lg:hidden"><div class="absolute inset-0 bg-slate-900/20" @click="filtersOpen = false" /></div>
    </Transition>
    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-4 opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-4 opacity-0">
      <div v-if="filtersOpen" class="fixed inset-x-0 bottom-0 z-[70] max-h-[80vh] overflow-y-auto rounded-t-2xl border-t border-slate-200 bg-white p-5 pb-8 lg:hidden">
        <div class="flex items-center justify-between pb-3">
          <p class="text-sm font-bold text-slate-900">{{ t('Filters') }}</p>
          <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" @click="filtersOpen = false">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6l-12 12"/></svg>
          </button>
        </div>
        <div class="space-y-4">
          <div>
            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">{{ t('Search') }}</p>
            <input v-model="form.q" type="search" :placeholder="t('Search products...')" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm focus:border-slate-400 focus:bg-white focus:outline-none" />
          </div>
          <div>
            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">{{ t('Category') }}</p>
            <select v-model="form.category" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
              <option value="">{{ t('All categories') }}</option>
              <option v-for="cat in categoryOptions" :key="cat.slug || cat.name" :value="cat.slug || cat.name">{{ cat.label }}</option>
            </select>
          </div>
          <div>
            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">{{ t('Price') }}</p>
            <div class="flex items-center gap-2">
              <input v-model="form.min_price" type="number" min="0" :placeholder="t('Min')" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
              <span class="text-xs text-slate-400">—</span>
              <input v-model="form.max_price" type="number" min="0" :placeholder="t('Max')" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
            </div>
          </div>
          <div>
            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">{{ t('Rating') }}</p>
            <select v-model="form.rating" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
              <option value="">{{ t('Any rating') }}</option>
              <option value="4">4+ {{ t('stars') }}</option>
              <option value="3">3+ {{ t('stars') }}</option>
              <option value="2">2+ {{ t('stars') }}</option>
            </select>
          </div>
          <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-700">
            <input v-model="form.in_stock" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-0" />
            {{ t('In stock only') }}
          </label>
          <button type="button" class="btn-red w-full" @click="applyFilters">{{ t('Apply') }}</button>
        </div>
      </div>
    </Transition>
  </StorefrontLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import Breadcrumbs from '@/Components/Breadcrumbs.vue'
import ProductCard from '@/Components/ProductCard.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  products: { type: Array, required: true },
  currency: { type: String, default: 'USD' },
  categories: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  filterContext: { type: Object, default: () => ({}) },
  shouldNoindex: { type: Boolean, default: false },
})

const page = usePage()
const { t } = useTranslations()
const promotionList = computed(() => (page?.props?.promotions || page?.props?.homepagePromotions || []))

const form = reactive({
  q: props.filters.q ?? '',
  category: props.filters.category ?? '',
  min_price: props.filters.min_price ?? '',
  max_price: props.filters.max_price ?? '',
  rating: props.filters.rating ?? '',
  in_stock: Boolean(props.filters.in_stock),
  is_featured: props.filters.is_featured ?? '',
  sort: props.filters.sort ?? '',
  page: props.filters.page ?? 1,
})

const filtersOpen = ref(false)
const openPanels = reactive({ category: true, price: true, rating: false })
const togglePanel = (key) => { openPanels[key] = !openPanels[key] }

const sortOptions = computed(() => [
  { value: '', label: t('Newest') },
  { value: 'price_asc', label: t('Price: low to high') },
  { value: 'price_desc', label: t('Price: high to low') },
  { value: 'rating', label: t('Top rated') },
  { value: 'popularity', label: t('Most reviewed') },
  { value: 'featured', label: t('Featured') },
])

const sortLabels = {
  '': t('Newest'), newest: t('Newest'), price_asc: t('Price: low to high'), price_desc: t('Price: high to low'),
  rating: t('Top rated'), popularity: t('Most reviewed'), featured: t('Featured'),
}

const flattenCategoryOptions = (nodes, depth = 0) => {
  if (!Array.isArray(nodes)) return []
  return nodes.flatMap((category) => {
    const name = category?.name || category?.slug || ''
    return [{ ...category, label: depth > 0 ? `${'— '.repeat(depth)}${name}` : name }, ...flattenCategoryOptions(category?.children ?? [], depth + 1)]
  })
}

const categoryOptions = computed(() => flattenCategoryOptions(props.categories))

const heroTitle = computed(() => {
  if (props.filterContext?.collection?.name) return props.filterContext.collection.name
  if (props.filterContext?.campaign?.name) return props.filterContext.campaign.name
  if (props.filterContext?.category?.name) return t('Shop :name', { name: props.filterContext.category.name })
  return t('All Products')
})

const breadcrumbs = computed(() => {
  const items = [{ label: 'Home', href: '/' }]
  if (props.filterContext?.category?.name) items.push({ label: props.filterContext.category.name, href: null })
  else if (props.filterContext?.collection?.name) items.push({ label: props.filterContext.collection.name, href: null })
  else if (props.filterContext?.campaign?.name) items.push({ label: props.filterContext.campaign.name, href: null })
  else items.push({ label: t('Products'), href: null })
  return items
})

const currentPage = props.filters.page || 1
const prevPageUrl = computed(() => {
  if (currentPage <= 1) return null
  const params = new URLSearchParams(window.location.search)
  params.set('page', currentPage - 1)
  return `${window.location.pathname}?${params.toString()}`
})
const nextPageUrl = computed(() => {
  if (props.products.length === 0) return null
  const params = new URLSearchParams(window.location.search)
  params.set('page', currentPage + 1)
  return `${window.location.pathname}?${params.toString()}`
})

const activeFilters = computed(() => {
  const items = []
  if (form.q) items.push({ key: 'q', label: t('Search: :value', { value: form.q }) })
  if (form.category) {
    const match = categoryOptions.value.find((cat) => (cat.slug || cat.name || cat) === form.category)
    items.push({ key: 'category', label: match?.name ?? form.category })
  }
  if (form.min_price) items.push({ key: 'min_price', label: form.min_price + '+ CFA' })
  if (form.max_price) items.push({ key: 'max_price', label: t('Up to :value', { value: form.max_price }) })
  if (form.rating) items.push({ key: 'rating', label: form.rating + '+ ' + t('stars') })
  if (form.in_stock) items.push({ key: 'in_stock', label: t('In stock') })
  if (form.sort && sortLabels[form.sort]) items.push({ key: 'sort', label: sortLabels[form.sort] })
  return items
})

const applyFilters = () => {
  filtersOpen.value = false
  form.page = 1
  router.get('/products', { ...form }, { preserveState: true, replace: true })
}

const handleSortChange = (value) => {
  form.sort = value
  applyFilters()
}

const resetFilters = () => {
  Object.assign(form, { q: '', category: '', min_price: '', max_price: '', rating: '', in_stock: false, is_featured: '', sort: '' })
  filtersOpen.value = false
  applyFilters()
}

const clearFilter = (key) => {
  if (typeof form[key] === 'boolean') form[key] = false; else form[key] = ''
  applyFilters()
}

const productsPaginator = computed(() => props.products ?? { data: [] })
const products = computed(() => productsPaginator.value.data ?? [])
const hasMore = computed(() => (productsPaginator.value.current_page ?? 1) < (productsPaginator.value.last_page ?? 1))

const pageNumbers = computed(() => {
  const current = productsPaginator.value.current_page ?? 1
  const last = productsPaginator.value.last_page ?? 1
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)
  const pages = [1]
  if (current > 3) pages.push('...')
  const start = Math.max(2, current - 1)
  const end = Math.min(last - 1, current + 1)
  for (let i = start; i <= end; i++) pages.push(i)
  if (current < last - 2) pages.push('...')
  pages.push(last)
  return pages
})

const goToPage = (page) => {
  const last = productsPaginator.value.last_page ?? 1
  if (page < 1 || page > last) return
  form.page = page
  router.get('/products', { ...form }, { preserveState: true })
}
</script>