<template>
  <StorefrontLayout>
    <div class="min-h-screen bg-white pb-28">
      <!-- Category Pills -->
      <div class="sticky top-0 z-40 overflow-x-auto border-b border-gray-100 bg-white/95 backdrop-blur [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <div class="flex gap-2 px-4 py-3">
          <Link
            v-for="cat in scrollCategories"
            :key="cat.id"
            :href="cat.href"
            class="shrink-0 whitespace-nowrap rounded-full border px-4 py-1.5 text-sm font-medium transition cursor-pointer"
            :class="activeCategory === cat.id ? 'border-slate-900 bg-slate-900 text-white' : 'border-gray-200 text-slate-600 hover:border-slate-400 hover:text-slate-900'"
            @click="activeCategory = cat.id"
          >
            {{ cat.name }}
          </Link>
        </div>
      </div>

      <!-- Hero Banner Slider (full-width) -->
      <div
        class="relative bg-gray-100"
        @mouseenter="pauseAutoPlay"
        @mouseleave="resumeAutoPlay"
        @touchstart.passive="pauseAutoPlay"
        @touchend.passive="resumeAutoPlay"
      >
        <div
          class="flex transition-transform duration-500 ease-out"
          :style="{ transform: `translateX(-${currentSlide * 100}%)` }"
        >
          <div
            v-for="(slide, idx) in heroSlides"
            :key="idx"
            class="relative w-full shrink-0"
          >
            <div
              v-if="slide.image"
              class="aspect-[1.2/1] w-full bg-cover bg-center sm:aspect-[2.5/1]"
              :style="{ backgroundImage: `url(${slide.image})` }"
            >
              <div class="flex h-full flex-col justify-end bg-gradient-to-t from-black/50 to-transparent p-6 text-white">
                <p v-if="slide.badge" class="mb-1 text-xs font-bold uppercase tracking-widest text-yellow-400">
                  {{ slide.badge }}
                </p>
                <p class="text-xl font-black sm:text-3xl">{{ slide.title }}</p>
                <p v-if="slide.subtitle" class="mt-1 text-sm text-white/80 line-clamp-2">{{ slide.subtitle }}</p>
                <Link
                  v-if="slide.primary"
                  :href="slide.primary.href"
                  class="mt-4 inline-flex h-10 w-fit items-center rounded-full bg-white px-6 text-sm font-bold text-slate-900 shadow-lg transition hover:bg-gray-100 active:scale-95"
                >
                  {{ slide.primary.label }}
                </Link>
              </div>
            </div>
            <div v-else class="flex aspect-[1.2/1] w-full items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200 sm:aspect-[2.5/1]">
              <div class="text-center">
                <p class="text-lg font-bold text-slate-400">{{ t('Bienvenue sur Simbazu') }}</p>
                <Link
                  href="/products"
                  class="mt-3 inline-flex h-10 items-center rounded-full bg-slate-900 px-6 text-sm font-bold text-white transition hover:bg-slate-800 active:scale-95"
                >
                  {{ t('Commencer') }}
                </Link>
              </div>
            </div>
          </div>
        </div>

        <button
          type="button"
          class="absolute left-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-lg backdrop-blur transition hover:bg-white active:scale-90 cursor-pointer"
          @click="prevSlide"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 19l-7-7 7-7"/></svg>
        </button>
        <button
          type="button"
          class="absolute right-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-lg backdrop-blur transition hover:bg-white active:scale-90 cursor-pointer"
          @click="nextSlide"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 5l7 7-7 7"/></svg>
        </button>

        <div class="absolute bottom-4 left-1/2 flex -translate-x-1/2 gap-2">
          <button
            v-for="(_, idx) in heroSlides"
            :key="idx"
            type="button"
            class="h-2 cursor-pointer rounded-full transition-all"
            :class="idx === currentSlide ? 'w-6 bg-white' : 'w-2 bg-white/50 hover:bg-white/70'"
            @click="currentSlide = idx"
          />
        </div>
      </div>

      <!-- Category Grid -->
      <div v-if="scrollCategories.length" class="mt-6 grid grid-cols-4 gap-3 px-4 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8">
        <Link
          v-for="cat in scrollCategories"
          :key="cat.id"
          :href="cat.href"
          class="flex cursor-pointer flex-col items-center gap-1.5 transition active:scale-95"
        >
          <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-gray-100 shadow-sm ring-1 ring-gray-200/50 transition hover:shadow-md hover:ring-2 hover:ring-slate-300 sm:h-18 sm:w-18">
            <img
              v-if="cat.image"
              :src="cat.image"
              :alt="cat.name"
              class="h-full w-full object-cover"
              loading="lazy"
            />
            <span v-else class="text-lg font-bold text-gray-400">{{ cat.short }}</span>
          </div>
          <span class="text-center text-[0.6rem] font-semibold text-slate-600 leading-tight line-clamp-2 sm:text-xs">{{ cat.name }}</span>
        </Link>
      </div>

      <!-- Flash Deals -->
      <section v-if="flashFeed.length" class="mt-8 px-4">
        <div class="mb-4 flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-red-500 to-red-600 shadow-sm">
              <svg class="h-4 w-4 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            </div>
            <div>
              <h2 class="text-base font-black text-slate-900">{{ t('Bonnes Affaires') }}</h2>
              <p class="text-xs text-slate-400">{{ t('Offres à durée limitée') }}</p>
            </div>
          </div>
          <Link href="/promotions/flash-sales" class="shrink-0 text-xs font-bold text-red-500 transition hover:text-red-600 cursor-pointer">{{ t('Voir tout') }}</Link>
        </div>
        <div class="flex gap-3 overflow-x-auto pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <Link
            v-for="product in flashFeed"
            :key="product.id"
            :href="product.href || `/products/${product.slug}`"
            class="w-36 shrink-0"
          >
            <div class="overflow-hidden rounded-xl bg-gray-100 shadow-sm transition hover:shadow-md">
              <img
                v-if="product.media?.[0] || product.image"
                :src="product.media?.[0] || product.image"
                :alt="product.name"
                class="aspect-[0.8] w-full object-cover transition duration-300 hover:scale-105"
                loading="lazy"
              />
              <div v-else class="aspect-[0.8] bg-gradient-to-br from-gray-100 to-gray-200" />
            </div>
            <p class="mt-1.5 text-sm font-black text-slate-900">{{ displayPrice(product) }}</p>
            <p v-if="product.compare_at_price" class="text-xs font-medium text-red-500 line-through">{{ displayCompareAt(product) }}</p>
          </Link>
        </div>
      </section>

      <!-- Featured -->
      <section v-if="featuredProducts.length" class="mt-8 px-4">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <h2 class="text-base font-black text-slate-900">{{ t('En Vedette') }}</h2>
            <p class="text-xs text-slate-400">{{ t('Nos sélections du moment') }}</p>
          </div>
          <Link href="/products" class="shrink-0 text-xs font-bold text-slate-500 transition hover:text-slate-900 cursor-pointer">{{ t('Voir tout') }}</Link>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <ProductCard
            v-for="product in featuredProducts"
            :key="product.id"
            :product="product"
            :currency="currency"
          />
        </div>
      </section>

      <!-- Best Sellers -->
      <section v-if="bestSellerProducts.length" class="mt-8 px-4">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <h2 class="text-base font-black text-slate-900">{{ t('Meilleures Ventes') }}</h2>
            <p class="text-xs text-slate-400">{{ t('Les plus populaires') }}</p>
          </div>
          <Link href="/products?sort=bestsellers" class="shrink-0 text-xs font-bold text-slate-500 transition hover:text-slate-900 cursor-pointer">{{ t('Voir tout') }}</Link>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <ProductCard
            v-for="product in bestSellerProducts"
            :key="product.id"
            :product="product"
            :currency="currency"
          />
        </div>
      </section>

      <!-- Recommended -->
      <section v-if="recommendedProducts.length" class="mt-8 px-4">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <h2 class="text-base font-black text-slate-900">{{ t('Vous Pourriez Aimer') }}</h2>
            <p class="text-xs text-slate-400">{{ t('Recommandé pour vous') }}</p>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <ProductCard
            v-for="product in recommendedProducts"
            :key="product.id"
            :product="product"
            :currency="currency"
          />
        </div>
      </section>
    </div>

    <AppDownloadPopup
      ref="appDownloadPopupRef"
      :settings="appDownloadSettings"
      :hero-image="heroImage"
    />
  </StorefrontLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'
import { useUserPreferences } from '@/composables/useUserPreferences.js'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ProductCard from '@/Components/homepage/ProductCard.vue'
import AppDownloadPopup from '@/Components/homepage/AppDownloadPopup.vue'

const props = defineProps({
  featured: { type: Array, required: true },
  bestSellers: { type: Array, required: true },
  recommended: { type: Array, required: true },
  bestValue: { type: Array, default: () => [] },
  flashDeals: { type: Array, default: () => [] },
  flashDealsViewAllHref: { type: String, default: '/promotions/flash-sales' },
  categoryHighlights: { type: Array, default: () => [] },
  featuredCategorySections: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  featuredCategories: { type: Array, default: () => [] },
  currency: { type: String, default: 'USD' },
  homeContent: { type: Object, default: null },
  banners: { type: Object, default: () => ({}) },
  seasonalDrops: { type: Array, default: () => [] },
  seasonalDropsViewAllHref: { type: String, default: '/products' },
  homeCollections: { type: Array, default: () => [] },
  homeCollectionsViewAllHref: { type: String, default: '/collections' },
  homepagePromotions: { type: Array, default: () => [] },
  popularSearches: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const appDownloadPopupRef = ref(null)
const { currentCurrency, formatCurrency, convertCurrency } = useUserPreferences()
const displayCurrency = computed(() => currentCurrency.value || props.currency)

const currentSlide = ref(0)
const activeCategory = ref(null)
let autoplayTimer = null

const nextSlide = () => {
  currentSlide.value = (currentSlide.value + 1) % heroSlides.value.length
}

const prevSlide = () => {
  currentSlide.value = (currentSlide.value - 1 + heroSlides.value.length) % heroSlides.value.length
}

const pauseAutoPlay = () => {
  if (autoplayTimer) {
    clearInterval(autoplayTimer)
    autoplayTimer = null
  }
}

const resumeAutoPlay = () => {
  pauseAutoPlay()
  if (heroSlides.value.length > 1) {
    autoplayTimer = setInterval(nextSlide, 5000)
  }
}

onMounted(() => resumeAutoPlay())
onBeforeUnmount(() => pauseAutoPlay())

const buildShort = (name) => {
  if (!name) return '?'
  const clean = String(name)
  const initials = clean.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase()
  return initials || clean.slice(0, 2).toUpperCase()
}

const dedupeProducts = (items) => {
  const seen = new Set()
  return items.filter(item => {
    if (!item?.id || seen.has(item.id)) return false
    seen.add(item.id)
    return true
  })
}

const displayPrice = (product) => {
  const price = product?.feed_price ?? product?.price ?? 0
  return formatCurrency(price, displayCurrency.value)
}

const displayCompareAt = (product) => {
  const price = product?.compare_at_price ?? 0
  return price ? formatCurrency(price, displayCurrency.value) : ''
}

const heroImage = computed(() => {
  return props.banners?.hero?.[0]?.imagePath ||
    props.banners?.carousel?.[0]?.imagePath ||
    props.homeCollections?.[0]?.image ||
    props.seasonalDrops?.[0]?.image ||
    null
})

const heroSlides = computed(() => {
  const cmsSlides = Array.isArray(props.homeContent?.hero_slides) ? props.homeContent.hero_slides : []
  if (cmsSlides.length) {
    return cmsSlides.map((slide, idx) => ({
      key: slide.id || `slide-${idx}`,
      badge: slide.badge || '',
      title: slide.title || t('Nouveautés'),
      subtitle: slide.subtitle || '',
      image: slide.image || heroImage.value,
      primary: slide.primary || null,
    }))
  }

  const fallbackImg = heroImage.value
  if (fallbackImg) {
    return [{
      key: 'hero-0',
      badge: t('Promo'),
      title: t('Découvrez nos produits'),
      subtitle: '',
      image: fallbackImg,
      primary: { label: t('Voir les produits'), href: '/products' },
    }]
  }

  return [{
    key: 'hero-0',
    badge: '',
    title: t('Bienvenue sur Simbazu'),
    subtitle: '',
    image: null,
    primary: { label: t('Commencer'), href: '/products' },
  }]
})

const scrollCategories = computed(() => {
  const source = (props.featuredCategories?.length ? props.featuredCategories : props.categories).slice(0, 12)
  return source.map(cat => ({
    ...cat,
    name: cat.name,
    image: cat.image || cat.heroImage || cat.hero_image || null,
    short: buildShort(cat.name),
    href: cat.slug ? `/categories/${encodeURIComponent(cat.slug)}` : '/products',
  }))
})

const featuredProducts = computed(() => dedupeProducts(props.featured).slice(0, 8))
const bestSellerProducts = computed(() => dedupeProducts(props.bestSellers).slice(0, 8))
const recommendedProducts = computed(() => dedupeProducts(props.recommended).slice(0, 8))
const flashFeed = computed(() => (Array.isArray(props.flashDeals) ? props.flashDeals : []).slice(0, 8))

const appDownloadSettings = computed(() => {
  const settings = props.homeContent?.app_download
  return {
    enabled: settings?.enabled ?? true,
    badge: settings?.badge || t('App-only deals'),
    title: settings?.title || t('Unlock the full Simbazu app experience'),
    subtitle: settings?.subtitle || t('Get faster checkout, real-time order tracking, and mobile-only drops.'),
    ios_label: settings?.ios_label || t('Download on the App Store'),
    ios_href: settings?.ios_href || '',
    android_label: settings?.android_label || t('Google Play coming soon'),
    android_href: settings?.android_href || '',
  }
})

const openAppDownloadPopup = () => {
  appDownloadPopupRef.value?.open?.()
}
</script>
