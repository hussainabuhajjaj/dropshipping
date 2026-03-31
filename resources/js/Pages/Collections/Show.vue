<template>
  <StorefrontLayout>
    <Head :title="collection.seo_title || collection.title">
      <meta name="description" head-key="description" :content="collection.seo_description || collection.description" />
    </Head>

    <section class="space-y-8">
      <div class="space-y-3">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
          {{ collection.hero_kicker || t('Collection') }}
        </p>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900">{{ collection.title }}</h1>
          <p class="max-w-xl text-sm text-slate-500">
            {{ collection.hero_subtitle || collection.description || t('Curated products picked for this collection.') }}
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
          <span>{{ products.length }} {{ t('items') }}</span>
          <span class="rounded-full bg-slate-100 px-3 py-1">{{ typeLabel }}</span>
          <span v-if="collection.starts_at" class="rounded-full bg-slate-100 px-3 py-1">
            {{ t('Starts') }} {{ formatDate(collection.starts_at) }}
          </span>
          <span v-if="collection.ends_at" class="rounded-full bg-slate-100 px-3 py-1">
            {{ t('Ends') }} {{ formatDate(collection.ends_at) }}
          </span>
        </div>
      </div>

      <div v-if="activeFilterPills.length" class="flex flex-wrap gap-2">
        <div class="flex flex-wrap gap-2">
          <span
            v-for="pill in activeFilterPills"
            :key="pill"
            class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600"
          >
            {{ pill }}
          </span>
        </div>
      </div>

      <div class="grid gap-8 lg:grid-cols-[260px,1fr]">
        <aside class="card hidden h-fit space-y-4 p-5 lg:block">
          <div v-if="collection.hero_image" class="overflow-hidden rounded-2xl border border-slate-100 bg-slate-100">
            <img :src="collection.hero_image" :alt="collection.title" class="h-44 w-full object-cover" />
          </div>
          <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Collection details') }}</p>
            <p class="text-sm text-slate-600">{{ collection.description || t('Products attached to this storefront collection.') }}</p>
          </div>
          <div class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Search') }}</p>
            <input v-model="searchTerm" type="search" :placeholder="t('Search within this collection')" class="input-base" />
          </div>
          <div class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Price range') }}</p>
            <div class="flex gap-2">
              <input v-model="minPrice" type="number" min="0" :placeholder="t('Min')" class="input-base" />
              <input v-model="maxPrice" type="number" min="0" :placeholder="t('Max')" class="input-base" />
            </div>
          </div>
          <div v-if="colorOptions.length" class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Color') }}</p>
            <select v-model="selectedColor" class="input-base">
              <option value="">{{ t('All colors') }}</option>
              <option v-for="option in colorOptions" :key="option" :value="option">{{ option }}</option>
            </select>
          </div>
          <div v-if="sizeOptions.length" class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Size') }}</p>
            <select v-model="selectedSize" class="input-base">
              <option value="">{{ t('All sizes') }}</option>
              <option v-for="option in sizeOptions" :key="option" :value="option">{{ option }}</option>
            </select>
          </div>
          <button type="button" class="btn-ghost text-left" @click="resetFilters">
            {{ t('Clear filters') }}
          </button>
          <Link v-if="collection.hero_cta_label && collection.hero_cta_url" :href="collection.hero_cta_url" class="btn-secondary text-center">
            {{ collection.hero_cta_label }}
          </Link>
        </aside>

        <div>
          <div class="mb-4 grid gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm lg:hidden">
            <input v-model="searchTerm" type="search" :placeholder="t('Search within this collection')" class="input-base" />
            <div class="grid gap-3 sm:grid-cols-2">
              <input v-model="minPrice" type="number" min="0" :placeholder="t('Min price')" class="input-base" />
              <input v-model="maxPrice" type="number" min="0" :placeholder="t('Max price')" class="input-base" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2" v-if="colorOptions.length || sizeOptions.length">
              <select v-if="colorOptions.length" v-model="selectedColor" class="input-base">
                <option value="">{{ t('All colors') }}</option>
                <option v-for="option in colorOptions" :key="option" :value="option">{{ option }}</option>
              </select>
              <select v-if="sizeOptions.length" v-model="selectedSize" class="input-base">
                <option value="">{{ t('All sizes') }}</option>
                <option v-for="option in sizeOptions" :key="option" :value="option">{{ option }}</option>
              </select>
            </div>
          </div>

        <div v-if="filteredProducts.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <ProductCard
            v-for="product in filteredProducts"
            :key="product.id"
            :product="product"
            :currency="currency"
            :promotions="promotions"
          />
        </div>
        <EmptyState
          v-else
          :eyebrow="t('Collection')"
          :title="t('No products match these filters')"
          :message="t('Try clearing filters or search for another term inside this collection.')"
        >
          <template #actions>
            <button type="button" class="btn-primary" @click="resetFilters">{{ t('Clear filters') }}</button>
          </template>
        </EmptyState>
        </div>
      </div>

      <div v-if="collection.content" class="prose max-w-none prose-slate" v-html="collection.content"></div>
    </section>
  </StorefrontLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ProductCard from '@/Components/ProductCard.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  collection: { type: Object, required: true },
  products: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const { t, locale } = useTranslations()
const page = usePage()
const promotions = computed(() => page.props.promotions || page.props.homepagePromotions || [])
const currency = computed(() => page.props.currency || 'USD')
const searchTerm = ref('')
const minPrice = ref('')
const maxPrice = ref('')
const selectedColor = ref('')
const selectedSize = ref('')

const typeLabel = computed(() => {
  switch (props.collection.type) {
    case 'guide':
      return t('Buying guide')
    case 'seasonal':
      return t('Seasonal drop')
    case 'drop':
      return t('Limited drop')
    default:
      return t('Collection')
  }
})

const formatDate = (value) => {
  if (!value) return ''
  return new Date(value).toLocaleDateString(locale.value || 'en')
}

const normalizeValue = (value) => String(value ?? '').trim().toLowerCase()

const variantOptions = computed(() =>
  props.products.flatMap((product) => Array.isArray(product.variants) ? product.variants : [])
)

const deriveOptionValues = (matcher) => {
  const values = variantOptions.value.flatMap((variant) => {
    const options = variant?.options && typeof variant.options === 'object' ? variant.options : {}
    return Object.entries(options)
      .filter(([key]) => matcher(String(key)))
      .map(([, value]) => String(value).trim())
      .filter(Boolean)
  })

  return [...new Set(values)].sort((a, b) => a.localeCompare(b))
}

const colorOptions = computed(() =>
  deriveOptionValues((key) => ['color', 'colour', 'lenses color'].includes(normalizeValue(key)))
)

const sizeOptions = computed(() =>
  deriveOptionValues((key) => ['size'].includes(normalizeValue(key)))
)

const matchesVariantOption = (product, selected, matcher) => {
  if (!selected) return true
  const variants = Array.isArray(product.variants) ? product.variants : []
  return variants.some((variant) => {
    const options = variant?.options && typeof variant.options === 'object' ? variant.options : {}
    return Object.entries(options).some(([key, value]) => matcher(String(key)) && normalizeValue(value) === normalizeValue(selected))
  })
}

const filteredProducts = computed(() => {
  const query = normalizeValue(searchTerm.value)
  const min = minPrice.value !== '' ? Number(minPrice.value) : null
  const max = maxPrice.value !== '' ? Number(maxPrice.value) : null

  return props.products.filter((product) => {
    const name = normalizeValue(product.name)
    const description = normalizeValue(product.description)
    const category = normalizeValue(product.category)
    const price = Number(product.price ?? 0)

    if (query && ![name, description, category].some((value) => value.includes(query))) {
      return false
    }
    if (min !== null && Number.isFinite(min) && price < min) {
      return false
    }
    if (max !== null && Number.isFinite(max) && price > max) {
      return false
    }
    if (!matchesVariantOption(product, selectedColor.value, (key) => ['color', 'colour', 'lenses color'].includes(normalizeValue(key)))) {
      return false
    }
    if (!matchesVariantOption(product, selectedSize.value, (key) => ['size'].includes(normalizeValue(key)))) {
      return false
    }

    return true
  })
})

const activeFilterPills = computed(() => {
  const pills = []
  if (searchTerm.value.trim()) pills.push(`${t('Search')}: ${searchTerm.value.trim()}`)
  if (minPrice.value !== '') pills.push(`${t('Min')}: ${minPrice.value}`)
  if (maxPrice.value !== '') pills.push(`${t('Max')}: ${maxPrice.value}`)
  if (selectedColor.value) pills.push(`${t('Color')}: ${selectedColor.value}`)
  if (selectedSize.value) pills.push(`${t('Size')}: ${selectedSize.value}`)
  return pills
})

const resetFilters = () => {
  searchTerm.value = ''
  minPrice.value = ''
  maxPrice.value = ''
  selectedColor.value = ''
  selectedSize.value = ''
}
</script>
