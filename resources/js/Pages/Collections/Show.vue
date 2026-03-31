<template>
  <StorefrontLayout>
    <Head :title="collection.seo_title || collection.title">
      <meta name="description" head-key="description" :content="collection.seo_description || collection.description" />
    </Head>

    <section class="space-y-10">
      <div class="grid gap-8 lg:grid-cols-[1.1fr,0.9fr]">
        <div class="space-y-4">
          <p v-if="collection.hero_kicker" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
            {{ collection.hero_kicker }}
          </p>
          <h1 class="text-3xl font-semibold text-slate-900">{{ collection.title }}</h1>
          <p class="text-sm text-slate-600">{{ collection.description }}</p>
          <div class="flex flex-wrap gap-2 text-xs text-slate-500">
            <span class="rounded-full bg-slate-100 px-3 py-1">{{ typeLabel }}</span>
            <span v-if="collection.starts_at" class="rounded-full bg-slate-100 px-3 py-1">
              {{ t('Starts') }} {{ formatDate(collection.starts_at) }}
            </span>
            <span v-if="collection.ends_at" class="rounded-full bg-slate-100 px-3 py-1">
              {{ t('Ends') }} {{ formatDate(collection.ends_at) }}
            </span>
          </div>
          <Link v-if="collection.hero_cta_label && collection.hero_cta_url" :href="collection.hero_cta_url" class="btn-primary">
            {{ collection.hero_cta_label }}
          </Link>
        </div>
        <div class="overflow-hidden rounded-3xl border border-slate-100 bg-slate-100">
          <img v-if="collection.hero_image" :src="collection.hero_image" :alt="collection.title" class="h-full w-full object-cover" />
        </div>
      </div>

      <div v-if="collection.content" class="prose max-w-none prose-slate" v-html="collection.content"></div>

      <div v-if="filterPills.length" class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-slate-900">{{ t('Filters') }}</h2>
        </div>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="pill in filterPills"
            :key="pill"
            class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600"
          >
            {{ pill }}
          </span>
        </div>
      </div>

      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-slate-900">{{ t('Featured products') }}</h2>
          <span class="text-xs text-slate-500">{{ products.length }} {{ t('items') }}</span>
        </div>
        <div v-if="products.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <ProductCard
            v-for="product in products"
            :key="product.id"
            :product="product"
            :currency="currency"
            :promotions="promotions"
          />
        </div>
        <div v-else class="card-muted p-5 text-sm text-slate-600">
          {{ t('No products are available for this collection yet.') }}
        </div>
      </div>
    </section>
  </StorefrontLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ProductCard from '@/Components/ProductCard.vue'
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

const filterPills = computed(() => {
  const pills = []
  const priceRange = props.filters?.price_range
  if (priceRange && (priceRange.min !== null || priceRange.max !== null)) {
    pills.push(`${t('Price')}: ${priceRange.min ?? 0} - ${priceRange.max ?? 0}`)
  }

  if (Array.isArray(props.filters?.brands) && props.filters.brands.length) {
    pills.push(`${t('Brands')}: ${props.filters.brands.slice(0, 4).join(', ')}`)
  }

  if (Array.isArray(props.filters?.attributeDefs)) {
    props.filters.attributeDefs
      .filter((item) => Array.isArray(item.options) && item.options.length)
      .slice(0, 6)
      .forEach((item) => {
        pills.push(`${item.label}: ${item.options.slice(0, 3).join(', ')}`)
      })
  }

  return pills
})
</script>
