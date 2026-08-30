<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'
import { useSeasonalHomepage } from '@/composables/useSeasonalHomepage.js'
import { useRecentlyViewed } from '@/composables/useRecentlyViewed.js'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import AppDownloadPopup from '@/Components/homepage/AppDownloadPopup.vue'
import HomeChoiceGrid from '@/Components/homepage/HomeChoiceGrid.vue'
import HomeCollectionsRail from '@/Components/homepage/HomeCollectionsRail.vue'
import HomeDealRail from '@/Components/homepage/HomeDealRail.vue'
import HomeFeatureBand from '@/Components/homepage/HomeFeatureBand.vue'
import HomeProductSection from '@/Components/homepage/HomeProductSection.vue'
import HomeSeasonalHero from '@/Components/homepage/HomeSeasonalHero.vue'
import HomeSectionHeader from '@/Components/homepage/HomeSectionHeader.vue'
import HomeTrustAndSearch from '@/Components/homepage/HomeTrustAndSearch.vue'
import CompactProductCard from '@/Components/homepage/CompactProductCard.vue'
import ProductQuickAddSheet from '@/Components/ProductQuickAddSheet.vue'

const props = defineProps({
  featured: { type: Array, required: true },
  bestSellers: { type: Array, required: true },
  recommended: { type: Array, required: true },
  bestValue: { type: Array, default: () => [] },
  newArrivals: { type: Array, default: () => [] },
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
  trending: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const { activeSeason } = useSeasonalHomepage()
const { recentlyViewed, clearRecentlyViewed } = useRecentlyViewed()

const appDownloadPopupRef = ref(null)
const countdown = ref('')
const newsletterEmail = ref('')
const newsletterSubmitted = ref(false)
const quickAddProduct = ref(null)
const quickAddSheetOpen = ref(false)

let countdownTimer = null

const openQuickAdd = (product) => {
  quickAddProduct.value = product
  quickAddSheetOpen.value = true
}

const closeQuickAdd = () => {
  quickAddSheetOpen.value = false
}

const submitNewsletter = async () => {
  if (!newsletterEmail.value) return

  try {
    const response = await fetch('/newsletter/subscribe', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({ email: newsletterEmail.value, source: 'homepage_inline' }),
    })

    if (response.ok) {
      newsletterSubmitted.value = true
      newsletterEmail.value = ''
    }
  } catch {
    // Newsletter should never block homepage browsing.
  }
}

const tickCountdown = () => {
  const now = new Date()
  const end = new Date(now)
  end.setHours(23, 59, 59, 999)
  const diff = end.getTime() - now.getTime()

  if (diff <= 0) {
    countdown.value = ''
    return
  }

  const h = Math.floor(diff / 3600000)
  const m = Math.floor((diff % 3600000) / 60000)
  const s = Math.floor((diff % 60000) / 1000)
  countdown.value = `${t('Ends in')} ${h}h ${m}m ${s}s`
}

onMounted(() => {
  if (flashFeed.value.length) {
    tickCountdown()
    countdownTimer = setInterval(tickCountdown, 1000)
  }
})

onBeforeUnmount(() => {
  if (countdownTimer) clearInterval(countdownTimer)
})

const buildShort = (name) => {
  if (!name) return '?'

  const clean = String(name)
  const initials = clean
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase()

  return initials || clean.slice(0, 2).toUpperCase()
}

const dedupeProducts = (items) => {
  const seen = new Set()

  return (Array.isArray(items) ? items : []).filter((item) => {
    if (!item?.id || seen.has(item.id)) return false
    seen.add(item.id)
    return true
  })
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
    return cmsSlides.map((slide, index) => ({
      key: slide.id || `slide-${index}`,
      badge: slide.badge || activeSeason.value.badge,
      title: slide.title || activeSeason.value.title,
      subtitle: slide.subtitle || activeSeason.value.subtitle,
      image: slide.image || heroImage.value,
      primary: slide.primary || { label: activeSeason.value.primaryLabel, href: activeSeason.value.href },
    }))
  }

  return [{
    key: 'seasonal-hero',
    badge: activeSeason.value.badge,
    title: activeSeason.value.title,
    subtitle: activeSeason.value.subtitle,
    image: heroImage.value,
    primary: { label: activeSeason.value.primaryLabel, href: activeSeason.value.href },
  }]
})

const scrollCategories = computed(() => {
  const source = (props.featuredCategories?.length ? props.featuredCategories : props.categories).slice(0, 14)

  return source.map((category) => ({
    ...category,
    name: category.name,
    image: category.image || category.heroImage || category.hero_image || null,
    short: buildShort(category.name),
    href: category.slug ? `/categories/${encodeURIComponent(category.slug)}` : '/products',
    meta: category.meta || t('Popular'),
  }))
})

const normalizedCollections = computed(() => (
  Array.isArray(props.homeCollections)
    ? props.homeCollections.map((collection) => ({
      ...collection,
      title: collection.title || collection.name,
      name: collection.name || collection.title,
      href: collection.href || (collection.slug ? `/collections/${collection.slug}` : props.homeCollectionsViewAllHref),
    }))
    : []
))

const homepageCollections = computed(() => {
  if (normalizedCollections.value.length) {
    return normalizedCollections.value
  }

  return scrollCategories.value.slice(0, 12).map((category) => ({
    id: category.id,
    title: category.name,
    name: category.name,
    image: category.image,
    href: category.href,
    kicker: t('Collection'),
  }))
})

const shoppingLanes = computed(() => {
  const core = [
    { key: 'just-for-you', label: 'Just for You', href: '/quick-shop/just-for-you' },
    { key: 'new-in', label: 'New In', href: '/quick-shop/new-in' },
    { key: 'sale', label: 'Sale', href: '/quick-shop/sale', tone: 'sale' },
    { key: 'best-sellers', label: 'Best Sellers', href: '/quick-shop/best-sellers' },
  ]

  const categoryLanes = scrollCategories.value.slice(0, 12).map((category) => ({
    key: `category-${category.id || category.slug || category.name}`,
    label: category.name,
    href: category.slug ? `/quick-shop/category/${encodeURIComponent(category.slug)}` : category.href,
  }))

  return [...core, ...categoryLanes]
})

const flashFeed = computed(() => dedupeProducts(props.flashDeals).slice(0, 12))
const featuredProducts = computed(() => dedupeProducts(props.featured).slice(0, 12))
const bestSellerProducts = computed(() => dedupeProducts(props.bestSellers).slice(0, 12))
const recommendedProducts = computed(() => dedupeProducts(props.recommended).slice(0, 12))
const trendyProducts = computed(() => dedupeProducts(props.trending).slice(0, 12))
const bestValueProducts = computed(() => dedupeProducts(props.bestValue).slice(0, 12))
const newArrivalsProducts = computed(() => dedupeProducts(props.newArrivals).slice(0, 12))
const seasonalProducts = computed(() => dedupeProducts(props.seasonalDrops).slice(0, 12))

const prioritySections = computed(() => [
  {
    key: 'seasonal',
    eyebrow: t('Seasonal edit'),
    title: t(activeSeason.value.badge),
    subtitle: t('Products and categories that match what customers need right now.'),
    href: props.seasonalDropsViewAllHref,
    actionLabel: t('Shop seasonal'),
    icon: 'new',
    products: seasonalProducts.value,
  },
  {
    key: 'value',
    eyebrow: t('Smart picks'),
    title: t('Best Value'),
    subtitle: t('Useful finds with sharp prices and clear checkout.'),
    href: '/products?sort=price-asc',
    actionLabel: t('More value picks'),
    icon: 'value',
    products: bestValueProducts.value,
  },
  {
    key: 'new',
    eyebrow: t('Fresh drops'),
    title: t('New Arrivals'),
    subtitle: t('Recently added products for customers who want first look.'),
    href: '/products?sort=newest',
    actionLabel: t('More new arrivals'),
    icon: 'new',
    products: newArrivalsProducts.value,
  },
])

const discoverySections = computed(() => [
  {
    key: 'bestsellers',
    eyebrow: t('Customer favorites'),
    title: t('Best Sellers'),
    subtitle: t('Popular products people keep coming back for.'),
    href: '/products?sort=bestsellers',
    actionLabel: t('All best sellers'),
    icon: 'star',
    products: bestSellerProducts.value,
  },
  {
    key: 'trending',
    eyebrow: t('In demand'),
    title: t('Trending Now'),
    subtitle: t('Products getting attention this week.'),
    href: '/products?sort=trending',
    actionLabel: t('See trending'),
    icon: 'spark',
    products: trendyProducts.value,
  },
  {
    key: 'featured',
    eyebrow: t('Editor picks'),
    title: t('Featured'),
    subtitle: t('A focused edit for customers who want a quick decision.'),
    href: '/products?sort=featured',
    actionLabel: t('All featured'),
    icon: 'spark',
    products: featuredProducts.value,
  },
])

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
</script>

<template>
  <StorefrontLayout>
    <main class="min-h-screen bg-[#f7f4ef] pb-28">
      <div class="mx-auto max-w-7xl space-y-5 px-4 py-5 sm:py-6">
        <HomeSeasonalHero
          :season="activeSeason"
          :slides="heroSlides"
          :quick-links="scrollCategories"
          :collections="homepageCollections"
        />

        <HomeCollectionsRail
          :collections="homepageCollections"
          :view-all-href="homeCollectionsViewAllHref"
        />

        <HomeChoiceGrid
          :categories="scrollCategories"
          :season="activeSeason"
        />

        <HomeTrustAndSearch :popular-searches="popularSearches" />

        <HomeDealRail
          v-if="flashFeed.length"
          :deals="flashFeed"
          :countdown="countdown"
          :view-all-href="flashDealsViewAllHref"
          :currency="currency"
          @quick-add="openQuickAdd"
        />

        <template v-for="section in prioritySections" :key="section.key">
          <HomeProductSection
            v-if="section.products.length"
            :eyebrow="section.eyebrow"
            :title="section.title"
            :subtitle="section.subtitle"
            :href="section.href"
            :action-label="section.actionLabel"
            :icon="section.icon"
            :products="section.products"
            :currency="currency"
            @quick-add="openQuickAdd"
          />
        </template>

        <template v-for="section in discoverySections" :key="section.key">
          <HomeProductSection
            v-if="section.products.length"
            :eyebrow="section.eyebrow"
            :title="section.title"
            :subtitle="section.subtitle"
            :href="section.href"
            :action-label="section.actionLabel"
            :icon="section.icon"
            :products="section.products"
            :currency="currency"
            @quick-add="openQuickAdd"
          />
        </template>

        <template v-for="section in featuredCategorySections" :key="section.id">
          <HomeProductSection
            v-if="section.products?.length"
            :title="section.name"
            :subtitle="t('More products from this collection.')"
            :href="section.viewAllHref"
            :action-label="t('Open collection')"
            icon="spark"
            :products="section.products"
            :currency="currency"
            @quick-add="openQuickAdd"
          />
        </template>

        <section v-if="recentlyViewed.length">
          <div class="mb-3 flex items-end justify-between gap-4">
            <HomeSectionHeader
              :title="t('Recently Viewed')"
              :subtitle="t('Pick up where you left off.')"
              icon="spark"
              class="mb-0"
            />
            <button
              type="button"
              class="shrink-0 rounded-md px-3 py-2 text-xs font-bold text-slate-500 transition hover:bg-white hover:text-slate-900"
              @click="clearRecentlyViewed"
            >
              {{ t('Clear') }}
            </button>
          </div>
          <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
            <CompactProductCard
              v-for="product in recentlyViewed"
              :key="product.id"
              :product="product"
              :currency="currency"
              @quick-add="openQuickAdd"
            />
          </div>
        </section>

        <HomeProductSection
          v-if="recommendedProducts.length"
          :eyebrow="t('For you')"
          :title="t('You May Also Like')"
          :subtitle="t('A few more products that fit the browse.')"
          icon="star"
          :products="recommendedProducts"
          :currency="currency"
          @quick-add="openQuickAdd"
        />

        <HomeFeatureBand
          v-model:newsletter-email="newsletterEmail"
          :newsletter-submitted="newsletterSubmitted"
          @submit-newsletter="submitNewsletter"
        />
      </div>
    </main>

    <ProductQuickAddSheet
      :is-open="quickAddSheetOpen"
      :product="quickAddProduct ?? {}"
      :currency="currency"
      @close="closeQuickAdd"
    />

    <AppDownloadPopup
      ref="appDownloadPopupRef"
      :settings="appDownloadSettings"
      :hero-image="heroImage"
    />
  </StorefrontLayout>
</template>
