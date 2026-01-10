<template>
  <StorefrontLayout>
    <div class="noon-home">
      <!-- Database Banners - Hero -->
      <BannerHero v-if="banners?.hero?.length" :banner="banners.hero[0]" />

      <!-- Database Banners - Carousel -->
      <BannerCarousel v-if="banners?.carousel?.length" :banners="banners.carousel" />

      <section class="top-strip">
        <div v-for="item in topStrip" :key="item.title" class="strip-card">
          <span class="strip-icon">{{ item.icon }}</span>
          <div>
            <p class="strip-title">{{ item.title }}</p>
            <p class="strip-subtitle">{{ item.subtitle }}</p>
          </div>
        </div>
      </section>

      <section class="hero-block">
        <div class="hero-carousel" @mouseenter="hoverPause = true" @mouseleave="hoverPause = false">
          <div class="hero-frame">
            <article
              v-for="(slide, index) in heroSlides"
              :key="slide.title"
              class="hero-slide"
              :class="{ 'is-active': index === activeIndex }"
            >
              <div class="hero-copy">
                <p class="hero-kicker">{{ slide.kicker }}</p>
                <h1 class="hero-title">{{ slide.title }}</h1>
                <p class="hero-subtitle">{{ slide.subtitle }}</p>
                <div class="hero-actions">
                  <Link :href="slide.primary.href" class="hero-primary">
                    {{ slide.primary.label }}
                  </Link>
                  <Link :href="slide.secondary.href" class="hero-secondary">
                    {{ slide.secondary.label }}
                  </Link>
                </div>
                <div v-if="slide.meta?.length" class="hero-meta">
                  <span v-for="item in slide.meta" :key="item">{{ item }}</span>
                </div>
              </div>
              <div class="hero-image">
                <img :src="slide.image" :alt="slide.title" loading="lazy" />
              </div>
            </article>
          </div>
          <div class="hero-controls">
            <button type="button" class="hero-arrow" @click="prevSlide" :aria-label="t('Previous slide')">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <div class="hero-dots">
              <button
                v-for="(slide, index) in heroSlides"
                :key="slide.title + '-dot'"
                type="button"
                class="hero-dot"
                :class="{ 'is-active': index === activeIndex }"
                :aria-label="t('Go to slide :number', { number: index + 1 })"
                @click="goToSlide(index)"
              ></button>
            </div>
            <button type="button" class="hero-arrow" @click="nextSlide" :aria-label="t('Next slide')">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
        </div>

        <div class="hero-rails">
          <div
            v-for="(card, index) in railCards"
            :key="card.title"
            class="rail-card"
            :class="index === 0 ? 'rail-sun' : 'rail-ink'"
          >
            <p class="rail-kicker">{{ card.kicker }}</p>
            <h3>{{ card.title }}</h3>
            <p>{{ card.subtitle }}</p>
            <Link :href="card.href" class="rail-link">{{ card.cta }}</Link>
          </div>
        </div>
      </section>

      <section class="category-rail">
        <div class="section-head">
          <div>
            <p class="section-kicker">{{ t('Top categories') }}</p>
            <h2 class="section-title">{{ t('Browse by category') }}</h2>
          </div>
          <Link href="/products" class="section-link">{{ t('View all') }}</Link>
        </div>
        <div class="rail-track">
          <Link
            v-for="(category, index) in categoryTiles"
            :key="category.name"
            :href="`/products?category=${encodeURIComponent(category.name)}`"
            class="rail-item"
            :style="{ animationDelay: `${index * 60}ms` }"
          >
            <div class="rail-icon">{{ category.short }}</div>
            <div>
              <p class="rail-title">{{ category.name }}</p>
              <p class="rail-count">{{ t(':count products', { count: formatCount(category.count) }) }}</p>
            </div>
          </Link>
        </div>
      </section>

      <section class="section-block">
        <div class="section-head">
          <div>
            <p class="section-kicker">{{ t('Flash deals') }}</p>
            <h2 class="section-title">{{ t('Limited-time drops') }}</h2>
          </div>
          <Link href="/products" class="section-link">{{ t('Shop deals') }}</Link>
        </div>
        <div class="deal-grid">
          <div v-for="(deal, index) in featuredDeals" :key="deal.id" class="deal-card">
            <div class="deal-head">
              <span class="deal-badge">{{ deal.category || 'Simbazu' }}</span>
              <span class="deal-timer">{{ t('Ends in :time', { time: dealTimers[index % dealTimers.length] }) }}</span>
            </div>
            <ProductCard :product="deal" :currency="currency" />
          </div>
        </div>
      </section>

      <section class="section-block">
        <div class="section-head">
          <div>
            <p class="section-kicker">{{ t('Best sellers') }}</p>
            <h2 class="section-title">{{ t('Trending this week') }}</h2>
          </div>
          <Link href="/products" class="section-link">{{ t('Browse all') }}</Link>
        </div>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <ProductCard
            v-for="product in bestSellers"
            :key="product.id"
            :product="product"
            :currency="currency"
            class="reveal"
          />
        </div>
      </section>

      <!-- Database Banners - Strip (after best sellers) -->
      <BannerStrip v-if="banners?.strip" :banner="banners.strip" />

      <section class="section-block">
        <div class="section-head">
          <div>
            <p class="section-kicker">{{ t('Recommended') }}</p>
            <h2 class="section-title">{{ t('Because you shop smart') }}</h2>
          </div>
          <Link href="/products" class="section-link">{{ t('See more') }}</Link>
        </div>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <ProductCard
            v-for="product in recommended"
            :key="product.id"
            :product="product"
            :currency="currency"
            class="reveal"
          />
        </div>
      </section>


      <!-- Homepage Promotions Section -->
      <section v-if="homepagePromotions && homepagePromotions.length" class="section-block">
        <div class="section-head">
          <div>
            <p class="section-kicker">{{ t('Featured Promotions') }}</p>
            <h2 class="section-title">{{ t('Special offers just for you') }}</h2>
          </div>
          <Link href="/promotions" class="section-link">{{ t('See all promotions') }}</Link>
        </div>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="promo in homepagePromotions" :key="promo.id" class="promotion-card reveal">
            <div class="promotion-header">
              <span class="promotion-type" :class="promo.type">{{ promo.type === 'flash_sale' ? t('Flash Sale') : t('Auto Discount') }}</span>
              <span v-if="promo.end_at" class="promotion-timer">{{ t('Ends at') }} {{ new Date(promo.end_at).toLocaleString() }}</span>
            </div>
            <h3 class="promotion-title">{{ promo.name }}</h3>
            <p class="promotion-desc">{{ promo.description }}</p>
            <div class="promotion-meta">
              <span v-if="promo.value_type === 'percent'">{{ promo.value }}% {{ t('off') }}</span>
              <span v-else-if="promo.value_type === 'amount'">-{{ displayPrice(promo.value) }}</span>
            </div>
            <div v-if="promo.targets && promo.targets.length" class="promotion-targets">
              <span v-for="target in promo.targets" :key="target.id" class="promotion-target">
                {{ target.target_type === 'product' ? t('Product') : t('Category') }}: {{ target.target_value }}
              </span>
            </div>
          </div>
        </div>
      </section>

      <section class="banner-strip">
        <div class="banner-fill">
          <div>
            <p class="banner-kicker">{{ bannerStrip.kicker }}</p>
            <h3>{{ bannerStrip.title }}</h3>
          </div>
          <Link :href="bannerStrip.href" class="banner-cta">{{ bannerStrip.cta }}</Link>
        </div>
      </section>
const homepagePromotions = computed(() => Array.isArray(page.props.homepagePromotions) ? page.props.homepagePromotions : [])

      <section class="value-grid">
        <div v-for="item in valueProps" :key="item.title" class="value-card">
          <h3>{{ item.title }}</h3>
          <p>{{ item.body }}</p>
        </div>
      </section>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ProductCard from '@/Components/ProductCard.vue'
import BannerHero from '@/Components/BannerHero.vue'
import BannerCarousel from '@/Components/BannerCarousel.vue'
import BannerStrip from '@/Components/BannerStrip.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  featured: { type: Array, required: true },
  bestSellers: { type: Array, required: true },
  recommended: { type: Array, required: true },
  categoryHighlights: { type: Array, default: () => [] },
  currency: { type: String, default: 'USD' },
  homeContent: { type: Object, default: () => ({}) },
  banners: { type: Object, default: () => ({}) },
})

const page = usePage()
const { t } = useTranslations()

const fallbackSlides = [
  {
    kicker: t('Fresh drop'),
    title: t('Bright home upgrades with clear delivery promises'),
    subtitle: t('Curated essentials, verified suppliers, and customs clarity before you pay.'),
    image: 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=900&q=80',
    primary: { label: t('Shop arrivals'), href: '/products' },
    secondary: { label: t('Track order'), href: '/orders/track' },
    meta: [t('Fast dispatch'), t('Duty clarity'), t('Reliable tracking')],
  },
  {
    kicker: t('Bundle-ready'),
    title: t('Bundle picks for tech, travel, and self care'),
    subtitle: t('High-impact essentials that ship together for easier delivery.'),
    image: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=80',
    primary: { label: t('Browse bundles'), href: '/products' },
    secondary: { label: t('See categories'), href: '/products' },
    meta: [t('Bundle savings'), t('Verified stock'), t('Clear timelines')],
  },
  {
    kicker: t('Smart sourcing'),
    title: t('Everyday heroes delivered with customs clarity'),
    subtitle: t('Shop top-rated essentials without surprises at checkout.'),
    image: 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=900&q=80',
    primary: { label: t('Shop essentials'), href: '/products' },
    secondary: { label: t('Support'), href: '/support' },
    meta: [t('No hidden fees'), t('WhatsApp support'), t('Trusted suppliers')],
  },
]

const activeIndex = ref(0)
const hoverPause = ref(false)
let timer = null

const nextSlide = () => {
  activeIndex.value = (activeIndex.value + 1) % heroSlides.value.length
}

const prevSlide = () => {
  activeIndex.value = (activeIndex.value - 1 + heroSlides.value.length) % heroSlides.value.length
}

const goToSlide = (index) => {
  activeIndex.value = index
}

const startTimer = () => {
  stopTimer()
  timer = window.setInterval(() => {
    if (! hoverPause.value) {
      nextSlide()
    }
  }, 6500)
}

const stopTimer = () => {
  if (timer) {
    window.clearInterval(timer)
    timer = null
  }
}

onMounted(() => {
  startTimer()
})

onBeforeUnmount(() => {
  stopTimer()
})

const fallbackCategories = [
  { name: t('Home and Kitchen'), count: 0 },
  { name: t('Tech and Gadgets'), count: 0 },
  { name: t('Beauty and Care'), count: 0 },
  { name: t('Fashion'), count: 0 },
  { name: t('Baby and Kids'), count: 0 },
  { name: t('Fitness and Outdoor'), count: 0 },
  { name: t('Office and Study'), count: 0 },
  { name: t('Travel and Luggage'), count: 0 },
]

const buildShort = (name) => {
  const initials = name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
  return initials || name.slice(0, 2).toUpperCase()
}

const categoryTiles = computed(() => {
  const source = props.categoryHighlights.length ? props.categoryHighlights : fallbackCategories
  return source.map((category) => ({
    ...category,
    short: buildShort(String(category.name)),
  }))
})

const formatCount = (count) => {
  if (! count) {
    return '20+'
  }
  if (count >= 100) {
    return '100+'
  }
  if (count >= 50) {
    return '50+'
  }
  return String(count)
}

const featuredDeals = computed(() => props.featured.slice(0, 6))
const dealTimers = ['03:18:22', '01:09:45', '05:33:10', '00:42:08', '04:15:19', '02:28:31']

const topStrip = computed(() => {
  if (props.homeContent?.top_strip?.length) {
    return props.homeContent.top_strip
  }
  return [
    { icon: '⚡', title: t('Flash deals daily'), subtitle: t('Short-run offers updated every 24h.') },
    { icon: '✈', title: t('Fast dispatch'), subtitle: t('Suppliers confirm within 24-48 hours.') },
    { icon: '✓', title: t('Customs clarity'), subtitle: t('Duties shown before checkout.') },
  ]
})

const heroSlides = computed(() => {
  const source = props.homeContent?.hero_slides?.length ? props.homeContent.hero_slides : fallbackSlides
  return source.map((slide) => ({
    ...slide,
    primary: slide.primary ?? {
      label: slide.primary_label ?? t('Shop now'),
      href: slide.primary_href ?? '/products',
    },
    secondary: slide.secondary ?? {
      label: slide.secondary_label ?? t('Track order'),
      href: slide.secondary_href ?? '/orders/track',
    },
    meta: Array.isArray(slide.meta)
      ? slide.meta
      : (typeof slide.meta === 'string' ? slide.meta.split(',').map((item) => item.trim()).filter(Boolean) : []),
  }))
})

const railCards = computed(() => {
  if (props.homeContent?.rail_cards?.length) {
    return props.homeContent.rail_cards
  }
  return [
    { kicker: t('Offers'), title: t('Weekend mega picks'), subtitle: t('Bundles, gadgets, and home upgrades with fast dispatch.'), cta: t('Shop offers'), href: '/products' },
    { kicker: t('Collections'), title: t('Smart home revamp'), subtitle: t('Energy-saving essentials curated for everyday comfort.'), cta: t('Browse collection'), href: '/products' },
  ]
})

const bannerStrip = computed(() => {
  if (props.homeContent?.banner_strip) {
    return props.homeContent.banner_strip
  }
  return {
    kicker: t('Simbazu picks'),
    title: t('Upgrade every room with clear delivery timelines'),
    cta: t('Explore home upgrades'),
    href: '/products',
  }
})

const fallbackValueProps = [
  {
    title: t("Delivery built for Cote d'Ivoire"),
    body: t('Standard delivery in 7 to 18 business days with proactive tracking updates.'),
  },
  {
    title: t('Smart sourcing, safer spending'),
    body: t('We verify supplier availability, quality, and customs requirements before checkout.'),
  },
  {
    title: t('Support that responds'),
    body: t('Get answers fast via WhatsApp and email with order-ready agents.'),
  },
]

const valueProps = computed(() => {
  const configured = page.props.storefront?.value_props
  if (Array.isArray(configured) && configured.length) {
    return configured
  }
  return fallbackValueProps
})
</script>

<style scoped>
.noon-home {
  font-family: "Segoe UI", ui-sans-serif, system-ui, -apple-system, "Helvetica Neue", Arial, sans-serif;
  color: #151517;
  --Simbazu-yellow: #ffd428;
  --Simbazu-ink: #111318;
  --Simbazu-slate: #4b5563;
  --Simbazu-cream: #fff7d6;
  --Simbazu-peach: #ffe6e1;
  --Simbazu-ice: #ecf2ff;
  display: flex;
  flex-direction: column;
  gap: 42px;
}

.noon-home h1,
.noon-home h2,
.noon-home h3 {
  font-family: "Segoe UI Semibold", "Segoe UI", ui-sans-serif, system-ui, -apple-system, "Helvetica Neue", Arial, sans-serif;
}

.top-strip {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.strip-card {
  display: flex;
  gap: 12px;
  align-items: center;
  padding: 14px 16px;
  border-radius: 16px;
  background: #fff;
  border: 1px solid #f3f4f6;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05);
}

.strip-icon {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: var(--Simbazu-cream);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.strip-title {
  font-weight: 700;
  font-size: 13px;
}

.strip-subtitle {
  font-size: 12px;
  color: var(--Simbazu-slate);
}

.hero-block {
  display: grid;
  gap: 18px;
}

.hero-carousel {
  border-radius: 26px;
  background: linear-gradient(135deg, #fffdf2, #fff6c5);
  border: 1px solid #f3e39f;
  padding: 22px;
  position: relative;
  overflow: hidden;
}

.hero-frame {
  position: relative;
  min-height: 320px;
}

.hero-slide {
  position: absolute;
  inset: 0;
  display: grid;
  gap: 20px;
  align-items: center;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  opacity: 0;
  transform: translateY(12px);
  transition: opacity 300ms ease, transform 300ms ease;
  pointer-events: none;
}

.hero-slide.is-active {
  opacity: 1;
  transform: translateY(0);
  pointer-events: auto;
}

.hero-copy {
  max-width: 520px;
}

.hero-kicker {
  text-transform: uppercase;
  letter-spacing: 0.3em;
  font-size: 11px;
  font-weight: 700;
  color: #b45309;
}

.hero-title {
  font-size: clamp(2rem, 2.4vw + 1rem, 3.2rem);
  margin-top: 12px;
  line-height: 1.05;
}

.hero-subtitle {
  margin-top: 12px;
  font-size: 15px;
  color: var(--Simbazu-slate);
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 18px;
}

.hero-primary,
.hero-secondary {
  border-radius: 999px;
  font-weight: 600;
  font-size: 13px;
  padding: 10px 18px;
}

.hero-primary {
  background: var(--Simbazu-ink);
  color: #fff;
}

.hero-secondary {
  background: #fff;
  border: 1px solid #d1d5db;
  color: var(--Simbazu-ink);
}

.hero-meta {
  margin-top: 14px;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 12px;
  color: #6b7280;
}

.hero-image {
  display: flex;
  justify-content: center;
}

.hero-image img {
  width: min(420px, 100%);
  border-radius: 20px;
  border: 5px solid #fff;
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.15);
}

.hero-controls {
  margin-top: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 10px 14px;
}

.hero-arrow {
  width: 36px;
  height: 36px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #111827;
}

.hero-dots {
  display: flex;
  gap: 10px;
  padding: 0 10px;
  flex-wrap: wrap;
  justify-content: center;
}

.hero-dot {
  width: 12px;
  height: 12px;
  border-radius: 999px;
  background: #d1d5db;
  border: 1px solid #cbd5e1;
  transition: all 180ms ease;
}

.hero-dot.is-active {
  background: var(--Simbazu-ink);
  width: 26px;
  border-color: var(--Simbazu-ink);
}

.rail-track {
  display: grid;
  gap: 12px;
  grid-auto-flow: column;
  grid-auto-columns: minmax(220px, 1fr);
  overflow-x: auto;
  padding-bottom: 6px;
}

.deal-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.hero-rails {
  display: grid;
  gap: 16px;
}

.rail-card {
  border-radius: 22px;
  padding: 18px;
  border: 1px solid transparent;
}

.rail-sun {
  background: linear-gradient(135deg, #fff4dc, #ffe2f0);
  border-color: #f8d3af;
}

.rail-ink {
  background: linear-gradient(135deg, #eef2ff, #f5f7ff);
  border-color: #d7ddff;
}

.rail-kicker {
  text-transform: uppercase;
  letter-spacing: 0.24em;
  font-size: 11px;
  color: #6b7280;
  font-weight: 700;
}

.rail-card h3 {
  margin-top: 8px;
  font-size: 19px;
}

.rail-card p {
  margin-top: 8px;
  color: #4b5563;
  font-size: 13px;
}

.rail-link {
  display: inline-flex;
  margin-top: 12px;
  font-weight: 600;
  font-size: 13px;
  color: #111827;
}

.category-rail {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
}

.section-kicker {
  font-size: 11px;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  font-weight: 700;
  color: #6b7280;
}

.section-title {
  font-size: clamp(1.4rem, 1.6vw + 1rem, 2rem);
  margin-top: 6px;
}

.section-link {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.rail-track {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.rail-item {
  display: flex;
  gap: 12px;
  align-items: center;
  padding: 14px 16px;
  border-radius: 16px;
  background: #fff;
  border: 1px solid #eef2f7;
  transition: transform 200ms ease, box-shadow 200ms ease;
}

.rail-item:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
}

.rail-icon {
  width: 40px;
  height: 40px;
  border-radius: 14px;
  background: var(--Simbazu-cream);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 13px;
  color: #9a3412;
}

.rail-title {
  font-weight: 600;
  font-size: 13px;
}

.rail-count {
  font-size: 12px;
  color: #6b7280;
}

.section-block {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.deal-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  align-items: stretch;
  align-content: start;
}

.deal-card {
  background: #fff;
  border-radius: 18px;
  padding: 12px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 10px 26px rgba(15, 23, 42, 0.04);
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-height: 0;
  overflow: hidden;
  position: relative;
}

.deal-card .card {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: stretch;
  min-height: 0;
}

.deal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.deal-badge {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  background: var(--Simbazu-cream);
  color: #9a3412;
  padding: 4px 8px;
  border-radius: 999px;
  font-weight: 700;
}

.deal-timer {
  font-size: 11px;
  color: #6b7280;
}

.banner-strip {
  background: #111827;
  color: #fff;
  border-radius: 24px;
  padding: 24px;
}

.banner-fill {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.banner-kicker {
  text-transform: uppercase;
  letter-spacing: 0.24em;
  font-size: 11px;
  color: #fcd34d;
}

.banner-cta {
  background: var(--Simbazu-yellow);
  color: #111827;
  font-weight: 700;
  border-radius: 999px;
  padding: 10px 18px;
  font-size: 13px;
}

.value-grid {
  display: grid;
  gap: 16px;
}

.value-card {
  border-radius: 20px;
  padding: 20px;
  background: #111827;
  color: #f8fafc;
}

.value-card p {
  margin-top: 8px;
  color: #cbd5f5;
  font-size: 14px;
}

.reveal {
  animation: fadeUp 600ms ease both;
}

@keyframes fadeUp {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (min-width: 900px) {
  .hero-block {
    grid-template-columns: minmax(0, 1.35fr) minmax(0, 0.65fr);
  }

  .value-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (prefers-reduced-motion: reduce) {
  .hero-slide,
  .reveal {
    animation: none;
    transition: none;
  }
}

@media (max-width: 700px) {
  .hero-title {
    font-size: clamp(1.8rem, 4vw + 1rem, 2.5rem);
  }

  .hero-image img {
    width: 100%;
    max-width: 360px;
  }

  .hero-controls {
    gap: 8px;
  }

  .hero-dot {
    width: 10px;
    height: 10px;
  }
}
</style>
