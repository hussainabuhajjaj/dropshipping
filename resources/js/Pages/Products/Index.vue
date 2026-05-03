<template>
  <StorefrontLayout>
    <Head>
      <meta name="robots" head-key="robots" :content="shouldNoindex ? 'noindex, nofollow' : 'index, follow'" />
      <link v-if="prevPageUrl" rel="prev" :href="prevPageUrl" />
      <link v-if="nextPageUrl" rel="next" :href="nextPageUrl" />
    </Head>
    <div class="space-y-5 bg-[#f7f3eb] pb-24 sm:space-y-6 sm:pb-28">
      <section class="overflow-hidden rounded-[1.8rem] bg-[#111111] text-white shadow-[0_20px_48px_rgba(15,23,42,0.16)]">
        <div class="grid gap-3 p-4 sm:p-5 lg:grid-cols-[1.12fr_0.88fr] lg:p-6">
          <div class="space-y-3">
            <div class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
              <span class="shrink-0 rounded-full bg-[#ff6b35] px-3 py-1 text-[0.64rem] font-bold uppercase tracking-[0.2em] text-white">{{ t('Catalog') }}</span>
              <span class="shrink-0 rounded-full bg-white/10 px-3 py-1 text-[0.64rem] font-bold uppercase tracking-[0.16em] text-white/90">{{ t(':count products', { count: productsPaginator.total ?? products.length }) }}</span>
              <span v-if="filterContextLabel" class="shrink-0 rounded-full bg-white/10 px-3 py-1 text-[0.64rem] font-bold uppercase tracking-[0.16em] text-white/90">{{ filterContextLabel }}</span>
            </div>

            <div class="rounded-[1.35rem] border border-white/10 bg-white/8 p-4 backdrop-blur">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <h1 class="text-[1.5rem] font-black leading-[0.95] tracking-[-0.04em] sm:text-[2rem]">{{ heroTitle }}</h1>
                  <p class="mt-2 max-w-2xl text-sm leading-6 text-white/74">{{ heroSubtitle }}</p>
                </div>
                <div class="shrink-0 rounded-[1rem] bg-[#facc15] px-2.5 py-1.5 text-[0.62rem] font-black uppercase tracking-[0.16em] text-slate-950">
                  {{ t('Live') }}
                </div>
              </div>

              <div class="mt-3 grid grid-cols-3 gap-2">
                <article class="rounded-[1rem] border border-white/10 bg-black/20 px-3 py-2.5">
                  <p class="text-[0.56rem] font-bold uppercase tracking-[0.18em] text-white/52">{{ t('Results') }}</p>
                  <p class="mt-1 text-[0.82rem] font-bold text-white">{{ productsPaginator.total ?? products.length }}</p>
                </article>
                <article class="rounded-[1rem] border border-white/10 bg-black/20 px-3 py-2.5">
                  <p class="text-[0.56rem] font-bold uppercase tracking-[0.18em] text-white/52">{{ t('Categories') }}</p>
                  <p class="mt-1 text-[0.82rem] font-bold text-white">{{ categoryOptions.length }}</p>
                </article>
                <article class="rounded-[1rem] border border-white/10 bg-black/20 px-3 py-2.5">
                  <p class="text-[0.56rem] font-bold uppercase tracking-[0.18em] text-white/52">{{ t('Sort') }}</p>
                  <p class="mt-1 text-[0.82rem] font-bold text-white">{{ currentSortLabel }}</p>
                </article>
              </div>

              <div v-if="contextChips.length" class="mt-3 flex flex-wrap gap-2">
                <Link
                  v-for="chip in contextChips"
                  :key="chip.label"
                  :href="chip.href"
                  class="inline-flex min-h-10 items-center rounded-full border border-white/16 bg-white/8 px-3 text-[0.72rem] font-semibold text-white transition hover:bg-white/12"
                >
                  {{ chip.label }}
                </Link>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <article class="rounded-[1.25rem] bg-[#ff6b35] px-3 py-3 text-white">
              <p class="text-[0.54rem] font-bold uppercase tracking-[0.18em] text-white/72">{{ t('Fast browse') }}</p>
              <p class="mt-1 text-sm font-black leading-4">{{ t('Dense product exposure') }}</p>
              <p class="mt-1 text-[0.7rem] leading-4 text-white/80">{{ t('More products per screen with quick add and visible buying cues.') }}</p>
            </article>
            <article class="rounded-[1.25rem] border border-white/10 bg-white/8 px-3 py-3 backdrop-blur">
              <p class="text-[0.54rem] font-bold uppercase tracking-[0.18em] text-white/55">{{ t('Discovery') }}</p>
              <p class="mt-1 text-sm font-black leading-4 text-white">{{ t('Search, sort, filter') }}</p>
              <p class="mt-1 text-[0.7rem] leading-4 text-white/72">{{ t('Everything stays within thumb reach on mobile.') }}</p>
            </article>
            <article class="col-span-2 overflow-hidden rounded-[1.25rem] border border-white/10 bg-white/8 p-1.5 backdrop-blur">
              <div class="rounded-[1rem] bg-gradient-to-br from-[#2a2a2a] via-[#1a1a1a] to-[#101010] px-4 py-4">
                <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-white/52">{{ t('Simbazu feed') }}</p>
                <p class="mt-2 text-[1.1rem] font-black tracking-[-0.03em] text-white">{{ t('Find the next add-to-cart faster') }}</p>
                <p class="mt-1 text-[0.74rem] leading-5 text-white/72">{{ t("Reliable delivery and transparent checkout stay intact while the browse experience gets denser.") }}</p>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section class="space-y-5">
        <BrowseToolbar
          :total-count="productsPaginator.total ?? products.length"
          :active-filter-count="activeFilters.length"
          :search="form.q"
          :search-placeholder="t('Search products')"
          :sort="form.sort"
          :sort-options="sortOptions"
          :filter-button-label="t('Filters')"
          @update:search="form.q = $event"
          @update:sort="handleSortChange"
          @open-filters="filtersOpen = true"
          @submit-search="applyFilters"
        />

        <div v-if="activeFilters.length" class="flex flex-wrap gap-2">
          <button
            v-for="filter in activeFilters"
            :key="filter.key"
            type="button"
            class="inline-flex min-h-9 items-center gap-2 rounded-full border border-[#eadfce] bg-[#fffaf4] px-3 text-[0.72rem] font-semibold text-slate-700 transition hover:border-slate-300"
            @click="clearFilter(filter.key)"
          >
            {{ filter.label }}
            <span class="text-slate-400">x</span>
          </button>
          <button type="button" class="btn-ghost text-xs" @click="resetFilters">
            {{ t('Clear all') }}
          </button>
        </div>

        <div class="grid gap-5 lg:grid-cols-[260px,1fr]">
          <aside class="hidden lg:block rounded-[1.6rem] border border-[#eadfce] bg-white p-5 shadow-[0_16px_38px_rgba(15,23,42,0.05)]">
            <div class="space-y-2">
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Filters') }}</p>
              <p class="text-sm text-slate-600">{{ t('Narrow results by category, price, stock, or featured status.') }}</p>
            </div>
            <form class="mt-4 space-y-4" @submit.prevent="applyFilters">
              <div class="space-y-2">
                <label class="text-xs font-semibold text-slate-600">{{ t('Category') }}</label>
                <select v-model="form.category" class="input-base">
                  <option value="">{{ t('All categories') }}</option>
                  <option v-for="category in categoryOptions" :key="category.slug || category.name" :value="category.slug || category.name">
                    {{ category.label }}
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
              <div class="space-y-2">
                <label class="text-xs font-semibold text-slate-600">{{ t('Minimum rating') }}</label>
                <select v-model="form.rating" class="input-base">
                  <option value="">{{ t('Any rating') }}</option>
                  <option value="4">4+ {{ t('stars') }}</option>
                  <option value="3">3+ {{ t('stars') }}</option>
                  <option value="2">2+ {{ t('stars') }}</option>
                </select>
              </div>
              <div class="space-y-2">
                <label class="text-xs font-semibold text-slate-600">{{ t('Featured') }}</label>
                <select v-model="form.is_featured" class="input-base">
                  <option value="">{{ t('Any') }}</option>
                  <option value="1">{{ t('Featured only') }}</option>
                  <option value="0">{{ t('Standard') }}</option>
                </select>
              </div>
              <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                <input v-model="form.in_stock" type="checkbox" />
                <span>{{ t('In stock only') }}</span>
              </label>
              <div class="flex gap-2">
                <button type="submit" class="btn-secondary flex-1">{{ t('Apply') }}</button>
                <button type="button" class="btn-ghost flex-1" @click="resetFilters">{{ t('Reset') }}</button>
              </div>
            </form>
          </aside>

          <div v-if="products.length" class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4">
          <ProductCard
            v-for="product in products"
            :key="product.id"
            :product="product"
            :currency="currency"
            :promotions="(page && page.props && (page.props.promotions || page.props.homepagePromotions)) ? (page.props.promotions || page.props.homepagePromotions) : []"
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
        </div>
      </section>
    </div>

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
                <option v-for="category in categoryOptions" :key="category.slug || category.name" :value="category.slug || category.name">
                  {{ category.label }}
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
          <div class="space-y-2">
            <label class="text-xs font-semibold text-slate-600">{{ t('Sort') }}</label>
            <select v-model="form.sort" class="input-base">
              <option value="">{{ t('Newest') }}</option>
              <option value="price_asc">{{ t('Price: low to high') }}</option>
              <option value="price_desc">{{ t('Price: high to low') }}</option>
              <option value="rating">{{ t('Top rated') }}</option>
              <option value="popularity">{{ t('Most reviewed') }}</option>
              <option value="featured">{{ t('Featured') }}</option>
            </select>
          </div>
          <div class="space-y-2">
            <label class="text-xs font-semibold text-slate-600">{{ t('Minimum rating') }}</label>
            <select v-model="form.rating" class="input-base">
              <option value="">{{ t('Any rating') }}</option>
              <option value="4">4+ {{ t('stars') }}</option>
              <option value="3">3+ {{ t('stars') }}</option>
              <option value="2">2+ {{ t('stars') }}</option>
            </select>
          </div>
          <div class="space-y-2">
            <label class="text-xs font-semibold text-slate-600">{{ t('Featured') }}</label>
            <select v-model="form.is_featured" class="input-base">
              <option value="">{{ t('Any') }}</option>
              <option value="1">{{ t('Featured only') }}</option>
              <option value="0">{{ t('Standard') }}</option>
            </select>
          </div>
          <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
            <input v-model="form.in_stock" type="checkbox" />
            <span>{{ t('In stock only') }}</span>
          </label>
          <div class="flex gap-2">
            <button type="submit" class="btn-secondary flex-1" @click="filtersOpen = false">{{ t('Apply') }}</button>
            <button type="button" class="btn-ghost flex-1" @click="resetFilters">{{ t('Reset') }}</button>
          </div>
        </form>
      </div>
  </Transition>

    <PaginationRail
      v-if="products.length"
      :current-page="productsPaginator.current_page ?? 1"
      :last-page="productsPaginator.last_page ?? 1"
      :can-next="hasMore"
      :loading="false"
      @prev="goToPage(Math.max(1, (productsPaginator.current_page ?? 1) - 1))"
      @next="goToPage(Math.min(productsPaginator.last_page ?? 1, (productsPaginator.current_page ?? 1) + 1))"
      @sort="() => {}"
      @filter="() => { filtersOpen = true }"
    />
  </StorefrontLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import BrowseToolbar from '@/Components/storefront/BrowseToolbar.vue'
import ProductCard from '@/Components/ProductCard.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationRail from '@/Components/PaginationRail.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  products: { type: Array, required: true },
  currency: { type: String, default: 'USD' },
  categories: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  filterContext: { type: Object, default: () => ({}) },
  shouldNoindex: { type: Boolean, default: false },
})

const page = usePage ? usePage() : null;

const { t } = useTranslations()

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

const sortLabels = {
  '': t('Newest'),
  newest: t('Newest'),
  price_asc: t('Price: low to high'),
  price_desc: t('Price: high to low'),
  rating: t('Top rated'),
  popularity: t('Most reviewed'),
  featured: t('Featured'),
}
const sortOptions = computed(() => [
  { value: '', label: t('Newest') },
  { value: 'price_asc', label: t('Price: low to high') },
  { value: 'price_desc', label: t('Price: high to low') },
  { value: 'rating', label: t('Top rated') },
  { value: 'popularity', label: t('Most reviewed') },
  { value: 'featured', label: t('Featured') },
])

const flattenCategoryOptions = (nodes, depth = 0) => {
  if (!Array.isArray(nodes)) return []

  return nodes.flatMap((category) => {
    const name = category?.name || category?.slug || ''
    const label = depth > 0 ? `${'— '.repeat(depth)}${name}` : name

    return [
      {
        ...category,
        label,
      },
      ...flattenCategoryOptions(category?.children ?? [], depth + 1),
    ]
  })
}

const categoryOptions = computed(() => flattenCategoryOptions(props.categories))
const heroTitle = computed(() => {
  if (props.filterContext?.collection?.name) return props.filterContext.collection.name
  if (props.filterContext?.campaign?.name) return props.filterContext.campaign.name
  if (props.filterContext?.category?.name) return t('Shop :name', { name: props.filterContext.category.name })
  return t('Shop the catalog')
})
const heroSubtitle = computed(() => {
  if (props.filterContext?.collection?.name) {
    return t("Browse this storefront collection like a live Simbazu feed with dense product exposure and faster add-to-cart decisions.")
  }
  if (props.filterContext?.campaign?.name) {
    return t("This campaign lane keeps the strongest matching products together so users can scroll, compare, and buy without friction.")
  }
  return t("Curated picks with reliable delivery to Cote d'Ivoire. Duties and customs are disclosed before payment.")
})
const filterContextLabel = computed(() => {
  if (props.filterContext?.collection?.name) return t('Collection')
  if (props.filterContext?.campaign?.name) return t('Campaign')
  if (props.filterContext?.category?.name) return t('Category')
  return ''
})
const contextChips = computed(() => {
  const chips = []
  if (props.filterContext?.collection?.slug) {
    chips.push({
      label: t('Collection: :value', { value: props.filterContext.collection.name }),
      href: `/products?collection=${encodeURIComponent(props.filterContext.collection.slug)}`,
    })
  }
  if (props.filterContext?.campaign?.slug) {
    chips.push({
      label: t('Campaign: :value', { value: props.filterContext.campaign.name }),
      href: `/products?campaign=${encodeURIComponent(props.filterContext.campaign.slug)}`,
    })
  }
  if (props.filterContext?.category?.slug) {
    chips.push({
      label: t('Category: :value', { value: props.filterContext.category.name }),
      href: `/categories/${encodeURIComponent(props.filterContext.category.slug)}`,
    })
  }
  return chips
})
const currentSortLabel = computed(() => sortLabels[form.sort] || sortLabels[''])

const activeFilters = computed(() => {
  const items = []
  if (form.q) {
    items.push({ key: 'q', label: t('Search: :value', { value: form.q }) })
  }
  if (form.category) {
    const match = categoryOptions.value.find((category) => (category.slug || category.name || category) === form.category)
    const label = match?.name ?? form.category
    items.push({ key: 'category', label: t('Category: :value', { value: label }) })
  }
  if (form.min_price) {
    items.push({ key: 'min_price', label: t('Min: :value', { value: form.min_price }) })
  }
  if (form.max_price) {
    items.push({ key: 'max_price', label: t('Max: :value', { value: form.max_price }) })
  }
  if (form.rating) {
    items.push({ key: 'rating', label: t('Rating: :value+', { value: form.rating }) })
  }
  if (form.in_stock) {
    items.push({ key: 'in_stock', label: t('In stock only') })
  }
  if (form.is_featured !== '') {
    items.push({
      key: 'is_featured',
      label: form.is_featured === '1' || form.is_featured === 1 || form.is_featured === true
        ? t('Featured only')
        : t('Standard'),
    })
  }
  if (form.sort && sortLabels[form.sort]) {
    items.push({ key: 'sort', label: t('Sort: :value', { value: sortLabels[form.sort] }) })
  }
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
  form.q = ''
  form.category = ''
  form.min_price = ''
  form.max_price = ''
  form.rating = ''
  form.in_stock = false
  form.is_featured = ''
  form.sort = ''
  filtersOpen.value = false
  form.page = 1
  applyFilters()
}

const clearFilter = (key) => {
  if (typeof form[key] === 'boolean') {
    form[key] = false
  } else {
    form[key] = ''
  }
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
