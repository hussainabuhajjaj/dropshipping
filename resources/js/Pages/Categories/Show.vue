<template>
  <StorefrontLayout>
    <Head :title="metaTitle">
      <meta name="description" head-key="description" :content="metaDescription" />
    </Head>

    <div class="min-h-screen bg-white pb-28">
      <Breadcrumbs :items="breadcrumbs" class="px-4" />

      <div class="mx-auto mt-4 max-w-7xl px-4">
        <!-- Category Header -->
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">{{ displayTitle }}</h1>
            <p v-if="displaySubtitle" class="mt-1 text-sm text-slate-500">{{ displaySubtitle }}</p>
            <p v-else class="mt-1 text-xs text-slate-400">{{ productsPager.total ?? 0 }} {{ t('products') }}</p>
          </div>
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

        <!-- Subcategory horizontal scroll -->
        <div v-if="subcategories.length" class="mt-4 flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
          <Link
            v-for="sub in subcategories"
            :key="sub.id || sub.slug || sub.name"
            :href="`/categories/${encodeURIComponent(sub.slug || sub.id || sub.name)}`"
            class="flex shrink-0 items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900"
          >
            <span v-if="sub.image" class="h-5 w-5 overflow-hidden rounded-full">
              <img :src="sub.image" :alt="sub.name" class="h-full w-full object-cover" />
            </span>
            {{ sub.name }}
            <span v-if="sub.product_count" class="text-[0.6rem] text-slate-400">({{ sub.product_count }})</span>
          </Link>
        </div>
      </div>

      <!-- Main content: sidebar filters + product grid -->
      <div v-if="products.length" class="mx-auto mt-6 max-w-7xl px-4">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
          <!-- Desktop Sidebar Filters -->
          <aside class="hidden w-60 shrink-0 lg:block">
            <FilterSidebar
              :model-value="form"
              wrapper-class="sticky top-28 space-y-4"
              :attributes="attributes"
              :category-tree="categoryTree"
              :expanded-categories="expandedCategories"
              :selected-categories="selectedCategories"
              :variant-attribute-keys="variantAttributeKeys"
              @update:modelValue="updateForm"
              @apply="applyFilters"
              @reset="resetFilters"
              @toggle-category="toggleCategorySelection"
              @toggle-expand="toggleExpand"
            />
          </aside>

          <div class="min-w-0 flex-1">
            <!-- Sort + browse toolbar -->
            <div class="flex items-center justify-between gap-4">
              <p class="text-xs text-slate-500">{{ productsPager.total ?? 0 }} {{ t('products') }}</p>
              <select
                :value="form.sort"
                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-600 focus:border-slate-400 focus:outline-none"
                @change="handleSortChange($event.target.value)"
              >
                <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>

            <!-- Active filter chips -->
            <div v-if="activeFilters.length" class="mt-3 flex flex-wrap gap-2">
              <button
                v-for="filter in activeFilters"
                :key="filter.key"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-[0.65rem] font-semibold text-slate-600 transition hover:bg-slate-100"
                @click="clearFilter(filter.key)"
              >
                {{ filter.label }}
                <svg class="h-3 w-3 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 6l12 12M18 6l-12 12"/></svg>
              </button>
              <button type="button" class="text-xs font-semibold text-red-500 hover:text-red-600" @click="resetFilters">
                {{ t('Clear all') }}
              </button>
            </div>

            <!-- Product grid -->
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
              <ProductCard
                v-for="product in products"
                :key="product.id"
                :product="product"
                :currency="currency"
                :promotions="promotionList"
              />
            </div>

            <!-- Pagination -->
            <nav v-if="lastPage > 1" class="mt-8 flex items-center justify-center gap-1">
              <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-sm text-slate-500 transition hover:bg-slate-100 disabled:opacity-30 disabled:pointer-events-none" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 19l-7-7 7-7"/></svg>
              </button>
              <template v-for="(p, i) in pageNumbers" :key="i">
                <span v-if="p === '...'" class="px-1 text-xs text-slate-400">...</span>
                <button v-else type="button" class="flex h-8 min-w-[2rem] items-center justify-center rounded-lg px-2 text-xs font-semibold transition"
                  :class="p === currentPage ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="goToPage(p)">{{ p }}</button>
              </template>
              <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-sm text-slate-500 transition hover:bg-slate-100 disabled:opacity-30 disabled:pointer-events-none" :disabled="currentPage >= lastPage" @click="goToPage(currentPage + 1)">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"/></svg>
              </button>
            </nav>
          </div>
        </div>
      </div>

      <EmptyState v-else :eyebrow="t('Category')" :title="t('No products here yet')" :message="t('This collection is currently being selected. Browse other categories or check back soon.')">
        <template #actions>
          <Link href="/products" class="btn-primary">{{ t('Browse catalog') }}</Link>
        </template>
      </EmptyState>
    </div>

    <!-- Mobile Filters Bottom Sheet -->
    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="filtersOpen" class="fixed inset-0 z-[60] lg:hidden">
        <div class="absolute inset-0 bg-slate-900/20" @click="filtersOpen = false" />
      </div>
    </Transition>
    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-4 opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-4 opacity-0">
      <div v-if="filtersOpen" class="fixed inset-x-0 bottom-0 z-[70] max-h-[80vh] overflow-y-auto rounded-t-2xl border-t border-slate-200 bg-white p-5 pb-8 lg:hidden">
        <div class="flex items-center justify-between pb-3">
          <p class="text-sm font-bold text-slate-900">{{ t('Filters') }}</p>
          <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" @click="filtersOpen = false">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6l-12 12"/></svg>
          </button>
        </div>
        <FilterSidebar
          :model-value="form"
          :attributes="attributes"
          :category-tree="categoryTree"
          :expanded-categories="expandedCategories"
          :selected-categories="selectedCategories"
          :variant-attribute-keys="variantAttributeKeys"
          @update:modelValue="updateForm"
          @apply="applyFilters"
          @reset="resetFilters"
          @toggle-category="toggleCategorySelection"
          @toggle-expand="toggleExpand"
        />
      </div>
    </Transition>
  </StorefrontLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import Breadcrumbs from '@/Components/Breadcrumbs.vue'
import FilterSidebar from '@/Components/FilterSidebar.vue'
import ProductCard from '@/Components/ProductCard.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { useJsonLd } from '@/composables/useJsonLd.js'
import { useTranslations } from '@/i18n'

const props = defineProps({
  category: { type: Object, required: true },
  products: { type: Object, default: () => ({ data: [] }) },
  currency: { type: String, default: 'USD' },
  filters: { type: Object, default: () => ({}) },
  brands: { type: Array, default: () => [] },
  attributes: { type: Array, default: () => [] },
  subcategories: { type: Array, default: () => [] },
  breadcrumbs: { type: Array, default: () => [] },
  variantAttributeKeys: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const page = usePage()
const promotionList = computed(() => (page?.props?.promotions || page?.props?.homepagePromotions || []))

const form = reactive({
  q: props.filters.q ?? '',
  min_price: props.filters.min_price ?? '',
  max_price: props.filters.max_price ?? '',
  rating: props.filters.rating ?? '',
  in_stock: props.filters.in_stock ?? '',
  sort: props.filters.sort ?? '',
  page: props.filters.page ?? 1,
  categories: Array.isArray(props.filters.categories) ? props.filters.categories : [],
  ...Object.fromEntries((props.attributes || []).map(attr => [attr.key, props.filters[attr.key] ?? ''])),
})

const filtersOpen = ref(false)

const updateForm = (next) => Object.assign(form, next)

const activeFilters = computed(() => {
  const items = []
  if (form.q) items.push({ key: 'q', label: t('Search: :value', { value: form.q }) })
  if (form.min_price) items.push({ key: 'min_price', label: form.min_price + '+ CFA' })
  if (form.max_price) items.push({ key: 'max_price', label: t('Up to :value', { value: form.max_price }) })
  if (form.rating) items.push({ key: 'rating', label: form.rating + '+ ' + t('stars') })
  if (form.in_stock) items.push({ key: 'in_stock', label: t('In stock') })
  ;(props.attributes || []).forEach(attr => {
    if (form[attr.key]) items.push({ key: attr.key, label: form[attr.key] })
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
  form.sort = ''
  ;(props.attributes || []).forEach(attr => { form[attr.key] = '' })
  filtersOpen.value = false
  form.page = 1
  applyFilters()
}

const clearFilter = (key) => {
  form[key] = ''
  form.page = 1
  applyFilters()
}

const metaTitle = computed(() => props.category.meta_title || `${props.category.name} | Simbazu`)
const metaDescription = computed(() => props.category.meta_description || '')
const displayTitle = computed(() => props.category.hero_title || props.category.name)
const displaySubtitle = computed(() => props.category.hero_subtitle || props.category.description || '')

const sortOptions = computed(() => [
  { value: '', label: t('Relevance') },
  { value: 'newest', label: t('Newest') },
  { value: 'price_asc', label: t('Price: Low to High') },
  { value: 'price_desc', label: t('Price: High to Low') },
  { value: 'rating', label: t('Rating') },
  { value: 'popularity', label: t('Popularity') },
])

const handleSortChange = (value) => {
  form.sort = value
  applyFilters()
}

const categorySchema = computed(() => {
  const baseUrl = window.location.origin
  const schema = {
    '@context': 'https://schema.org/',
    '@type': 'CollectionPage',
    name: props.category.name,
    description: metaDescription.value || displaySubtitle.value,
    url: `${baseUrl}/categories/${props.category.slug}`,
  }
  if (props.category.hero_image) schema.image = props.category.hero_image
  if (products.value.length > 0) {
    schema.mainEntity = {
      '@type': 'ItemList',
      itemListElement: products.value.slice(0, 10).map((product, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        item: { '@type': 'Product', name: product.name, url: product.url || `${baseUrl}/products/${product.slug}`, image: product.image },
      })),
    }
  }
  return JSON.stringify(schema)
})

const productsPager = computed(() => props.products ?? { data: [] })
const products = computed(() => productsPager.value.data ?? [])

const mapSubcategory = (node) => ({
  ...node,
  id: node.id ?? node.slug ?? node.name,
  name: node.name,
  slug: node.slug,
  image: node.image || node.thumbnail || node.hero_image || node.banner_image,
  product_count: node.product_count || node.products_count || 0,
  children: Array.isArray(node.children) ? node.children.map(mapSubcategory) : [],
})

const subcategories = computed(() => {
  const sources = [props.subcategories, props.category?.subcategories, props.category?.children, props.category?.children_recursive].find((arr) => Array.isArray(arr) && arr.length)
  return sources ? sources.map(mapSubcategory) : []
})

const normalizeTree = (nodes = []) => nodes.map((node) => ({ ...node, id: node.id ?? node.slug ?? node.name, name: node.name, children: Array.isArray(node.children) ? normalizeTree(node.children) : [] }))

const categoryTree = computed(() => normalizeTree(props.category.children || props.category.subcategories || props.category.children_recursive || props.subcategories || (page?.props?.categories ?? []) || []))

const expandedCategories = ref(new Set())
const selectedCategories = computed(() => Array.isArray(form.categories) ? form.categories : [])

watch(categoryTree, (nodes) => {
  const set = new Set(expandedCategories.value)
  nodes.slice(0, 5).forEach((n) => set.add(n.id))
  expandedCategories.value = set
}, { immediate: true })

const toggleCategorySelection = (id) => {
  const set = new Set(selectedCategories.value)
  if (set.has(id)) set.delete(id); else set.add(id)
  form.categories = Array.from(set)
  form.page = 1
  applyFilters()
}

const toggleExpand = (id) => {
  const set = new Set(expandedCategories.value)
  if (set.has(id)) set.delete(id); else set.add(id)
  expandedCategories.value = set
}

const currentPage = computed(() => productsPager.value.current_page ?? 1)
const lastPage = computed(() => productsPager.value.last_page ?? 1)

const pageNumbers = computed(() => {
  const current = currentPage.value
  const last = lastPage.value
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
  if (page < 1 || page > (productsPager.value.last_page ?? 1)) return
  form.page = page
  router.get(`/categories/${props.category.slug}`, { ...form }, { preserveState: true, replace: true })
}
</script>