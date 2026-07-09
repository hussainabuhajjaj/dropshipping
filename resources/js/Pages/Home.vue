<template>
  <StorefrontLayout>
    <div class="min-h-screen bg-white pb-28">
      <!-- Delivery Promise Bar -->
      <div class="border-b border-gray-100 bg-[#f8fafc]">
        <div class="flex items-center justify-center gap-6 overflow-x-auto px-4 py-2.5 text-xs font-medium text-slate-600 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <span class="flex shrink-0 items-center gap-1.5">
            <svg class="h-3.5 w-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0 1 12 2.944a11.955 11.955 0 0 1-8.618 3.04A12.02 12.02 0 0 0 3 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            {{ t('Secure checkout') }}
          </span>
          <span class="flex shrink-0 items-center gap-1.5">
            <svg class="h-3.5 w-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            {{ t('Delivery in 7-18 days') }}
          </span>
          <span class="flex shrink-0 items-center gap-1.5">
            <svg class="h-3.5 w-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-4-4 4m0 0-4-4m4 4V4" />
            </svg>
            {{ t('Easy returns') }}
          </span>
          <span class="flex shrink-0 items-center gap-1.5">
            <svg class="h-3.5 w-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7m16 0v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5m16 0h-2.586a1 1 0 0 0-.707.293l-2.414 2.414a1 1 0 0 1-.707.293h-3.172a1 1 0 0 1-.707-.293l-2.414-2.414A1 1 0 0 0 6.586 13H4" />
            </svg>
            {{ t('Real-time tracking') }}
          </span>
        </div>
      </div>

      <!-- Hero: Center Slider + Left/Right Collections -->
      <div class="lg:grid lg:grid-cols-[180px_1fr_180px] xl:grid-cols-[200px_1fr_200px] lg:gap-2 xl:gap-3 lg:px-4">
        <div v-if="leftCollections.length" class="hidden lg:flex flex-col gap-2">
          <Link
            v-for="col in leftCollections"
            :key="col.id ?? col.href"
            :href="col.href ?? '/collections'"
            class="group relative block overflow-hidden rounded-lg"
          >
            <img
              v-if="col.image"
              :src="col.image"
              :alt="col.title"
              class="aspect-[3/2] w-full object-cover transition duration-300 group-hover:scale-105"
              loading="lazy"
            />
            <div v-else class="aspect-[3/2] bg-gradient-to-br from-slate-100 to-slate-200" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
            <div class="absolute bottom-0 left-0 right-0 p-2">
              <p v-if="col.kicker" class="text-[0.5rem] font-bold uppercase tracking-[0.15em] text-amber-400">{{ col.kicker }}</p>
              <p class="text-[0.65rem] font-bold text-white leading-tight sm:text-xs">{{ col.title }}</p>
            </div>
          </Link>
        </div>

        <div
          class="relative overflow-hidden bg-gray-100 lg:rounded-xl"
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
                class="aspect-[1/1.3] w-full bg-cover bg-center sm:aspect-[2/1]"
                :style="{ backgroundImage: `url(${slide.image})` }"
              >
                <div class="flex h-full flex-col justify-center bg-gradient-to-r from-black/65 via-black/30 to-transparent px-6 text-white sm:px-10">
                  <p v-if="slide.badge" class="mb-2 text-[0.65rem] font-bold uppercase tracking-[0.25em] text-amber-400">
                    {{ slide.badge }}
                  </p>
                  <p class="max-w-[75%] text-xl font-black leading-tight sm:text-3xl">{{ slide.title }}</p>
                  <p v-if="slide.subtitle" class="mt-1.5 max-w-xs text-xs text-white/75 sm:text-sm">{{ slide.subtitle }}</p>
                  <Link
                    v-if="slide.primary"
                    :href="slide.primary.href"
                    class="mt-4 inline-flex h-10 w-fit items-center rounded-full bg-white px-6 text-sm font-bold text-slate-900 shadow-lg transition hover:bg-gray-100 active:scale-95"
                  >
                    {{ slide.primary.label }}
                  </Link>
                </div>
              </div>
              <div v-else class="flex aspect-[1/1.3] w-full items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200 sm:aspect-[2/1]">
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
            v-if="heroSlides.length > 1"
            type="button"
            class="absolute left-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-lg backdrop-blur transition hover:bg-white active:scale-90 cursor-pointer"
            @click="prevSlide"
          >
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 19l-7-7 7-7"/></svg>
          </button>
          <button
            v-if="heroSlides.length > 1"
            type="button"
            class="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-lg backdrop-blur transition hover:bg-white active:scale-90 cursor-pointer"
            @click="nextSlide"
          >
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 5l7 7-7 7"/></svg>
          </button>

          <div v-if="heroSlides.length > 1" class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
            <button
              v-for="(_, idx) in heroSlides"
              :key="idx"
              type="button"
              class="h-1.5 cursor-pointer rounded-full transition-all"
              :class="idx === currentSlide ? 'w-5 bg-white' : 'w-1.5 bg-white/50 hover:bg-white/70'"
              @click="currentSlide = idx"
            />
          </div>
        </div>

        <div v-if="rightCollections.length" class="hidden lg:flex flex-col gap-2">
          <Link
            v-for="col in rightCollections"
            :key="col.id ?? col.href"
            :href="col.href ?? '/collections'"
            class="group relative block overflow-hidden rounded-lg"
          >
            <img
              v-if="col.image"
              :src="col.image"
              :alt="col.title"
              class="aspect-[3/2] w-full object-cover transition duration-300 group-hover:scale-105"
              loading="lazy"
            />
            <div v-else class="aspect-[3/2] bg-gradient-to-br from-slate-100 to-slate-200" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
            <div class="absolute bottom-0 left-0 right-0 p-2">
              <p v-if="col.kicker" class="text-[0.5rem] font-bold uppercase tracking-[0.15em] text-amber-400">{{ col.kicker }}</p>
              <p class="text-[0.65rem] font-bold text-white leading-tight sm:text-xs">{{ col.title }}</p>
            </div>
          </Link>
        </div>
      </div>

      <!-- Mobile: Collections scroll strip below hero -->
      <div v-if="homeCollections.length" class="mt-3 flex gap-3 overflow-x-auto px-4 pb-1 lg:hidden [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <Link
          v-for="col in homeCollections"
          :key="col.id ?? col.href"
          :href="col.href ?? '/collections'"
          class="w-32 shrink-0"
        >
          <div class="relative overflow-hidden rounded-xl">
            <img
              v-if="col.image"
              :src="col.image"
              :alt="col.title"
              class="aspect-[3/4] w-full object-cover"
              loading="lazy"
            />
            <div v-else class="aspect-[3/4] bg-gradient-to-br from-slate-100 to-slate-200" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
            <p class="absolute bottom-1.5 left-2 right-2 text-xs font-bold text-white leading-tight">{{ col.title }}</p>
          </div>
        </Link>
      </div>

      <!-- Category Scroll Strip -->
      <div v-if="scrollCategories.length" class="mt-6" ref="categoryStripRef">
        <div class="flex gap-6 overflow-x-auto px-4 pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <Link
            v-for="cat in scrollCategories"
            :key="cat.id"
            :href="cat.href"
            class="flex w-[72px] shrink-0 cursor-pointer flex-col items-center gap-1.5 transition active:scale-95"
          >
            <div class="flex h-[68px] w-[68px] items-center justify-center overflow-hidden rounded-full bg-gray-100 shadow-sm ring-1 ring-gray-200/50 transition hover:shadow-md hover:ring-2 hover:ring-slate-300">
              <img
                v-if="cat.image"
                :src="cat.image"
                :alt="cat.name"
                class="h-full w-full object-cover"
                loading="lazy"
              />
              <span v-else class="text-lg font-bold text-gray-400">{{ cat.short }}</span>
            </div>
            <span class="whitespace-nowrap text-[0.6rem] font-semibold text-slate-600 sm:text-xs">{{ cat.name }}</span>
          </Link>
        </div>
      </div>

      <!-- Live Social Proof Marquee -->
      <div class="mt-5 overflow-hidden border-y border-slate-100 bg-gradient-to-r from-amber-50/80 via-white to-rose-50/80 py-2.5">
        <div class="flex animate-marquee gap-10 whitespace-nowrap px-4 text-xs font-semibold text-slate-600">
          <span class="flex items-center gap-1.5">
            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
            {{ t('12 people are viewing this store right now') }}
          </span>
          <span class="flex items-center gap-1.5">
            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
            {{ t('42 orders placed in the last hour') }}
          </span>
          <span class="flex items-center gap-1.5">
            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
            {{ t('Free shipping on orders over $50') }}
          </span>
          <span class="flex items-center gap-1.5">
            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
            {{ t('Over 10,000 happy customers worldwide') }}
          </span>
          <span class="flex items-center gap-1.5">
            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
            {{ t('12 people are viewing this store right now') }}
          </span>
          <span class="flex items-center gap-1.5">
            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
            {{ t('42 orders placed in the last hour') }}
          </span>
          <span class="flex items-center gap-1.5">
            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
            {{ t('Free shipping on orders over $50') }}
          </span>
          <span class="flex items-center gap-1.5">
            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
            {{ t('Over 10,000 happy customers worldwide') }}
          </span>
        </div>
      </div>

      <!-- Flash Deals -->
      <section v-if="flashFeed.length" class="mt-6 px-4">
        <div class="mb-4 flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-red-500 to-red-600 shadow-sm">
              <svg class="h-4 w-4 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            </div>
            <div>
              <h2 class="text-base font-black text-slate-900">{{ t('Flash Deals') }}</h2>
              <p v-if="countdown" class="text-xs font-semibold text-red-500">{{ countdown }}</p>
              <p v-else class="text-xs text-slate-400">{{ t('Limited time offers') }}</p>
            </div>
          </div>
          <Link :href="flashDealsViewAllHref" class="shrink-0 text-xs font-bold text-red-500 transition hover:text-red-600 cursor-pointer">{{ t('View all') }}</Link>
        </div>
        <div class="flex gap-3 overflow-x-auto pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <div
            v-for="product in flashFeed"
            :key="product.id"
            class="w-36 shrink-0"
          >
            <div class="group relative overflow-hidden rounded-xl bg-gray-100 shadow-sm transition hover:shadow-md">
              <div
                v-if="product.compare_at_price"
                class="absolute left-1.5 top-1.5 z-10 rounded bg-red-500 px-1.5 py-0.5 text-[0.6rem] font-bold text-white shadow-xs"
              >
                -{{ discountPercent(product) }}%
              </div>
              <Link :href="product.href || `/products/${product.slug}`">
                <img
                  v-if="product.media?.[0] || product.image"
                  :src="product.media?.[0] || product.image"
                  :alt="product.name"
                  class="aspect-[0.8] w-full object-cover transition duration-300 hover:scale-105"
                  loading="lazy"
                />
                <div v-else class="aspect-[0.8] bg-gradient-to-br from-gray-100 to-gray-200" />
              </Link>
              <div class="absolute inset-x-0 bottom-0 flex translate-y-full items-center justify-center bg-gradient-to-t from-black/60 to-transparent px-2 pb-2 pt-6 transition-transform duration-300 group-hover:translate-y-0">
                <button
                  type="button"
                  class="w-full rounded-full bg-white py-1.5 text-[0.55rem] font-bold text-slate-900 shadow-sm transition hover:bg-slate-100 active:scale-95"
                  @click="openQuickAdd(product)"
                >
                  {{ t('Quick add') }}
                </button>
              </div>
            </div>
            <Link :href="product.href || `/products/${product.slug}`" class="block">
              <p class="mt-1.5 text-sm font-black text-red-500">{{ displayPrice(product) }}</p>
              <p v-if="product.compare_at_price" class="text-xs font-medium text-slate-400 line-through">{{ displayCompareAt(product) }}</p>
            </Link>
          </div>
        </div>
      </section>

      <!-- Best Value / Budget Picks -->
      <section v-if="bestValueProducts.length" class="mt-6 px-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-900">{{ t('Best Value') }}</h2>
          <Link href="/products?sort=price-asc" class="shrink-0 text-xs font-semibold text-emerald-600 cursor-pointer">{{ t('View all') }}</Link>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
          <CompactProductCard
            v-for="(product, idx) in bestValueProducts"
            :key="product.id"
            :product="product"
            :currency="currency"
            :featured="idx === 0"
            @quick-add="openQuickAdd"
          />
        </div>
      </section>

      <!-- Collections scroll strip -->
      <section v-if="homeCollections.length" class="mt-6">
        <div class="flex gap-3 overflow-x-auto px-4 pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <Link
            v-for="col in homeCollections"
            :key="col.id ?? col.slug ?? col.name"
            :href="col.href ?? `/collections/${col.slug ?? col.id}`"
            class="relative w-40 shrink-0 overflow-hidden rounded-lg sm:w-52"
          >
            <img
              v-if="col.image"
              :src="col.image"
              :alt="col.name"
              class="aspect-[3/4] w-full object-cover"
              loading="lazy"
            />
            <div v-else class="aspect-[3/4] bg-gradient-to-br from-slate-100 to-slate-200" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
            <p class="absolute bottom-2 left-2 right-2 text-sm font-bold text-white leading-tight">{{ col.name }}</p>
          </Link>
        </div>
      </section>

      <!-- New Arrivals -->
      <section v-if="newArrivalsProducts.length" class="mt-6 px-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-900">{{ t('New Arrivals') }}</h2>
          <Link href="/products?sort=newest" class="shrink-0 text-xs font-semibold text-blue-600 cursor-pointer">{{ t('View all') }}</Link>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
          <CompactProductCard
            v-for="(product, idx) in newArrivalsProducts"
            :key="product.id"
            :product="product"
            :currency="currency"
            :featured="idx === 0"
            @quick-add="openQuickAdd"
          />
        </div>
      </section>

      <!-- Best Sellers -->
      <section v-if="bestSellerProducts.length" class="mt-6 px-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-900">{{ t('Best Sellers') }}</h2>
          <Link href="/products?sort=bestsellers" class="shrink-0 text-xs font-semibold text-red-500 cursor-pointer">{{ t('View all') }}</Link>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
          <CompactProductCard
            v-for="(product, idx) in bestSellerProducts"
            :key="product.id"
            :product="product"
            :currency="currency"
            :featured="idx === 0"
            @quick-add="openQuickAdd"
          />
        </div>
      </section>

      <!-- Featured Products -->
      <section v-if="featuredProducts.length" class="mt-6 px-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-900">{{ t('Featured') }}</h2>
          <Link href="/products?sort=featured" class="shrink-0 text-xs font-semibold text-amber-600 cursor-pointer">{{ t('View all') }}</Link>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
          <CompactProductCard
            v-for="(product, idx) in featuredProducts"
            :key="product.id"
            :product="product"
            :currency="currency"
            :featured="idx === 0"
            @quick-add="openQuickAdd"
          />
        </div>
      </section>

      <!-- Trendy Products -->
      <section v-if="trendyProducts.length" class="mt-6 px-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-900">{{ t('Trending Now') }}</h2>
          <Link href="/products?sort=trending" class="shrink-0 text-xs font-semibold text-purple-600 cursor-pointer">{{ t('View all') }}</Link>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
          <CompactProductCard
            v-for="(product, idx) in trendyProducts"
            :key="product.id"
            :product="product"
            :currency="currency"
            :featured="idx === 0"
            @quick-add="openQuickAdd"
          />
        </div>
      </section>

      <!-- Seasonal Drops -->
      <section v-if="seasonalDrops.length" class="mt-6 px-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-900">{{ t('Seasonal Drops') }}</h2>
          <Link :href="seasonalDropsViewAllHref" class="shrink-0 text-xs font-semibold text-rose-600 cursor-pointer">{{ t('View all') }}</Link>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
          <CompactProductCard
            v-for="(product, idx) in seasonalDrops"
            :key="product.id"
            :product="product"
            :currency="currency"
            :featured="idx === 0"
            @quick-add="openQuickAdd"
          />
        </div>
      </section>

      <!-- Featured Category Sections -->
      <template v-for="section in featuredCategorySections" :key="section.id">
        <section class="mt-6 px-4">
          <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900">{{ section.name }}</h2>
            <Link
              v-if="section.viewAllHref"
              :href="section.viewAllHref"
              class="shrink-0 text-xs font-semibold text-red-500 cursor-pointer"
            >
              {{ t('View all') }}
            </Link>
          </div>
          <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
            <CompactProductCard
              v-for="(product, idx) in section.products"
              :key="product.id"
              :product="product"
              :currency="currency"
              :featured="idx === 0"
              @quick-add="openQuickAdd"
            />
          </div>
        </section>
      </template>

      <!-- Recommended -->
      <section v-if="recommendedProducts.length" class="mt-6 px-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-900">{{ t('You May Also Like') }}</h2>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
          <CompactProductCard
            v-for="(product, idx) in recommendedProducts"
            :key="product.id"
            :product="product"
            :currency="currency"
            :featured="idx === 0"
            @quick-add="openQuickAdd"
          />
        </div>
      </section>

      <!-- Popular Searches -->
      <section v-if="popularSearches.length" class="mt-6 px-4">
        <p class="mb-3 text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400">{{ t('Trending searches') }}</p>
        <div class="flex flex-wrap gap-2">
          <Link
            v-for="search in popularSearches"
            :key="search.query"
            :href="search.href"
            class="rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 active:scale-95"
          >
            {{ search.query }}
          </Link>
        </div>
      </section>

      <!-- Why Shop With Us / Trust Section -->
      <section class="mt-8 mx-4 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 px-5 py-7 text-white">
        <p class="text-center text-[0.6rem] font-bold uppercase tracking-[0.25em] text-amber-400">{{ t('Why Simbazu') }}</p>
        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div class="flex flex-col items-center gap-2 text-center">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10">
              <svg class="h-5 w-5 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
            </div>
            <p class="text-sm font-bold">{{ t('Free Shipping') }}</p>
            <p class="text-[0.6rem] text-white/60">{{ t('On orders over $50') }}</p>
          </div>
          <div class="flex flex-col items-center gap-2 text-center">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10">
              <svg class="h-5 w-5 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0 1 12 2.944a11.955 11.955 0 0 1-8.618 3.04A12.02 12.02 0 0 0 3 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <p class="text-sm font-bold">{{ t('Secure Payments') }}</p>
            <p class="text-[0.6rem] text-white/60">{{ t('256-bit SSL encryption') }}</p>
          </div>
          <div class="flex flex-col items-center gap-2 text-center">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10">
              <svg class="h-5 w-5 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-4-4 4m0 0-4-4m4 4V4" />
              </svg>
            </div>
            <p class="text-sm font-bold">{{ t('Easy Returns') }}</p>
            <p class="text-[0.6rem] text-white/60">{{ t('30-day return policy') }}</p>
          </div>
          <div class="flex flex-col items-center gap-2 text-center">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10">
              <svg class="h-5 w-5 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
              </svg>
            </div>
            <p class="text-sm font-bold">{{ t('Real-time Tracking') }}</p>
            <p class="text-[0.6rem] text-white/60">{{ t('From warehouse to your door') }}</p>
          </div>
        </div>
      </section>

      <!-- Social Proof Counters -->
      <section class="mt-6 mx-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div class="rounded-xl border border-slate-100 bg-white px-3 py-4 text-center shadow-sm">
            <p class="text-xl font-black text-slate-900">10,000+</p>
            <p class="text-[0.55rem] font-semibold uppercase tracking-[0.15em] text-slate-400">{{ t('Products') }}</p>
          </div>
          <div class="rounded-xl border border-slate-100 bg-white px-3 py-4 text-center shadow-sm">
            <p class="text-xl font-black text-slate-900">5,000+</p>
            <p class="text-[0.55rem] font-semibold uppercase tracking-[0.15em] text-slate-400">{{ t('Happy Customers') }}</p>
          </div>
          <div class="rounded-xl border border-slate-100 bg-white px-3 py-4 text-center shadow-sm">
            <p class="text-xl font-black text-slate-900">4.8 ★</p>
            <p class="text-[0.55rem] font-semibold uppercase tracking-[0.15em] text-slate-400">{{ t('Average Rating') }}</p>
          </div>
          <div class="rounded-xl border border-slate-100 bg-white px-3 py-4 text-center shadow-sm">
            <p class="text-xl font-black text-slate-900">50+</p>
            <p class="text-[0.55rem] font-semibold uppercase tracking-[0.15em] text-slate-400">{{ t('Countries Shipped') }}</p>
          </div>
        </div>
      </section>

      <!-- Newsletter -->
      <section class="mt-6 mx-4 rounded-xl bg-slate-900 px-5 py-6 text-center text-white">
        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-amber-400">{{ t('Stay in the loop') }}</p>
        <p class="mt-1 text-xs text-white/70">{{ t('New drops, promos, and delivery updates straight to your inbox.') }}</p>
        <form class="mx-auto mt-3 flex max-w-xs gap-2" @submit.prevent="submitNewsletter">
          <input
            v-model="newsletterEmail"
            type="email"
            required
            :placeholder="t('Your email address')"
            class="min-w-0 flex-1 rounded-lg border-0 px-3 py-2 text-xs text-slate-900 placeholder-slate-400"
          />
          <button type="submit" class="shrink-0 rounded-lg bg-amber-500 px-4 py-2 text-xs font-bold text-slate-900 transition hover:bg-amber-400 active:scale-95">
            {{ newsletterSubmitted ? t('Subscribed!') : t('Subscribe') }}
          </button>
        </form>
      </section>
    </div>

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

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'
import { useUserPreferences } from '@/composables/useUserPreferences.js'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import CompactProductCard from '@/Components/homepage/CompactProductCard.vue'
import ProductQuickAddSheet from '@/Components/ProductQuickAddSheet.vue'
import AppDownloadPopup from '@/Components/homepage/AppDownloadPopup.vue'

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
const appDownloadPopupRef = ref(null)
const { currentCurrency, formatCurrency, convertCurrency } = useUserPreferences()
const displayCurrency = computed(() => currentCurrency.value || props.currency)

const currentSlide = ref(0)

const countdown = ref('')
let countdownTimer = null

const quickAddProduct = ref(null)
const quickAddSheetOpen = ref(false)

const openQuickAdd = (product) => {
  quickAddProduct.value = product
  quickAddSheetOpen.value = true
}
const closeQuickAdd = () => {
  quickAddSheetOpen.value = false
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

const discountPercent = (product) => {
  const current = product?.feed_price ?? product?.price ?? 0
  const original = product?.compare_at_price ?? 0
  if (!original || !current) return 0
  return Math.round(((original - current) / original) * 100)
}

const newsletterEmail = ref('')
const newsletterSubmitted = ref(false)

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
    // silently fail
  }
}

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

onMounted(() => {
  resumeAutoPlay()
  if (flashFeed.value.length) {
    tickCountdown()
    countdownTimer = setInterval(tickCountdown, 1000)
  }
})

onBeforeUnmount(() => {
  pauseAutoPlay()
  if (countdownTimer) clearInterval(countdownTimer)
})

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
      title: slide.title || t('New Arrivals'),
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
      title: t('Discover Our Products'),
      subtitle: '',
      image: fallbackImg,
      primary: { label: t('View Products'), href: '/products' },
    }]
  }

  return [{
    key: 'hero-0',
    badge: '',
    title: t('Welcome to Simbazu'),
    subtitle: '',
    image: null,
    primary: { label: t('Get Started'), href: '/products' },
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

const featuredProducts = computed(() => dedupeProducts(props.featured).slice(0, 16))
const bestSellerProducts = computed(() => dedupeProducts(props.bestSellers).slice(0, 16))
const recommendedProducts = computed(() => dedupeProducts(props.recommended).slice(0, 16))
const trendyProducts = computed(() => dedupeProducts(props.trending).slice(0, 16))
const bestValueProducts = computed(() => dedupeProducts(props.bestValue).slice(0, 16))
const newArrivalsProducts = computed(() => dedupeProducts(props.newArrivals).slice(0, 16))
const flashFeed = computed(() => (Array.isArray(props.flashDeals) ? props.flashDeals : []).slice(0, 12))

const allCollections = computed(() => {
  const cols = Array.isArray(props.homeCollections) ? props.homeCollections : []
  if (cols.length >= 6) return cols
  const padded = [...cols]
  while (padded.length < 6) padded.push(...cols)
  return padded.slice(0, 6)
})
const leftCollections = computed(() => allCollections.value.slice(0, 3))
const rightCollections = computed(() => allCollections.value.slice(3, 6))

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

<style scoped>
@keyframes marquee {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}
.animate-marquee {
  animation: marquee 30s linear infinite;
}
</style>
