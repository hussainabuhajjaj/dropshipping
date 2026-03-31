<template>
    <StorefrontLayout>
        <div class="noon-home">

            <!-- Homepage Guide for Promotions/Discounts -->
            <section v-if="logisticsSupportPromo" class="promo-guide mb-6">
                <div class="promo-guide-content">
                    <span class="promo-guide-badge">{{ logisticsSupportPromo.badge_text || t('Logistics Support') }}</span>
                    <span class="promo-guide-text">
            {{ logisticsSupportPromo.name || t('Logistics support applied at checkout for qualifying orders.') }}
          </span>
                    <Link href="/promotions" class="promo-guide-link">{{ t('See all promotions') }}</Link>
                </div>
            </section>

            <!-- Database Banners - Hero (highlight promotion if available) -->
            <BannerHero
                v-if="banners?.hero?.length"
                :banner="banners.hero[0]"
            />

            <BannerCarousel
                v-if="banners?.carousel?.length"
                :banners="banners.carousel"
            />

            <section v-if="topStrip.length" class="top-strip">
                <div v-for="item in topStrip" :key="item.title" class="strip-card">
                    <span class="strip-icon">{{ item.icon }}</span>
                    <div>
                        <p class="strip-title">{{ item.title }}</p>
                        <p class="strip-subtitle">{{ item.subtitle }}</p>
                    </div>
                </div>
            </section>

            <section v-if="heroSlides.length || railCards.length" class="hero-block">
                <div v-if="heroSlides.length" class="hero-carousel" @mouseenter="hoverPause = true" @mouseleave="hoverPause = false">
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

<!--                <div v-if="railCards.length" class="hero-rails">-->
<!--                    <div-->
<!--                        v-for="(card, index) in railCards"-->
<!--                        :key="card.title"-->
<!--                        class="rail-card"-->
<!--                        :class="index === 0 ? 'rail-sun' : 'rail-ink'"-->
<!--                    >-->
<!--                        <p class="rail-kicker">{{ card.kicker }}</p>-->
<!--                        <h3>{{ card.title }}</h3>-->
<!--                        <p>{{ card.subtitle }}</p>-->
<!--                        <Link :href="card.href" class="rail-link">{{ card.cta }}</Link>-->
<!--                    </div>-->
<!--                </div>-->
            </section>

            <section v-if="promoBanner" class="promo-banner">
                <div class="promo-banner-inner" :style="{ background: promoBanner.background || '#fff' }">
                    <div class="promo-copy">
                        <p class="promo-kicker">{{ promoBanner.kicker || t('Featured') }}</p>
                        <h2>{{ promoBanner.title }}</h2>
                        <p class="promo-subtitle">{{ promoBanner.subtitle }}</p>
                        <div class="promo-actions" v-if="promoBanner.cta">
                            <Link :href="promoBanner.cta.href || '#'" class="promo-cta">{{ promoBanner.cta.label || t('Learn more') }}</Link>
                        </div>
                    </div>
                    <div v-if="promoBanner.images?.length" class="promo-strip">
                        <img v-for="(img, idx) in promoBanner.images" :key="`promo-img-${idx}`" :src="img" :alt="promoBanner.title" loading="lazy" />
                    </div>
                </div>
            </section>

            <section v-if="reasonsBlocks.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('More reasons to shop') }}</p>
                        <h2 class="section-title">{{ t('Why customers choose us') }}</h2>
                    </div>
                </div>
                <div class="reasons-grid">
                    <article
                        v-for="reason in reasonsBlocks"
                        :key="`reason-${reason.title}`"
                        class="reason-card"
                    >
                        <div class="reason-icon" v-if="reason.icon">{{ reason.icon }}</div>
                        <img v-else-if="reason.image" :src="reason.image" :alt="reason.title" loading="lazy" />
                        <p class="reason-title">{{ reason.title }}</p>
                        <p class="reason-copy">{{ reason.subtitle }}</p>
                    </article>
                </div>
            </section>


            <section v-if="featuredCategories.length" class="section-block category-stack">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">{{ t('Curated picks across the store') }}</h2>
                    </div>
                </div>
                <CategoryProductRail
                    v-for="category in featuredCategories"
                    :key="`category-rail-${category.slug || category.id || category.name}`"
                    :category="category"
                    :currency="currency"
                    :promotions="homepagePromotions"
                />
            </section>

            <section v-if="seasonalDrops.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('Collections') }}</p>
                        <h2 class="section-title">{{ t('Curated collections for you') }}</h2>
                    </div>
                    <Link :href="seasonalDropsViewAllHref" class="section-link">{{ t('View all') }}</Link>
                </div>
                <div class="focus-grid">
                    <Link
                        v-for="item in seasonalDrops"
                        :key="`seasonal-drop-${item.id || item.href || item.title}`"
                        :href="item.href || seasonalDropsViewAllHref"
                        class="focus-card"
                    >
                        <div v-if="item.image" class="focus-media">
                            <img :src="item.image" :alt="item.title || t('Collection')" loading="lazy" />
                        </div>
                        <div class="focus-body">
                            <p class="focus-kicker">{{ item.kicker || item.tag || t('Collection') }}</p>
                            <h3>{{ item.title || t('Collection') }}</h3>
                            <p class="focus-subtitle">{{ item.subtitle || '' }}</p>
                        </div>
                    </Link>
                </div>
            </section>

            <section v-if="savingsBlocks.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('Maximize your savings') }}</p>
                        <h2 class="section-title">{{ t('Stackable deals and perks') }}</h2>
                    </div>
                </div>
                <div class="savings-grid">
                    <Link
                        v-for="block in savingsBlocks"
                        :key="`savings-${block.id || block.title}`"
                        :href="block.href || '/products'"
                        class="savings-card"
                    >
                        <p class="savings-kicker">{{ block.kicker || t('Deal') }}</p>
                        <h3 class="savings-title">{{ block.title }}</h3>
                        <p class="savings-subtitle">{{ block.subtitle }}</p>
                        <span class="savings-tag">{{ block.tagline || t('Save more') }}</span>
                    </Link>
                </div>
            </section>

            <section v-if="categoryIcons.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('Explore more') }}</p>
                        <h2 class="section-title">{{ t('Top picks by category') }}</h2>
                    </div>
                </div>
                <div class="icon-grid">
                    <Link
                        v-for="icon in categoryIcons"
                        :key="`icon-${icon.slug}`"
                        :href="icon.href"
                        class="icon-tile"
                    >
                        <div class="icon-avatar">{{ icon.badge }}</div>
                        <p class="icon-label">{{ icon.label }}</p>
                    </Link>
                </div>
            </section>

            <section v-if="flashDeals?.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('Deals') }}</p>
                        <h2 class="section-title">{{ t('Hot deals today') }}</h2>
                    </div>
                    <Link href="/products?sort=price_desc" class="section-link">{{ t('View all') }}</Link>
                </div>
                <div class="rail-track overflow-x-auto pb-2">
                    <ProductCard
                        v-for="deal in flashDeals"
                        :key="`deal-${deal.id}`"
                        :product="deal"
                        :currency="currency"
                        :promotions="homepagePromotions"
                        class="rail-card"
                    />
                </div>
            </section>

            <section v-if="featuredDeals.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('Maximize your savings') }}</p>
                        <h2 class="section-title">{{ t('Stackable deals and fast delivery') }}</h2>
                    </div>
                    <Link :href="flashDealsViewAllHref" class="section-link">{{ t('Shop all deals') }}</Link>
                </div>
                <div class="deal-rail">
                    <ProductCard
                        v-for="deal in featuredDeals"
                        :key="`featured-deal-${deal.id}`"
                        :product="deal"
                        :currency="currency"
                        :promotions="homepagePromotions"
                        class="deal-card-rail"
                    />
                </div>
            </section>

            <section v-if="sectionBlocks.length" class="space-y-10">
                <div
                    v-for="block in sectionBlocks"
                    :key="block.key"
                    class="section-block"
                >
                    <div class="section-head">
                        <h2 class="section-title">{{ block.title }}</h2>
                        <Link href="/products" class="section-link">{{ t('View all') }}</Link>
                    </div>
                    <div class="rail-track overflow-x-auto pb-2">
                        <ProductCard
                            v-for="item in block.items"
                            :key="`${block.key}-${item.id}`"
                            :product="item"
                            :currency="currency"
                            :promotions="homepagePromotions"
                            class="rail-card"
                        />
                    </div>
                </div>
            </section>

            <section v-if="categoryTiles.length" class="category-rail">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('Top categories') }}</p>
                        <h2 class="section-title">{{ t('Browse by category') }}</h2>
                    </div>
                    <Link href="/promotions/categories" class="section-link">{{ t('View all') }}</Link>
                </div>
                <div class="rail-track">
                    <Link
                        v-for="(category, index) in categoryTiles"
                        :key="category.name"
                        :href="`/products?category=${encodeURIComponent(category.slug || category.name)}`"
                        class="rail-item"
                        :style="{ animationDelay: `${index * 60}ms` }"
                    >
                        <div class="rail-icon">{{ category.short }}</div>
                        <div>
                            <p class="rail-title">{{ category.name }}
                                <span v-if="categoryHasPromotion(category, homepagePromotions)" class="category-promo-badge">{{ t('Promo!') }}</span>
                            </p>
                            <p class="rail-count">{{ t(':count products', { count: formatCount(category.count) }) }}</p>
                        </div>
                    </Link>
                </div>
            </section>

            <section v-if="featuredCategorySections.length" class="featured-category-sections section-block">
                <div
                    v-for="section in featuredCategorySections"
                    :key="`featured-category-${section.id}`"
                    class="featured-category-block"
                >
                    <div class="section-head">
                        <h2 class="section-title featured-category-title">{{ section.name }}</h2>
                        <Link :href="section.viewAllHref" class="section-link">{{ t('View all') }}</Link>
                    </div>

                    <div class="featured-category-slider">
                        <ProductCard
                            v-for="product in section.products"
                            :key="`featured-category-product-${section.id}-${product.id}`"
                            :product="product"
                            :currency="currency"
                            :promotions="homepagePromotions"
                            dense
                            class="featured-category-product"
                        />
                    </div>
                </div>
            </section>

            <section v-if="focusBlocks.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('In focus') }}</p>
                        <h2 class="section-title">{{ t('Curated stories and trends') }}</h2>
                    </div>
                </div>
                <div class="focus-grid">
                    <Link
                        v-for="block in focusBlocks"
                        :key="`focus-${block.id || block.title}`"
                        :href="block.href || '/products'"
                        class="focus-card"
                    >
                        <div v-if="block.image" class="focus-media">
                            <img :src="block.image" :alt="block.title" loading="lazy" />
                        </div>
                        <div class="focus-body">
                            <p class="focus-kicker">{{ block.kicker || t('Spotlight') }}</p>
                            <h3>{{ block.title }}</h3>
                            <p class="focus-subtitle">{{ block.subtitle }}</p>
                        </div>
                    </Link>
                </div>
            </section>

            <section v-if="featuredDeals.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('Flash deals') }}</p>
                        <h2 class="section-title">{{ t('Limited-time drops') }}</h2>
                    </div>
                    <Link :href="flashDealsViewAllHref" class="section-link">{{ t('Shop deals') }}</Link>
                </div>
                <div class="deal-grid">
                    <div v-for="(deal, index) in featuredDeals" :key="deal.id" class="deal-card">
                        <div class="deal-head">
                            <span class="deal-badge">{{ deal.category || 'Simbazu' }}</span>
                            <span v-if="dealCountdown(deal)" class="deal-timer">
              {{ t('Ends in :time', { time: dealCountdown(deal) }) }}
            </span>
                        </div>
                        <ProductCard :product="deal" :currency="currency" :promotions="homepagePromotions" dense />
                    </div>
                </div>
            </section>

            <section v-if="bestSellers.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('Best sellers') }}</p>
                        <h2 class="section-title">{{ t('Trending this week') }}</h2>
                    </div>
                    <Link href="/products" class="section-link">{{ t('Browse all') }}</Link>
                </div>
                <div class="grid gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                        <ProductCard
                            v-for="product in bestSellers"
                            :key="product.id"
                            :product="product"
                            :currency="currency"
                            :promotions="homepagePromotions"
                            dense
                            class="reveal"
                        />
                </div>
            </section>

            <!-- Database Banners - Strip (after best sellers) -->
            <BannerStrip v-if="banners?.strip" :banner="banners.strip" />

            <section v-if="recommended.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('Recommended') }}</p>
                        <h2 class="section-title">{{ t('Because you shop smart') }}</h2>
                    </div>
                    <Link href="/products" class="section-link">{{ t('See more') }}</Link>
                </div>
                <div class="grid gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                        <ProductCard
                            v-for="product in recommended"
                            :key="product.id"
                            :product="product"
                            :currency="currency"
                            :promotions="homepagePromotions"
                            dense
                            class="reveal"
                        />
                </div>
            </section>

            <section v-if="bestValue.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('Best value') }}</p>
                        <h2 class="section-title">{{ t('Top rated for the price') }}</h2>
                    </div>
                    <Link href="/products?sort=rating" class="section-link">{{ t('View all') }}</Link>
                </div>
                <div class="grid gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                        <ProductCard
                            v-for="product in bestValue"
                            :key="product.id"
                            :product="product"
                            :currency="currency"
                            :promotions="homepagePromotions"
                            dense
                            class="reveal"
                        />
                </div>
            </section>


            <!-- Homepage Promotions Section -->
            <section v-if="featuredPromotions.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('Featured Promotions') }}</p>
                        <h2 class="section-title">{{ t('Special offers just for you') }}</h2>
                    </div>
                    <Link href="/promotions" class="section-link">{{ t('See all promotions') }}</Link>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-2">
                    <div v-for="promo in featuredPromotions" :key="promo.id" class="promotion-card reveal">
                        <div class="promotion-header">
                            <span class="promotion-badge">{{ promo.badge_text || (promo.type === 'flash_sale' ? t('Flash Sale') : t('Auto Discount')) }}</span>
                            <span v-if="promoCountdown(promo)" class="promotion-timer">{{ t('Ends in :time', { time: promoCountdown(promo) }) }}</span>
                        </div>
                        <h3 class="promotion-title">{{ promo.name }}</h3>
                        <p v-if="promo.description" class="promotion-desc">{{ promo.description }}</p>
                        <div class="promotion-meta">
                            <span class="promotion-value">{{ promoValue(promo) }}</span>
                            <span class="promotion-scope">{{ promoScope(promo) }}</span>
                        </div>
                        <p v-if="promo.apply_hint" class="promotion-hint">{{ promo.apply_hint }}</p>
                        <Link :href="promoCta(promo).href" class="promotion-cta">
                            {{ promoCta(promo).label }}
                        </Link>
                    </div>
                </div>
            </section>

            <section v-if="bannerStrip" class="banner-strip">
                <div class="banner-fill">
                    <div>
                        <p class="banner-kicker">{{ bannerStrip.kicker }}</p>
                        <h3>{{ bannerStrip.title }}</h3>
                    </div>
                    <Link :href="bannerStrip.href" class="banner-cta">{{ bannerStrip.cta }}</Link>
                </div>
            </section>

            <section v-if="popularSearches.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('Popular Searches') }}</p>
                        <h2 class="section-title">{{ t('Trending lookups') }}</h2>
                    </div>
                </div>
                <div class="chip-cloud">
                    <Link
                        v-for="term in popularSearches"
                        :key="`search-${term}`"
                        :href="`/search?q=${encodeURIComponent(term)}`"
                        class="chip-pill"
                    >
                        {{ term }}
                    </Link>
                </div>
            </section>

            <section v-if="contentSections.length" class="section-block">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">{{ t('Learn more') }}</p>
                        <h2 class="section-title">{{ t('Shopping guides and FAQs') }}</h2>
                    </div>
                </div>
                <div class="content-stack">
                    <article
                        v-for="section in contentSections"
                        :key="`content-${section.id || section.title}`"
                        class="content-section"
                    >
                        <h3 class="content-title">{{ section.title }}</h3>
                        <p class="content-body">{{ section.body }}</p>
                        <Link v-if="section.href" :href="section.href" class="content-link">{{ t('Read more') }}</Link>
                    </article>
                </div>
            </section>

            <section v-if="supportContacts.length" class="support-strip">
                <div class="support-title">{{ t("We're Always Here To Help") }}</div>
                <div class="support-items">
                    <div
                        v-for="contact in supportContacts"
                        :key="contact.label"
                        class="support-item"
                    >
                        <p class="support-label">{{ contact.label }}</p>
                        <p class="support-value">
                            <Link :href="contact.href || '#'" class="support-link">
                                {{ contact.value }}
                            </Link>
                        </p>
                    </div>
                </div>
            </section>

            <section v-if="footerColumns.length" class="footer-grid">
                <div
                    v-for="column in footerColumns"
                    :key="column.title"
                    class="footer-column"
                >
                    <p class="footer-heading">{{ column.title }}</p>
                    <Link
                        v-for="link in column.links || []"
                        :key="link.label"
                        :href="link.href"
                        class="footer-link"
                    >
                        {{ link.label }}
                    </Link>
                </div>
            </section>

            <section v-if="downloadBadges.length || paymentBadges.length" class="footer-meta">
                <div v-if="downloadBadges.length" class="download-row">
                    <p class="footer-heading">{{ t('Shop on the go') }}</p>
                    <div class="badge-row">
                        <Link v-for="badge in downloadBadges" :key="badge.href" :href="badge.href" class="badge">
                            {{ badge.label }}
                        </Link>
                    </div>
                </div>
                <div v-if="paymentBadges.length" class="payment-row">
                    <p class="footer-heading">{{ t('Payment methods') }}</p>
                    <div class="badge-row">
                        <span v-for="badge in paymentBadges" :key="badge" class="badge">{{ badge }}</span>
                    </div>
                </div>
            </section>

            <section v-if="valueProps.length" class="value-grid">
                <div v-for="item in valueProps" :key="item.title" class="value-card">
                    <h3>{{ item.title }}</h3>
                    <p>{{ item.body }}</p>
                </div>
            </section>
        </div>
    </StorefrontLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ProductCard from '@/Components/ProductCard.vue'
import BannerHero from '@/Components/BannerHero.vue'
import BannerCarousel from '@/Components/BannerCarousel.vue'
import BannerStrip from '@/Components/BannerStrip.vue'
import TrustBadges from '@/Components/TrustBadges.vue'
import DeliveryTimeline from '@/Components/DeliveryTimeline.vue'
import CategoryProductRail from '@/Components/CategoryProductRail.vue'
import { useTranslations } from '@/i18n'
import { usePromoNow, formatCountdown } from '@/composables/usePromoCountdown.js'
import { useUserPreferences } from '@/composables/useUserPreferences.js'

// Helper: Check if a category is targeted by any promotion
function categoryHasPromotion(category, promotions) {
    if (!promotions || !promotions.length) return false
    const categoryId = category?.id ?? null
    return promotions.some(p =>
        (p.targets || []).some(t => {
            if (t.target_type !== 'category') return false
            if (categoryId && t.target_id == categoryId) return true
            return false
        })
    )
}

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
    popularSearches: { type: Array, default: () => [] },
    footerColumns: { type: Array, default: () => [] },
    supportContacts: { type: Array, default: () => [] },
    downloadBadges: { type: Array, default: () => [] },
    paymentBadges: { type: Array, default: () => [] },
})

const page = usePage()
const { t } = useTranslations()
const now = usePromoNow()
const { currentCurrency, formatCurrency, convertCurrency } = useUserPreferences()
const displayCurrency = computed(() => currentCurrency.value || props.currency)
const homepagePromotions = computed(() =>
    Array.isArray(page.props.homepagePromotions) ? page.props.homepagePromotions : []
)

const displayPrice = (amount) =>
    formatCurrency(convertCurrency(Number(amount ?? 0), 'USD', displayCurrency.value), displayCurrency.value)

const logisticsSupportPromo = computed(() => {
    return homepagePromotions.value.find((promo) => promo.intent === 'shipping_support') ?? null
})

const activeIndex = ref(0)
const hoverPause = ref(false)
let timer = null

const nextSlide = () => {
    if (heroSlides.value.length <= 1) return
    activeIndex.value = (activeIndex.value + 1) % heroSlides.value.length
}

const prevSlide = () => {
    if (heroSlides.value.length <= 1) return
    activeIndex.value = (activeIndex.value - 1 + heroSlides.value.length) % heroSlides.value.length
}

const goToSlide = (index) => {
    if (heroSlides.value.length <= 1) return
    activeIndex.value = index
}

const startTimer = () => {
    if (heroSlides.value.length <= 1) return
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

onBeforeUnmount(() => {
    stopTimer()
})

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
    const source = Array.isArray(props.categoryHighlights) ? props.categoryHighlights : []
    return source.map((category) => ({
        ...category,
        id: category.id ?? null,
        slug: category.slug ?? null,
        short: buildShort(String(category.name)),
    }))
})

const featuredCategorySections = computed(() => {
    const source = Array.isArray(props.featuredCategorySections) ? props.featuredCategorySections : []

    return source
        .map((section) => ({
            ...section,
            id: section?.id ?? null,
            name: section?.name ?? t('Featured category'),
            viewAllHref: section?.viewAllHref || '/products',
            products: Array.isArray(section?.products) ? section.products : [],
        }))
        .filter((section) => section.products.length > 0)
})

const formatCount = (count) => {
    const value = Number(count ?? 0)
    if (Number.isNaN(value) || value <= 0) {
        return '0'
    }
    return new Intl.NumberFormat().format(value)
}

const featuredDeals = computed(() => (Array.isArray(props.flashDeals) ? props.flashDeals.slice(0, 6) : []))
const sectionBlocks = computed(() => {
    return [
        { key: 'trending', title: t('Trending Now'), items: props.trending ?? [] },
        { key: 'best-sellers', title: t('Best Sellers'), items: props.bestSellers ?? [] },
        { key: 'recommended', title: t('Recommended For You'), items: props.recommended ?? [] },
        { key: 'new-arrivals', title: t('New Arrivals'), items: props.bestValue ?? [] },
    ].filter((section) => Array.isArray(section.items) && section.items.length)
})
const categoryIcons = computed(() => {
    const fromContent = Array.isArray(props.homeContent?.category_icons) ? props.homeContent.category_icons : []
    const fallback = Array.isArray(props.categories) ? props.categories : []
    const source = fromContent.length ? fromContent : fallback
    return source.slice(0, 16).map((item) => {
        const label = item.label || item.name
        const slug = item.slug || item.id || label
        return {
            ...item,
            label,
            slug,
            badge: item.badge || buildShort(String(label || '')),
            href: item.href || `/categories/${encodeURIComponent(slug)}`,
        }
    })
})
const savingsBlocks = computed(() => {
    const blocks = Array.isArray(props.homeContent?.savings_blocks) ? props.homeContent.savings_blocks : []
    return blocks.slice(0, 6)
})
const focusBlocks = computed(() => {
    const blocks = Array.isArray(props.homeContent?.focus_blocks) ? props.homeContent.focus_blocks : []
    return blocks.slice(0, 6).map((block) => ({
        ...block,
        title: block.title || t('Featured story'),
        subtitle: block.subtitle || '',
    }))
})
const popularSearches = computed(() => {
    if (Array.isArray(props.homeContent?.popular_searches)) return props.homeContent.popular_searches
    return Array.isArray(props.popularSearches) ? props.popularSearches : []
})
const footerColumns = computed(() => {
    if (Array.isArray(props.homeContent?.footer_columns)) return props.homeContent.footer_columns
    return Array.isArray(props.footerColumns) ? props.footerColumns : []
})
const supportContacts = computed(() => {
    if (Array.isArray(props.homeContent?.support_contacts)) return props.homeContent.support_contacts
    return Array.isArray(props.supportContacts) ? props.supportContacts : []
})
const downloadBadges = computed(() => {
    if (Array.isArray(props.homeContent?.download_badges)) return props.homeContent.download_badges
    return Array.isArray(props.downloadBadges) ? props.downloadBadges : []
})
const paymentBadges = computed(() => {
    if (Array.isArray(props.homeContent?.payment_badges)) return props.homeContent.payment_badges
    return Array.isArray(props.paymentBadges) ? props.paymentBadges : []
})
const contentSections = computed(() => {
    const sections = Array.isArray(props.homeContent?.content_sections) ? props.homeContent.content_sections : []
    return sections.filter((section) => section?.title && section?.body)
})
const promotionForProduct = (product) => {
    if (!product || homepagePromotions.value.length === 0) return null
    return homepagePromotions.value.find(p => {
        if (!Array.isArray(p.targets) || p.targets.length === 0) {
            return true
        }
        return p.targets.some(t => {
            if (t.target_type === 'product') return t.target_id == product.id
            if (t.target_type === 'category') return t.target_id == product.category_id
            return false
        })
    })
}

const dealCountdown = (product) => {
    const promo = promotionForProduct(product)
    if (!promo?.end_at) return ''
    return formatCountdown(promo.end_at, now.value) ?? ''
}

const promoCountdown = (promo) => {
    if (!promo?.end_at) return ''
    return formatCountdown(promo.end_at, now.value) ?? ''
}

const featuredPromotions = computed(() => [])

const promoValue = (promo) => {
    if (!promo) return ''
    if (promo.value_type === 'percentage') return `${promo.value}% ${t('off')}`
    if (promo.value_type === 'fixed') return `-${displayPrice(promo.value)}`
    if (promo.value_type === 'free_shipping') return t('Free shipping')
    return t('Special offer')
}

const promoScope = (promo) => {
    if (!promo?.targets || promo.targets.length === 0) return t('Sitewide')
    const hasProduct = promo.targets.some(t => t.target_type === 'product')
    const hasCategory = promo.targets.some(t => t.target_type === 'category')
    if (hasProduct && hasCategory) return t('Products + categories')
    if (hasProduct) return t('Product deals')
    if (hasCategory) return t('Category deals')
    return t('Limited offer')
}

const promoCta = (promo) => {
    const promotionId = promo?.id ?? null
    if (promo?.type === 'flash_sale') {
        return { label: t('Shop flash deals'), href: '/products?promotion_type=flash_sale' }
    }

    if (!promo?.targets || promo.targets.length === 0) {
        return { label: t('View deals'), href: '/promotions/deals' }
    }
    if (promotionId) {
        return { label: t('Shop promoted products'), href: `/products?promotion=${promotionId}` }
    }
    return { label: t('Browse promoted products'), href: '/products' }
}

const topStrip = computed(() => {
    if (Array.isArray(props.homeContent?.top_strip) && props.homeContent.top_strip.length) {
        return props.homeContent.top_strip
    }
    return []
})

const heroSlides = computed(() => {
    const source = Array.isArray(props.homeContent?.hero_slides) ? props.homeContent.hero_slides : []
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

watch(() => heroSlides.value.length, (length) => {
    activeIndex.value = 0
    if (length > 1) {
        startTimer()
        return
    }
    stopTimer()
})

const railCards = computed(() => {
    if (Array.isArray(props.homeContent?.rail_cards) && props.homeContent.rail_cards.length) {
        return props.homeContent.rail_cards
    }
    return []
})
const promoBanner = computed(() => {
    if (props.homeContent?.promo_banner && typeof props.homeContent.promo_banner === 'object') {
        return props.homeContent.promo_banner
    }
    return null
})
const reasonsBlocks = computed(() => {
    const reasons = Array.isArray(props.homeContent?.reasons) ? props.homeContent.reasons : []
    return reasons.map((r) => ({
        title: r.title || t('Reason'),
        subtitle: r.subtitle || '',
        icon: r.icon || '',
        image: r.image || '',
    }))
})

const featuredCategories = computed(() => {
    const explicit = Array.isArray(props.featuredCategories) ? props.featuredCategories : []
    const fallback = Array.isArray(props.categories) ? props.categories : []
    const source = explicit.length ? explicit : fallback

    return source
        .filter((category) => {
            if (!category?.name) return false
            const flag = category.is_featured ?? category.isFeatured ?? category.featured
            return flag === true || flag === 1 || flag === '1' || flag === 'true'
        })
        .map((category) => ({
            ...category,
            viewAllHref: `/products?category=${encodeURIComponent(category.slug || category.name)}`,
        }))
})

const bannerStrip = computed(() => {
    if (props.homeContent?.banner_strip && typeof props.homeContent.banner_strip === 'object') {
        return props.homeContent.banner_strip
    }
    return null
})

const valueProps = computed(() => {
    const configured = page.props.storefront?.value_props
    if (Array.isArray(configured) && configured.length) {
        return configured
    }
    return []
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
    display: block;
    gap: 18px;
}

.rail-track {
    display: flex;
    gap: 12px;
}

.rail-card {
    min-width: 180px;
    max-width: 220px;
}

.rail-chip {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    background: #fff;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
    transition: transform 150ms ease, box-shadow 150ms ease;
    white-space: nowrap;
}
.rail-chip:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
}
.rail-chip-icon {
    width: 30px;
    height: 30px;
    border-radius: 12px;
    background: var(--Simbazu-cream);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: var(--Simbazu-ink);
}
.rail-chip-label {
    font-weight: 700;
    color: var(--Simbazu-ink);
}

.offer-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}
.offer-card {
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    background: #fff;
    padding: 14px;
    box-shadow: 0 12px 26px rgba(15, 23, 42, 0.06);
    display: grid;
    gap: 8px;
}
.offer-badge {
    display: inline-flex;
    padding: 6px 10px;
    border-radius: 999px;
    background: var(--Simbazu-cream);
    color: var(--Simbazu-ink);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
}
.offer-title {
    font-size: 15px;
    font-weight: 800;
    color: var(--Simbazu-ink);
    line-height: 1.2;
}
.offer-subtitle {
    font-size: 12px;
    color: #475569;
}
.offer-tag {
    font-weight: 800;
    color: var(--Simbazu-ink);
    font-size: 13px;
}

.deal-rail {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 4px;
}
.deal-card-rail {
    min-width: 180px;
    max-width: 220px;
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
    gap: 6px;
    padding: 0 8px;
    flex-wrap: wrap;
    justify-content: center;
}

.hero-dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: #d1d5db;
    border: 1px solid #cbd5e1;
    transition: all 180ms ease;
}

.hero-dot.is-active {
    background: var(--Simbazu-ink);
    width: 16px;
    border-color: var(--Simbazu-ink);
}

.rail-track {
    display: grid;
    gap: 10px;
    grid-auto-flow: column;
    grid-auto-columns: minmax(180px, 1fr);
    overflow-x: auto;
    padding-bottom: 6px;
}

.deal-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.hero-rails {
    display: grid;
    gap: 16px;
}

.rail-card {
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

.promo-banner {
    width: 100%;
}

.promo-banner-inner {
    display: grid;
    gap: 16px;
    padding: 18px;
    border-radius: 24px;
    border: 1px solid #e5e7eb;
    background: #fff;
    box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
}

.promo-copy h2 {
    font-size: clamp(1.5rem, 1.2vw + 1rem, 2rem);
    font-weight: 800;
    color: #111827;
    margin-top: 6px;
}

.promo-copy .promo-kicker {
    font-size: 11px;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    font-weight: 700;
    color: #6b7280;
}

.promo-subtitle {
    color: #4b5563;
    font-size: 13px;
    margin-top: 6px;
}

.promo-actions .promo-cta {
    display: inline-flex;
    margin-top: 10px;
    padding: 10px 16px;
    border-radius: 999px;
    background: var(--Simbazu-ink);
    color: #fff;
    font-weight: 700;
}

.promo-strip {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(120px, 1fr);
    gap: 10px;
    overflow-x: auto;
    padding-bottom: 6px;
}

.promo-strip img {
    width: 100%;
    border-radius: 14px;
    background: #fff;
    border: 1px solid #e5e7eb;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
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
    gap: 10px;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

.promotion-card {
    border-radius: 18px;
    padding: 18px;
    background: #ffffff;
    border: 1px solid #e7edf4;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
    position: relative;
    overflow: hidden;
}

.promotion-card::before {
    content: "";
    position: absolute;
    inset: -60px -60px auto auto;
    width: 180px;
    height: 180px;
    border-radius: 999px;
    background: radial-gradient(circle, rgba(41, 171, 135, 0.35), rgba(41, 171, 135, 0));
    opacity: 0.6;
}

.promotion-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.promotion-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #0f172a;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 6px 12px;
}

.promotion-timer {
    font-size: 11px;
    font-weight: 600;
    color: #b45309;
}

.promotion-title {
    margin-top: 12px;
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}

.promotion-desc {
    margin-top: 6px;
    font-size: 12px;
    line-height: 1.5;
    color: #64748b;
    min-height: 32px;
}

.promotion-meta {
    margin-top: 10px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.promotion-value {
    font-size: 14px;
    font-weight: 700;
    color: #111827;
}

.promotion-scope {
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 4px 10px;
}

.promotion-hint {
    margin-top: 8px;
    font-size: 11px;
    color: #64748b;
    font-weight: 600;
}

.promotion-cta {
    display: inline-flex;
    margin-top: 12px;
    font-size: 12px;
    font-weight: 700;
    color: #29ab87;
}

.seasonal-grid {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.seasonal-card {
    border-radius: 22px;
    border: 1px solid #eef2f7;
    background: #ffffff;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 12px;
    transition: transform 200ms ease, box-shadow 200ms ease;
}

.seasonal-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
}

.seasonal-media {
    position: relative;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    background: #f8fafc;
}

.seasonal-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.seasonal-fallback {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    color: #b45309;
    background: var(--Simbazu-cream);
}

.seasonal-tag {
    position: absolute;
    top: 12px;
    right: 12px;
    border-radius: 999px;
    background: #111827;
    color: #ffffff;
    padding: 4px 10px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
}

.seasonal-body {
    padding: 0 16px 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.seasonal-kicker {
    font-size: 10px;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: #9ca3af;
    font-weight: 700;
}

.seasonal-body h3 {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
}

.seasonal-subtitle {
    font-size: 12px;
    color: #6b7280;
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

.category-stack {
    gap: 26px;
    padding: 6px 0;
}

.featured-category-sections {
    gap: 20px;
}

.featured-category-block {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.featured-category-title {
    font-size: clamp(1.2rem, 1.1vw + 1rem, 1.65rem);
    margin-top: 0;
}

.featured-category-slider {
    display: grid;
    gap: 12px;
    grid-auto-flow: column;
    grid-auto-columns: minmax(180px, 1fr);
    overflow-x: auto;
    padding: 4px 2px 8px;
    scroll-snap-type: x proximity;
}

.featured-category-product {
    scroll-snap-align: start;
}

.deal-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

.icon-grid {
    display: grid;
    gap: 10px;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
}

.icon-tile {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 12px;
    border-radius: 14px;
    background: #fff;
    border: 1px solid #e5e7eb;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
}

.icon-avatar {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: var(--Simbazu-cream);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #111827;
}

.icon-label {
    font-weight: 700;
    font-size: 13px;
    color: #0f172a;
    text-align: center;
}

.savings-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.savings-card {
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    background: #fff;
    padding: 14px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
    display: grid;
    gap: 8px;
}

.savings-kicker {
    font-size: 11px;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #6b7280;
    font-weight: 700;
}

.savings-title {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
}

.savings-subtitle {
    font-size: 13px;
    color: #4b5563;
}

.savings-tag {
    display: inline-flex;
    width: fit-content;
    padding: 6px 10px;
    border-radius: 999px;
    background: var(--Simbazu-cream);
    color: #111827;
    font-weight: 700;
    font-size: 12px;
}

.focus-grid {
    display: grid;
    gap: 14px;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.focus-card {
    border-radius: 18px;
    border: 1px solid #e5e7eb;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.focus-media {
    aspect-ratio: 16 / 9;
    background: #f8fafc;
    overflow: hidden;
}

.focus-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.focus-body {
    padding: 14px;
    display: grid;
    gap: 6px;
}

.focus-kicker {
    font-size: 11px;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #6b7280;
    font-weight: 700;
}

.focus-subtitle {
    font-size: 12px;
    color: #4b5563;
}

.content-stack {
    display: grid;
    gap: 12px;
}

.content-section {
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    background: #fff;
    padding: 16px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
}

.content-title {
    font-weight: 800;
    font-size: 15px;
    color: #0f172a;
}

.content-body {
    margin-top: 8px;
    color: #4b5563;
    font-size: 13px;
    line-height: 1.5;
}

.content-link {
    margin-top: 10px;
    display: inline-flex;
    font-weight: 700;
    color: #111827;
    font-size: 13px;
}

.reasons-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.reason-card {
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    background: #fff;
    padding: 14px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    display: grid;
    gap: 8px;
    text-align: center;
}

.reason-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto;
    border-radius: 14px;
    background: var(--Simbazu-cream);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #111827;
}

.reason-card img {
    width: 48px;
    height: 48px;
    margin: 0 auto;
    object-fit: contain;
}

.reason-title {
    font-weight: 800;
    color: #0f172a;
    font-size: 14px;
}

.reason-copy {
    color: #4b5563;
    font-size: 12px;
}

.chip-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.chip-pill {
    display: inline-flex;
    align-items: center;
    padding: 8px 14px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #fff;
    font-weight: 600;
    font-size: 13px;
    color: #0f172a;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
}

.support-strip {
    border-radius: 20px;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
    padding: 18px;
    display: grid;
    gap: 12px;
}

.support-title {
    font-weight: 800;
    font-size: 16px;
    color: #111827;
}

.support-items {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.support-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.support-label {
    font-weight: 700;
    color: #1f2937;
}

.support-link {
    color: #0f172a;
    font-weight: 600;
}

.footer-grid {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
}

.footer-column {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.footer-heading {
    font-weight: 800;
    font-size: 14px;
    color: #0f172a;
    text-transform: capitalize;
}

.footer-link {
    color: #4b5563;
    font-size: 13px;
}

.footer-meta {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    align-items: center;
}

.badge-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
}

.badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #fff;
    font-weight: 700;
    font-size: 12px;
    color: #0f172a;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.05);
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

/* Responsive adjustments for product grids */
@media (max-width: 640px) {
    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
    }

    .featured-category-slider {
        grid-auto-columns: minmax(140px, 1fr);
    }

    .deal-grid {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
}

@media (min-width: 768px) and (max-width: 1024px) {
    .grid {
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    }
}

@media (min-width: 1280px) {
    .grid {
        grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
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
    .hero-carousel {
        padding: 16px;
    }

    .hero-frame {
        min-height: auto;
    }

    .hero-slide {
        position: static;
        display: none;
        opacity: 1;
        transform: none;
        pointer-events: auto;
    }

    .hero-slide.is-active {
        display: grid;
    }

    .hero-title {
        font-size: clamp(1.8rem, 4vw + 1rem, 2.5rem);
    }

    .hero-image img {
        width: 100%;
        max-width: 100%;
        aspect-ratio: 4 / 3;
        object-fit: cover;
        border-width: 3px;
    }

    .hero-controls {
        gap: 8px;
    }

    .hero-dot {
        width: 6px;
        height: 6px;
    }
}
</style>
