<template>
  <StorefrontLayout>
    <Head :title="metaTitle">
      <meta name="description" head-key="description" :content="metaDescription" />
    </Head>

    <div class="space-y-5 bg-[#f7f3eb] pb-24 sm:space-y-6 sm:pb-28">
      <Breadcrumbs :items="breadcrumbs" class="px-1" />

      <section class="overflow-hidden rounded-[1.8rem] bg-[#111111] text-white shadow-[0_20px_48px_rgba(15,23,42,0.16)]">
        <div class="grid gap-3 p-4 sm:p-5 lg:grid-cols-[1.15fr_0.85fr] lg:items-stretch lg:p-6">
          <div class="space-y-3">
            <div class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
              <span class="shrink-0 rounded-full bg-[#ff6b35] px-3 py-1 text-[0.64rem] font-bold uppercase tracking-[0.2em] text-white">{{ t('Category') }}</span>
              <span class="shrink-0 rounded-full bg-white/10 px-3 py-1 text-[0.64rem] font-bold uppercase tracking-[0.16em] text-white/90">{{ t(':count products', { count: productsPager.total ?? 0 }) }}</span>
              <span v-if="subcategories.length" class="shrink-0 rounded-full bg-white/10 px-3 py-1 text-[0.64rem] font-bold uppercase tracking-[0.16em] text-white/90">{{ t(':count subcategories', { count: subcategories.length }) }}</span>
            </div>

            <div class="rounded-[1.35rem] border border-white/10 bg-white/8 p-4 backdrop-blur">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <h1 class="text-[1.5rem] font-black leading-[0.95] tracking-[-0.04em] sm:text-[2rem]">{{ displayTitle }}</h1>
                  <p v-if="displaySubtitle" class="mt-2 max-w-2xl text-sm leading-6 text-white/74">{{ displaySubtitle }}</p>
                  <p v-else-if="metaDescription" class="mt-2 max-w-2xl text-sm leading-6 text-white/74">{{ metaDescription }}</p>
                </div>
                <div class="shrink-0 rounded-[1rem] bg-[#facc15] px-2.5 py-1.5 text-[0.62rem] font-black uppercase tracking-[0.16em] text-slate-950">
                  {{ categoryPromoCountdown ? t('Live') : t('Hot') }}
                </div>
              </div>

              <div v-if="categoryPromotion" class="mt-3 flex flex-wrap items-center gap-2 rounded-[1rem] border border-[#f7c16d]/40 bg-[#fffbf2] px-3 py-2 text-xs font-semibold text-amber-900">
                <span>{{ categoryPromotion.badge_text || categoryPromotion.name }}</span>
                <span v-if="categoryPromotion.value_type === 'percentage'">-{{ categoryPromotion.value }}%</span>
                <span v-else-if="categoryPromotion.value_type === 'fixed'">-{{ categoryPromotion.value }}</span>
                <span v-if="categoryPromotion.apply_hint" class="text-[11px] font-medium text-amber-800">
                  {{ categoryPromotion.apply_hint }}
                </span>
                <span v-if="categoryPromoCountdown" class="text-[11px] font-semibold text-amber-700">
                  {{ t('Ends in') }} {{ categoryPromoCountdown }}
                </span>
              </div>

              <div class="mt-3 grid grid-cols-3 gap-2">
                <article class="rounded-[1rem] border border-white/10 bg-black/20 px-3 py-2.5">
                  <p class="text-[0.56rem] font-bold uppercase tracking-[0.18em] text-white/52">{{ t('Results') }}</p>
                  <p class="mt-1 text-[0.82rem] font-bold text-white">{{ productsPager.total ?? 0 }}</p>
                </article>
                <article class="rounded-[1rem] border border-white/10 bg-black/20 px-3 py-2.5">
                  <p class="text-[0.56rem] font-bold uppercase tracking-[0.18em] text-white/52">{{ t('Subcats') }}</p>
                  <p class="mt-1 text-[0.82rem] font-bold text-white">{{ subcategories.length }}</p>
                </article>
                <article class="rounded-[1rem] border border-white/10 bg-black/20 px-3 py-2.5">
                  <p class="text-[0.56rem] font-bold uppercase tracking-[0.18em] text-white/52">{{ t('Sort') }}</p>
                  <p class="mt-1 text-[0.82rem] font-bold text-white">{{ sortLabel }}</p>
                </article>
              </div>

              <div class="mt-3 flex flex-wrap gap-2">
                <Link
                  v-if="category.hero_cta_label && category.hero_cta_link"
                  :href="category.hero_cta_link"
                  class="inline-flex min-h-11 items-center justify-center rounded-full bg-[#ff6b35] px-4 text-[0.78rem] font-bold uppercase tracking-[0.12em] text-white shadow-[0_10px_24px_rgba(255,107,53,0.34)] transition hover:bg-[#ff5420]"
                >
                  {{ category.hero_cta_label }}
                </Link>
                <button
                  type="button"
                  class="inline-flex min-h-11 items-center justify-center rounded-full border border-white/16 bg-white/8 px-4 text-[0.78rem] font-bold uppercase tracking-[0.12em] text-white transition hover:bg-white/12 lg:hidden"
                  @click="filtersOpen = true"
                >
                  {{ t('Open filters') }}
                </button>
              </div>
            </div>

            <TrustBadges compact :columns="3" tone="muted" class="pt-1" />
          </div>

          <div class="grid gap-3">
            <div v-if="category.hero_image" class="overflow-hidden rounded-[1.4rem] border border-white/10 bg-white/8 p-1.5 backdrop-blur">
              <img :src="category.hero_image" :alt="category.name" class="aspect-[1.12] w-full rounded-[1.1rem] object-cover" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <article class="rounded-[1.25rem] bg-[#ff6b35] px-3 py-3 text-white">
                <p class="text-[0.54rem] font-bold uppercase tracking-[0.18em] text-white/72">{{ t('Fast browse') }}</p>
                <p class="mt-1 text-sm font-black leading-4">{{ t('Quick category entry') }}</p>
                <p class="mt-1 text-[0.7rem] leading-4 text-white/80">{{ t('Jump from subcategory to products in one tap.') }}</p>
              </article>
              <article class="rounded-[1.25rem] border border-white/10 bg-white/8 px-3 py-3 backdrop-blur">
                <p class="text-[0.54rem] font-bold uppercase tracking-[0.18em] text-white/55">{{ t('Buying cues') }}</p>
                <p class="mt-1 text-sm font-black leading-4 text-white">{{ t('Price, stock, reviews') }}</p>
                <p class="mt-1 text-[0.7rem] leading-4 text-white/72">{{ t('Everything visible before product entry.') }}</p>
              </article>
            </div>
          </div>
        </div>
      </section>

      <section class="space-y-5">
      <!-- Subcategory cards -->
      <div v-if="subcategories.length" class="rounded-[1.6rem] bg-white p-4 shadow-[0_16px_38px_rgba(15,23,42,0.05)]">
        <div class="flex items-end justify-between gap-3">
          <div>
            <p class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#ff6b35]">{{ t('Subcategories') }}</p>
            <h2 class="text-lg font-black tracking-[-0.03em] text-slate-950">{{ t('Jump into a more specific lane') }}</h2>
          </div>
        </div>
        <div class="mt-3 flex gap-3 overflow-x-auto pb-1 sm:mt-4 sm:grid sm:grid-cols-3 sm:overflow-visible lg:grid-cols-4 xl:grid-cols-5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <Link
            v-for="sub in subcategories"
            :key="sub.id || sub.slug || sub.name"
            :href="`/categories/${encodeURIComponent(sub.slug || sub.id || sub.name)}`"
            class="subcat-card shrink-0 w-[152px] sm:w-auto"
          >
            <div class="subcat-thumb" v-if="sub.image"><img :src="sub.image" :alt="sub.name" loading="lazy" /></div>
            <div class="subcat-fallback" v-else>{{ sub.name?.slice(0,2) || 'SC' }}</div>
            <p class="subcat-name">{{ sub.name }}</p>
            <p v-if="sub.product_count" class="subcat-count">{{ t(':count products', { count: sub.product_count }) }}</p>
          </Link>
        </div>
      </div>

      <div v-if="products.length" class="space-y-4">
      <div class="flex flex-col gap-5 lg:flex-row lg:gap-8">
        <!-- Sidebar Filters -->
        <aside class="hidden lg:block min-w-[260px] max-w-xs">
          <FilterSidebar
            :model-value="form"
            wrapper-class="sticky top-28 rounded-[1.6rem] border border-[#eadfce] bg-white h-fit space-y-4 p-5 shadow-[0_16px_38px_rgba(15,23,42,0.05)]"
            :brands="brands"
            :attributes="attributes"
            :category-tree="categoryTree"
            :expanded-categories="expandedCategories"
            :selected-categories="selectedCategories"
            @update:modelValue="updateForm"
            @apply="applyFilters"
            @reset="resetFilters"
            @toggle-category="toggleCategorySelection"
            @toggle-expand="toggleExpand"
          />
        </aside>

        <div class="flex-1 space-y-4">
          <BrowseToolbar
            :total-count="productsPager.total ?? products.length"
            :active-filter-count="activeFilters.length"
            :search="form.q"
            :search-placeholder="t('Search in this category')"
            :sort="form.sort"
            :sort-options="sortOptions"
            :filter-button-label="t('Filters')"
            @update:search="form.q = $event"
            @update:sort="handleSortChange"
            @open-filters="filtersOpen = true"
            @submit-search="applyFilters"
          />

          <!-- Active filters chips -->
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

          <!-- Product grid -->
          <div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4">
            <ProductCard
              v-for="product in products"
              :key="product.id"
              :product="product"
              :currency="currency"
              :promotions="(page && page.props && (page.props.promotions || page.props.homepagePromotions)) ? (page.props.promotions || page.props.homepagePromotions) : []"
            />
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
          <FilterSidebar
            :model-value="form"
            wrapper-class="mt-4 max-h-[70vh] space-y-4 overflow-y-auto pb-6"
            :brands="brands"
            :attributes="attributes"
            :category-tree="categoryTree"
            :expanded-categories="expandedCategories"
            :selected-categories="selectedCategories"
            @update:modelValue="updateForm"
            @apply="applyFilters"
            @reset="resetFilters"
            @toggle-category="toggleCategorySelection"
            @toggle-expand="toggleExpand"
          />
        </div>
      </Transition>
      </div>
    </section>

    <PaginationRail
      v-if="products.length"
      :current-page="productsPager.current_page ?? 1"
      :last-page="productsPager.last_page ?? 1"
      :can-next="hasMore"
      :loading="false"
      :show-sort="false"
      @prev="goToPage(Math.max(1, (productsPager.current_page ?? 1) - 1))"
      @next="goToPage(Math.min(productsPager.last_page ?? 1, (productsPager.current_page ?? 1) + 1))"
      @filter="() => { filtersOpen = true }"
    />
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
    </div>
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
import PaginationRail from '@/Components/PaginationRail.vue'
import { useJsonLd } from '@/composables/useJsonLd.js'
import BrowseToolbar from '@/Components/storefront/BrowseToolbar.vue'
import TrustBadges from '@/Components/TrustBadges.vue'
import { useTranslations } from '@/i18n'
import { usePromoNow, formatCountdown } from '@/composables/usePromoCountdown.js'

const props = defineProps({
  category: { type: Object, required: true },
  products: { type: Object, default: () => ({ data: [] }) },
  currency: { type: String, default: 'USD' },
  filters: { type: Object, default: () => ({}) },
  brands: { type: Array, default: () => [] },
  attributes: { type: Array, default: () => [] },
  subcategories: { type: Array, default: () => [] },
  breadcrumbs: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const page = usePage ? usePage() : null
const now = usePromoNow()

const activePromotions = computed(() => {
  return (page && page.props && (page.props.promotions || page.props.homepagePromotions)) ? (page.props.promotions || page.props.homepagePromotions) : []
})

const categoryPromotion = computed(() => {
  if (!activePromotions.value.length) return null
  return activePromotions.value.find((promo) => {
    const targets = promo.targets || []
    if (targets.length === 0) return promo.is_sitewide
    return targets.some((target) => target.target_type === 'category' && target.target_id == props.category.id)
  }) ?? null
})

const categoryPromoCountdown = computed(() => formatCountdown(categoryPromotion.value?.end_at, now.value))

const form = reactive({
  q: props.filters.q ?? '',
  min_price: props.filters.min_price ?? '',
  max_price: props.filters.max_price ?? '',
  rating: props.filters.rating ?? '',
  in_stock: props.filters.in_stock ?? '',
  brand: props.filters.brand ?? '',
  sort: props.filters.sort ?? '',
  page: props.filters.page ?? 1,
  categories: Array.isArray(props.filters.categories) ? props.filters.categories : [],
  // Dynamic attributes
  ...Object.fromEntries((props.attributes || []).map(attr => [attr.key, props.filters[attr.key] ?? ''])),
})


const filtersOpen = ref(false)

const updateForm = (next) => {
  Object.assign(form, next)
}

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

const metaTitle = computed(() => props.category.meta_title || `${props.category.name} | Simbazu`)
const metaDescription = computed(() => props.category.meta_description || '')
const displayTitle = computed(() => props.category.hero_title || props.category.name)
const displaySubtitle = computed(() => props.category.hero_subtitle || props.category.description || '')
const sortOptions = computed(() => [
  { value: '', label: t('Relevance') },
  { value: 'price_asc', label: t('Price: Low to High') },
  { value: 'price_desc', label: t('Price: High to Low') },
  { value: 'newest', label: t('Newest') },
  { value: 'rating', label: t('Rating') },
  { value: 'popularity', label: t('Popularity') },
])
const sortLabel = computed(() => {
  const labels = {
    '': t('Relevance'),
    price_asc: t('Low-high'),
    price_desc: t('High-low'),
    newest: t('Newest'),
    rating: t('Rating'),
    popularity: t('Popular'),
  }

  return labels[form.sort ?? ''] || t('Relevance')
})

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
  
  if (props.category.hero_image) {
    schema.image = props.category.hero_image
  }
  
  if (products.value.length > 0) {
    schema.mainEntity = {
      '@type': 'ItemList',
      itemListElement: products.value.slice(0, 10).map((product, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        item: {
          '@type': 'Product',
          name: product.name,
          url: product.url || `${baseUrl}/products/${product.slug}`,
          image: product.image,
          price: product.price ? `${props.currency} ${product.price}` : undefined,
        },
      })),
    }
  }
  
  return JSON.stringify(schema)
})

// Inject JSON-LD schema (temporarily disabled due to initialization error)
// useJsonLd(categorySchema)

const productsPager = computed(() => props.products ?? { data: [] })
const products = computed(() => productsPager.value.data ?? [])
const breadcrumbs = computed(() => props.breadcrumbs ?? [])
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
  const sources = [
    props.subcategories,
    props.category?.subcategories,
    props.category?.children,
    props.category?.children_recursive,
  ].find((arr) => Array.isArray(arr) && arr.length)

  if (!sources) return []
  return sources.map(mapSubcategory)
})
const hasMore = computed(() => (productsPager.value.current_page ?? 1) < (productsPager.value.last_page ?? 1))

const normalizeTree = (nodes = []) =>
  nodes.map((node) => ({
    ...node,
    id: node.id ?? node.slug ?? node.name,
    name: node.name,
    children: Array.isArray(node.children) ? normalizeTree(node.children) : [],
  }))

const categoryTree = computed(() => normalizeTree(
  props.category.children
  || props.category.subcategories
  || props.category.children_recursive
  || props.subcategories
  || (page?.props?.categories ?? [])
  || []
))

const expandedCategories = ref(new Set())
const selectedCategories = computed(() => Array.isArray(form.categories) ? form.categories : [])

// Auto-expand first level when tree loads
watch(categoryTree, (nodes) => {
  const set = new Set(expandedCategories.value)
  nodes.slice(0, 5).forEach((n) => set.add(n.id))
  expandedCategories.value = set
}, { immediate: true })

const toggleCategorySelection = (id) => {
  const set = new Set(selectedCategories.value)
  if (set.has(id)) set.delete(id)
  else set.add(id)
  form.categories = Array.from(set)
  form.page = 1
  applyFilters()
}

const toggleExpand = (id) => {
  const set = new Set(expandedCategories.value)
  if (set.has(id)) set.delete(id)
  else set.add(id)
  expandedCategories.value = set
}

const goToPage = (page) => {
  if (page < 1 || page > (productsPager.value.last_page ?? 1)) {
    return
  }
  form.page = page
  router.get(`/categories/${props.category.slug}`, { ...form }, { preserveState: true, replace: true })
}
</script>

<style scoped>
.subcat-card {
  display: grid;
  gap: 6px;
  padding: 10px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #fff;
  transition: transform 150ms ease, box-shadow 150ms ease;
}
.subcat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
}
.subcat-thumb {
  width: 100%;
  aspect-ratio: 4 / 3;
  border-radius: 12px;
  overflow: hidden;
  background: #f8fafc;
}
.subcat-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.subcat-fallback {
  width: 100%;
  aspect-ratio: 4 / 3;
  border-radius: 12px;
  background: #f8fafc;
  display: grid;
  place-items: center;
  font-weight: 800;
  color: #0f172a;
}
.subcat-name {
  font-weight: 700;
  color: #0f172a;
  font-size: 13px;
}
.subcat-count {
  font-size: 12px;
  color: #64748b;
}
</style>
