<template>
    <div class="brand-theme min-h-screen min-h-[100dvh] text-slate-900" :style="themeStyle">
        <!-- Fullscreen navigation loader -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isNavigating"
                class="fixed inset-0 z-[9999] flex flex-col items-center justify-center gap-4 bg-white/90 backdrop-blur dark:bg-slate-900/90"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800"
                    >
                        <img v-if="logoUrl" :src="logoUrl" :alt="brandName" class="h-12 w-12 object-contain"/>
                        <span v-else class="text-lg font-semibold text-slate-800 dark:text-slate-100">
              {{ brandName }}
            </span>
                    </div>
                    <div class="text-xl font-semibold text-slate-900 dark:text-white">{{ brandName }}</div>
                </div>

                <DotLottieVue style="height: 240px; width: 240px" autoplay loop src="/lottie/loader.json"/>

                <p class="text-sm text-slate-600 dark:text-slate-300">{{ t('Loading...') }}</p>
            </div>
        </Transition>

        <!-- SEO Head -->
        <Head :title="seoTitle">
            <meta name="description" head-key="description" :content="seoDescription"/>
            <link v-if="canonicalUrl" head-key="canonical" rel="canonical" :href="canonicalUrl"/>

            <meta property="og:title" head-key="og:title" :content="seoTitle"/>
            <meta property="og:description" head-key="og:description" :content="seoDescription"/>
            <meta property="og:type" head-key="og:type" content="website"/>
            <meta v-if="canonicalUrl" property="og:url" head-key="og:url" :content="canonicalUrl"/>
            <meta v-if="seoImage" property="og:image" head-key="og:image" :content="seoImage"/>

            <meta name="twitter:card" head-key="twitter:card" content="summary_large_image"/>
            <meta name="twitter:title" head-key="twitter:title" :content="seoTitle"/>
            <meta name="twitter:description" head-key="twitter:description" :content="seoDescription"/>
            <meta v-if="seoImage" name="twitter:image" head-key="twitter:image" :content="seoImage"/>
            <link v-if="canonicalUrl" rel="alternate" hreflang="en" head-key="hreflang:en" :href="canonicalUrl" />
            <link v-if="frenchAlternateUrl" rel="alternate" hreflang="fr" head-key="hreflang:fr" :href="frenchAlternateUrl" />
            <link v-if="canonicalUrl" rel="alternate" hreflang="x-default" head-key="hreflang:x-default" :href="canonicalUrl" />
        </Head>

        <div aria-hidden="true" class="storefront-header-spacer" :style="headerSpacerStyle"></div>

        <!-- Marketplace Header -->
        <header
            ref="headerRef"
            class="storefront-header fixed top-0 inset-x-0 z-[100] border-b border-slate-200 bg-white shadow-sm text-slate-900"
            :class="[
                mobileHeaderCompact ? 'mobile-header-compact' : '',
                headerHidden ? 'storefront-header--hidden' : '',
            ]"
        >
            <AnnouncementBar />

            <!-- Top row -->
            <div class="container-base">
                <div class="flex items-center gap-2 transition-all duration-200" :class="mobileHeaderCompact ? 'py-2' : 'py-3'">
                    <!-- Mobile Menu Toggle -->
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 lg:hidden"
                        @click="mobileOpen = true"
                    >
                        <span class="sr-only">{{ t('Open menu') }}</span>
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <!-- Brand Logo -->
                    <Link href="/" class="flex shrink-0 items-center gap-2">
                        <img v-if="logoUrl" :src="logoUrl" :alt="brandName" class="w-auto transition-all duration-200" :class="mobileHeaderCompact ? 'h-7' : 'h-9'"/>
                        <span v-else class="text-lg font-bold font-heading text-slate-900">{{ brandName }}</span>
                    </Link>

                    <!-- Location Selector (desktop) -->
                    <button
                        type="button"
                        class="hidden items-center gap-1.5 rounded-lg px-2.5 py-2 text-xs text-slate-500 transition hover:bg-slate-100 lg:flex"
                        @click="locationOpen = !locationOpen"
                    >
                        <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        <span class="font-medium text-slate-700">{{ selectedLocation }}</span>
                    </button>

                    <!-- Large Search Bar (desktop) -->
                    <form class="hidden flex-1 items-center lg:flex" @submit.prevent="submitSearch">
                        <div ref="desktopSearchRef" class="relative mx-auto w-full max-w-2xl">
                            <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z"/>
                            </svg>
                            <input
                                v-model="search"
                                type="search"
                                :placeholder="t('Search products...')"
                                class="w-full rounded-full border border-slate-300 bg-slate-50 px-4 py-2.5 pl-10 text-sm text-slate-900 placeholder-slate-400 transition focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-0"
                                :aria-label="t('Search products')"
                                @focus="handleSearchFocus"
                                @keydown.down.prevent="handleSuggestionNext"
                                @keydown.up.prevent="handleSuggestionPrev"
                                @keydown.enter.prevent="handleSearchEnter"
                                @keydown.esc.prevent="closeSearchSuggestions"
                            />
                            <button
                                type="submit"
                                class="absolute right-1 top-1/2 -translate-y-1/2 rounded-full bg-red-500 px-5 py-1.5 text-xs font-semibold text-white transition hover:bg-red-600"
                            >
                                {{ t('Search') }}
                            </button>

                            <SearchSuggestions
                                v-if="showSearchSuggestions"
                                :items="searchSuggestionItems"
                                :is-fetching="isFetchingSuggestions"
                                :show-no-results="showNoSuggestionsState"
                                :is-showing-recent="isShowingRecentSuggestions"
                                :selected-index="selectedSuggestionIndex"
                                @select="selectSuggestion"
                                @hover="selectedSuggestionIndex = $event"
                                @clear-recent="clearRecentSearches"
                            />
                        </div>
                    </form>

                    <!-- Right Side Icons -->
                    <div class="ml-auto flex items-center gap-1">
                        <!-- Language Toggle (desktop) -->
                        <div class="hidden items-center gap-0.5 lg:flex">
                            <button
                                v-for="option in getLocaleOptions"
                                :key="option.code"
                                type="button"
                                class="rounded px-2 py-1 text-xs font-semibold uppercase transition"
                                :class="option.code === currentLanguage ? 'bg-red-500 text-white' : 'text-slate-500 hover:text-slate-800'"
                                :title="option.label"
                                @click="setLanguage(option.code)"
                            >
                                {{ option.code }}
                            </button>
                        </div>

                        <!-- Currency selector (desktop) -->
                        <select
                            v-if="displaySettings?.show_currency_selector"
                            v-model="currentCurrency"
                            @change="onCurrencyChange"
                            class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-600 focus:border-red-500 focus:outline-none hidden lg:block"
                        >
                            <option v-for="currency in availableCurrencies" :key="currency" :value="currency">
                                {{ currency }}
                            </option>
                        </select>

                        <!-- Wishlist -->
                        <Link
                            href="/account/wishlist"
                            class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-red-500"
                            :aria-label="t('Wishlist')"
                        >
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                            </svg>
                            <span
                                v-if="wishlistCount"
                                class="absolute -right-0.5 -top-0.5 inline-flex min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[0.55rem] font-bold text-white"
                            >
                                {{ wishlistCount }}
                            </span>
                        </Link>

                        <!-- Account -->
                        <div ref="accountRef" class="relative z-[100] hidden lg:block">
                            <button
                                type="button"
                                class="relative inline-flex h-9 w-9 items-center justify-center overflow-visible rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-red-500"
                                :aria-label="t('Account')"
                                :aria-expanded="accountOpen"
                                @click.stop="toggleAccount"
                            >
                                <span
                                    v-if="unreadNotifications"
                                    class="absolute -right-0.5 -top-0.5 z-10 inline-flex min-w-[1rem] items-center justify-center rounded-full bg-rose-600 px-1 py-0.5 text-[0.5rem] font-bold text-white shadow"
                                >
                                    {{ unreadNotifications > 99 ? '99+' : unreadNotifications }}
                                </span>
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                </svg>
                            </button>

                            <Transition
                                enter-active-class="transition duration-150 ease-out"
                                enter-from-class="opacity-0 translate-y-1"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition duration-100 ease-in"
                                leave-from-class="opacity-100 translate-y-0"
                                leave-to-class="opacity-0 translate-y-1"
                            >
                                <div
                                    v-if="accountOpen"
                                    class="absolute right-0 top-full mt-2 w-56 rounded-2xl border border-slate-200 bg-white p-3 text-sm shadow-lg"
                                >
                                    <div v-if="authUser" class="space-y-1 border-b border-slate-100 pb-3">
                                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ t('Signed in') }}</p>
                                        <p class="font-semibold text-slate-900">{{ authUser.name }}</p>
                                        <p class="text-xs text-slate-500">{{ authUser.email }}</p>
                                    </div>

                                    <div class="mt-3 grid gap-2">
                                        <Link v-if="authUser" :href="route('account.index')" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">{{ t('Account overview') }}</Link>
                                        <Link v-if="authUser" href="/account/notifications" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                            <span class="flex w-full items-center justify-between">
                                                <span>{{ t('Notifications') }}</span>
                                                <span v-if="unreadNotifications" class="ml-2 rounded-full bg-rose-600 px-2 py-0.5 text-[0.6rem] font-semibold text-white">{{ unreadNotifications }}</span>
                                            </span>
                                        </Link>
                                        <Link v-if="authUser" href="/orders" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">{{ t('My orders') }}</Link>
                                        <Link v-if="authUser" href="/account/addresses" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">{{ t('Addresses') }}</Link>
                                        <Link v-if="authUser" href="/account/payments" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">{{ t('Payments') }}</Link>
                                        <Link v-if="authUser" href="/account/wallet" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">{{ t('Wallet') }}</Link>
                                        <Link v-if="authUser" href="/account/refunds" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">{{ t('Refunds') }}</Link>
                                        <Link v-if="authUser" href="/account/wishlist" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                            <span class="flex items-center justify-between">
                                                <span>{{ t('Wishlist') }}</span>
                                                <span v-if="wishlistCount" class="ml-2 rounded-full bg-slate-900 px-2 py-0.5 text-[0.6rem] font-semibold text-white">{{ wishlistCount }}</span>
                                            </span>
                                        </Link>
                                        <Link v-if="!authUser" :href="route('login')" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">{{ t('Sign in') }}</Link>
                                        <Link v-if="!authUser" :href="route('register')" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">{{ t('Create account') }}</Link>
                                        <Link v-if="authUser" :href="route('logout')" method="post" as="button" class="rounded-lg px-3 py-2 text-left text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">{{ t('Sign out') }}</Link>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <!-- Cart -->
                        <div ref="cartRef" class="relative z-[100] hidden lg:block">
                            <button
                                type="button"
                                class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-red-500"
                                :aria-label="t('Cart')"
                                :aria-expanded="cartOpen"
                                @click.stop="toggleCart"
                            >
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                                </svg>
                                <span
                                    v-if="cartCount"
                                    class="absolute -right-0.5 -top-0.5 inline-flex min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[0.55rem] font-bold text-white"
                                >
                                    {{ cartCount }}
                                </span>
                            </button>

                            <Transition
                                enter-active-class="transition duration-150 ease-out"
                                enter-from-class="opacity-0 translate-y-1"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition duration-100 ease-in"
                                leave-from-class="opacity-100 translate-y-0"
                                leave-to-class="opacity-0 translate-y-1"
                            >
                                <div
                                    v-if="cartOpen"
                                    class="absolute right-0 top-full mt-2 flex max-h-[min(32rem,calc(100dvh-7rem))] w-72 flex-col rounded-2xl border border-slate-200 bg-white p-4 text-sm shadow-lg"
                                >
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ t('Cart') }}</p>

                                    <div v-if="cartLines.length" class="mt-3 flex min-h-0 flex-1 flex-col">
                                        <div class="min-h-0 flex-1 space-y-3 overflow-y-auto pr-1">
                                            <div v-for="line in cartLines" :key="line.id" class="flex items-center gap-3">
                                                <div
                                                    class="h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                                    <img v-if="line.media?.[0]" :src="line.media[0]" :alt="line.name"
                                                         class="h-full w-full object-cover"/>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-xs font-semibold text-slate-900">{{ line.name }}</p>
                                                    <p class="text-[0.7rem] text-slate-500">
                                                        {{ line.variant || t('Standard') }} ·
                                                        {{ t('Qty :quantity', {quantity: line.quantity}) }}
                                                    </p>
                                                </div>
                                                <div class="shrink-0 text-xs font-semibold text-slate-800">
                                                    {{ formatPrice(line.price) }}
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-600">
                                            <span>{{ t('Subtotal') }}</span>
                                            <span class="font-semibold text-slate-900">{{ formatPrice(cartSubtotal) }}</span>
                                        </div>

                                        <div class="mt-3 grid gap-2">
                                            <Link href="/cart" class="btn-primary w-full text-center">{{
                                                    t('View cart')
                                                }}
                                            </Link>
                                            <Link href="/checkout" class="btn-secondary w-full text-center">
                                                {{ t('Checkout') }}
                                            </Link>
                                        </div>
                                    </div>

                                    <div v-else class="mt-3 text-xs text-slate-500">
                                        {{ t('Your cart is empty. Start exploring the catalog.') }}
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile Search Bar -->
            <div class="border-t border-slate-100 px-4 py-2.5 lg:hidden">
                <form class="flex items-center gap-2" @submit.prevent="submitSearch">
                    <div ref="mobileSearchRef" class="relative w-full">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z"/>
                        </svg>
                        <input
                            v-model="search"
                            type="search"
                            :placeholder="t('Search products...')"
                            class="w-full rounded-full border border-slate-300 bg-slate-50 px-4 py-2 pl-9 text-sm text-slate-900 placeholder-slate-400 focus:border-slate-400 focus:bg-white focus:outline-none"
                            :aria-label="t('Search products')"
                            @focus="handleSearchFocus"
                            @keydown.down.prevent="handleSuggestionNext"
                            @keydown.up.prevent="handleSuggestionPrev"
                            @keydown.enter.prevent="handleSearchEnter"
                            @keydown.esc.prevent="closeSearchSuggestions"
                        />
                        <SearchSuggestions
                            v-if="showSearchSuggestions"
                            :items="searchSuggestionItems"
                            :is-fetching="isFetchingSuggestions"
                            :show-no-results="showNoSuggestionsState"
                            :is-showing-recent="isShowingRecentSuggestions"
                            :selected-index="selectedSuggestionIndex"
                            @select="selectSuggestion"
                            @hover="selectedSuggestionIndex = $event"
                            @clear-recent="clearRecentSearches"
                        />
                    </div>
                </form>
            </div>

            <div class="relative">
                <MainNav :t="t" />
                <MegaMenu :t="t" />
            </div>
        </header>

        <!-- Notices -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div v-if="notices.length" class="container-base">
                <div class="space-y-2">
                    <div
                        v-for="notice in notices"
                        :key="notice.key"
                        class="rounded-2xl border border-slate-200 bg-white/90 p-3 text-xs font-semibold text-slate-700 shadow-sm"
                    >
                        {{ notice.message }}
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Lucky Draw Campaign Announcement Bar -->
        <a
            v-if="luckyDrawCampaign"
            :href="'/campaigns/' + luckyDrawCampaign.slug"
            class="block bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 text-black hover:from-amber-400 hover:via-amber-300 hover:to-amber-400 transition-all"
        >
            <div class="mx-auto flex max-w-7xl items-center justify-center gap-2 px-4 py-2 text-xs font-bold sm:text-sm">
                <span class="animate-pulse">🎉</span>
                <span>{{ t('WIN A') }} {{ (luckyDrawCampaign.grand_prize || 'iPhone').toUpperCase() }}!</span>
                <span v-if="luckyDrawCampaign.min_order_amount" class="hidden sm:inline">
                    {{ t('Spend') }} {{ formatLuckyAmount(luckyDrawCampaign.min_order_amount, luckyDrawCampaign.currency) }}{{ t('+') }}
                </span>
                <span class="inline-flex items-center gap-1 rounded-full bg-black/20 px-2.5 py-0.5 text-[0.6rem] font-bold uppercase tracking-wider text-white">
                    {{ t('Shop Now') }}
                </span>
            </div>
        </a>

        <main class="container-base pb-24 pt-6 lg:pb-16 lg:pt-10">
            <slot/>
        </main>

        <footer class="border-t border-slate-200 bg-white/90 pb-24 lg:pb-0">
            <div class="container-base grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-5">
                <div class="space-y-3">
                    <p class="text-lg font-semibold text-slate-900">{{ brandName }}</p>
                    <p class="text-sm text-slate-600">{{ footerBlurb }}</p>
                    <div class="text-xs text-slate-500">
                        {{ t('Support: :email', {email: supportEmail}) }}
                    </div>
                    <PaymentBadges :label="t('Accepted payments')" />
                    
                    <!-- Social Media Links -->
                    <div v-if="socialLinks.length" class="flex gap-3 mt-4">
                        <a
                            v-for="link in socialLinks"
                            :key="link.href"
                            :href="link.href"
                            :aria-label="link.label"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-slate-400 hover:text-slate-600 transition-colors"
                        >
                            <svg v-if="link.icon === 'facebook'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                            <svg v-else-if="link.icon === 'twitter'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.953 4.57a10 10 0 01-2.825.748 4.958 4.958 0 00-8.343 2.488A4.958 4.958 0 003.031 9.75a4.958 4.958 0 002.632-2.917 4.958 4.958 0 01-2.212-.085 4.958 4.958 0 004.631 3.414 9.917 9.917 0 01-6.107 2.107 4.958 4.958 0 003.414-4.631 9.917 9.917 0 01-2.107 6.107 4.958 4.958 0 004.631-3.414 9.917 9.917 0 016.107-2.107 4.958 4.958 0 00-3.414 4.631z"/>
                            </svg>
                            <svg v-else-if="link.icon === 'instagram'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.85 0 3.204-.012 3.584-.069 4.85-.148 3.252-1.691 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.85-.069-3.252-.148-4.771-1.691-4.919-4.919-.058-1.265-.07-1.644-.07-4.85 0-3.204.012-3.583.069-4.849.148-3.252 1.691-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM5.838 12a6.162 6.162 0 1112.324 0 6.162 6.162 0 01-12.324 0zM12 16a4 4 0 110-8 4 4 0 010 8zm4.965-10.405a1.44 1.44 0 112.881.001 1.44 1.44 0 01-2.881-.001z"/>
                            </svg>
                            <svg v-else-if="link.icon === 'youtube'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-11.376.405a3.016 3.016 0 00-2.122 2.136C3.505 6.186 1.545 8.827 1.545 12c0 3.173 1.96 5.814 5.957 6.414a3.016 3.016 0 002.122 2.136c.878.347 3.87.405 11.376.405 7.505 0 10.498-.058 11.376-.405 1.498-1.082 2.136-2.122 2.136-3.173 0-5.814-1.96-8.827-5.957-6.414z"/>
                            </svg>
                            <svg v-else-if="link.icon === 'email'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                            </svg>
                            <svg v-else-if="link.icon === 'whatsapp'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.123-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.885-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                            <span v-else class="text-slate-600">{{ link.label }}</span>
                        </a>
                    </div>
                </div>

                <div v-for="column in footerColumns" :key="column.title" class="space-y-2 text-sm text-slate-600">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ column.title }}</p>
                    <Link v-for="link in column.links || []" :key="link.href + link.label" :href="link.href"
                          class="block hover:text-slate-900">
                        {{ link.label }}
                    </Link>
                </div>
            </div>

            <div class="border-t border-slate-100">
                <div
                    class="container-base flex flex-col gap-2 py-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                    <span>{{ copyrightText }} (c) {{ new Date().getFullYear() }}</span>
                    <span>{{ deliveryNotice }}</span>
                </div>
            </div>
        </footer>

        <!-- Mobile bottom app tabs -->
        <nav class="mobile-app-nav fixed inset-x-0 bottom-0 z-[55] border-t border-slate-200 bg-white/95 shadow-[0_-10px_35px_rgba(15,23,42,0.12)] backdrop-blur lg:hidden">
            <div class="mx-auto flex max-w-lg items-center justify-between px-2 pt-2">
                <Link
                    v-for="tab in mobileTabs"
                    :key="tab.key"
                    :href="tab.href"
                    class="mobile-app-tab"
                    :class="isMobileTabActive(tab) ? 'mobile-app-tab-active' : 'mobile-app-tab-idle'"
                >
                    <span class="relative inline-flex h-7 w-7 items-center justify-center">
                        <svg v-if="tab.icon === 'home'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 9.75V21h13.5V9.75" />
                        </svg>
                        <svg v-else-if="tab.icon === 'categories'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" />
                        </svg>
                        <svg v-else-if="tab.icon === 'download'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <svg v-else-if="tab.icon === 'search'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
                        </svg>
                        <svg v-else-if="tab.icon === 'cart'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                        </svg>
                        <svg v-else viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>

                            <span
                                v-if="tab.key === 'cart' && cartCount"
                                class="absolute -right-2 -top-1 inline-flex min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[0.6rem] font-bold text-white"
                            >
                                {{ cartCount > 99 ? '99+' : cartCount }}
                            </span>
                    </span>
                    <span class="text-[0.65rem] font-semibold tracking-wide">{{ tab.label }}</span>
                </Link>
            </div>
        </nav>

        <!-- Mobile overlay -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="mobileOpen" class="fixed inset-0 z-[60]">
                <div class="absolute inset-0 bg-slate-900/20" @click="mobileOpen = false"/>
            </div>
        </Transition>

        <!-- Mobile drawer -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-x-4 opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="-translate-x-4 opacity-0"
        >
            <aside
                v-if="mobileOpen"
                class="fixed inset-y-0 left-0 z-[70] flex w-[85%] max-w-xs flex-col overflow-hidden border-r border-slate-200 bg-white p-5"
            >
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-900">{{ t('Menu') }}</p>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-600"
                        @click="mobileOpen = false"
                    >
                        <span class="sr-only">{{ t('Close menu') }}</span>
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6l-12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="mt-6 min-h-0 flex-1 space-y-6 overflow-y-auto pb-24 pr-1">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Quick access') }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <Link
                                v-for="link in mobilePrimaryLinks"
                                :key="`${link.href}-${link.label}-mobile-primary`"
                                :href="link.href"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-800 transition hover:border-slate-300 hover:text-slate-900"
                                @click="mobileOpen = false"
                            >
                                <span class="flex items-center justify-between gap-2">
                                    <span>{{ link.label }}</span>
                                    <span
                                        v-if="link.badge"
                                        class="rounded-full bg-slate-900 px-2 py-0.5 text-[0.65rem] font-semibold text-white"
                                    >
                                        {{ link.badge }}
                                    </span>
                                </span>
                            </Link>
                        </div>
                    </div>

                    <MegaMenu mobile :t="t" @navigate="mobileOpen = false" />

                    <div class="space-y-2 rounded-2xl border border-slate-200 p-4 text-sm text-slate-600">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Account') }}</p>

                        <template v-if="authUser">
                            <Link v-for="link in mobileAccountLinks" :key="`${link.href}-${link.label}-mobile-account`" :href="link.href" class="block rounded-lg px-1 py-1.5 transition hover:text-slate-900" @click="mobileOpen = false">
                                <span class="inline-flex w-full items-center justify-between gap-2">
                                    <span>{{ link.label }}</span>
                                    <span v-if="link.badge" :class="link.badgeClass || 'rounded-full bg-slate-900 px-2 py-0.5 text-[0.6rem] font-semibold text-white'">
                                        {{ link.badge }}
                                    </span>
                                </span>
                            </Link>
                        </template>

                        <template v-else>
                            <Link :href="route('login')" class="block rounded-lg px-1 py-1.5 transition hover:text-slate-900" @click="mobileOpen = false">
                                {{ t('Sign in') }}
                            </Link>
                            <Link :href="route('register')" class="block rounded-lg px-1 py-1.5 font-semibold text-slate-900 transition hover:text-[#f59e0b]" @click="mobileOpen = false">
                                {{ t('Create account') }}
                            </Link>
                        </template>
                    </div>

                    <div class="space-y-2 rounded-2xl border border-slate-200 p-4 text-sm text-slate-600">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Support') }}</p>
                        <Link
                            v-for="link in mobileSupportLinks"
                            :key="`${link.href}-${link.label}-mobile-support`"
                            :href="link.href"
                            class="block rounded-lg px-1 py-1.5 transition hover:text-slate-900"
                            @click="mobileOpen = false"
                        >
                            {{ link.label }}
                        </Link>
                    </div>

                    <div class="space-y-4 rounded-2xl border border-slate-200 p-4">
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Language') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="option in getLocaleOptions"
                                    :key="option.code"
                                    type="button"
                                    class="cursor-pointer rounded-lg border px-3 py-2.5 text-xs font-medium uppercase transition active:scale-95"
                                    :class="option.code === currentLanguage ? 'border-[#f59e0b] bg-[#f59e0b] text-white' : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
                                    @click="setLanguage(option.code); mobileOpen = false"
                                >
                                    {{ option.code === 'en' ? 'English' : 'Français' }}
                                </button>
                            </div>
                        </div>

                        <div v-if="displaySettings?.show_currency_selector">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Currency') }}</p>
                            <select
                                v-model="currentCurrency"
                                @change="onCurrencyChange(); mobileOpen = false"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#f59e0b] focus:outline-none"
                            >
                                <option v-for="currency in availableCurrencies" :key="currency" :value="currency">
                                    {{ currency }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </aside>
        </Transition>

        <PopupBannerModal v-if="showStorefrontPopups" :banners="popupBanners" />
        <NewsletterPopup v-if="showStorefrontPopups" :settings="newsletterPopupSettings" />
        <CookieConsentBanner />
        <FloatingWhatsAppCTA v-if="!mobileOpen" />
        <SocialProofPopup v-if="false" />
    </div>
</template>

<script setup>

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'

import {Head, Link, router, usePage} from '@inertiajs/vue3'
import {DotLottieVue} from '@lottiefiles/dotlottie-vue'
import {useUserPreferences} from '@/composables/useUserPreferences.js'
import {usePersistentCart} from '@/composables/usePersistentCart.js'
import {useMultipleJsonLd} from '@/composables/useJsonLd.js'
import PopupBannerModal from '@/Components/PopupBannerModal.vue'
import NewsletterPopup from '@/Components/NewsletterPopup.vue'
import CookieConsentBanner from '@/Components/CookieConsentBanner.vue'
import PaymentBadges from '@/Components/PaymentBadges.vue'
import FloatingWhatsAppCTA from '@/Components/FloatingWhatsAppCTA.vue'
import SocialProofPopup from '@/Components/SocialProofPopup.vue'
import SearchSuggestions from '@/Components/SearchSuggestions.vue'
import AnnouncementBar from '@/Components/AnnouncementBar.vue'
import MainNav from '@/Components/navigation/MainNav.vue'
import MegaMenu from '@/Components/navigation/MegaMenu.vue'
import { useCategoryStore } from '@/stores/category'

import { toastAlert } from "@/utils/toast";

/** Persistent cart (kept here, remove if unused) */
const {cart: persistentCart, setCart, addLine, updateLine, removeLine, clearCart} = usePersistentCart()

// --- Multi-currency support ---
const { 
    currentCurrency, 
    currentLanguage, 
    setCurrency, 
    setLanguage, 
    formatCurrency, 
    convertCurrency,
    availableCurrencies,
    availableLanguages,
    getLocaleOptions,
    displaySettings
} = useUserPreferences()

const formatPrice = (amount) =>
    formatCurrency(convertCurrency(Number(amount ?? 0), 'USD', currentCurrency.value), currentCurrency.value)

function onCurrencyChange() {
    setCurrency(currentCurrency.value)
}

// --- App / page ---
const page = usePage()
const categoryStore = useCategoryStore()
const { desktopOpen: megaMenuOpen } = storeToRefs(categoryStore)

const luckyDrawCampaign = computed(() => page.props.luckyDraw || null)

function formatLuckyAmount(amount, currency) {
  const n = Number(amount || 0).toLocaleString()
  return currency === 'USD' ? `$${n}` : `${n} FCFA`
}

// Simple fallback for translations
const t = (key, replacements = {}) => {
  // Try to get from page props first
  const translations = page.props.translations || {}
  const text = translations[key] || key
  
  // Apply simple replacements
  if (replacements && typeof replacements === 'object') {
    return text.replace(/:(\w+)/g, (match, key) => replacements[key] || match)
  }
  
  return text
}

// --- UI state ---
const mobileOpen = ref(false)
const accountOpen = ref(false)
const cartOpen = ref(false)
const accountRef = ref(null)
const cartRef = ref(null)
const desktopSearchRef = ref(null)
const mobileSearchRef = ref(null)
const isNavigating = ref(false)
	const mobileHeaderCompact = ref(false)

	// --- Header (hide on scroll) ---
	const headerRef = ref(null)
	// Start with a reasonable default to reduce initial layout shift before measuring.
	const headerHeight = ref(88)
	const headerHidden = ref(false)
	const headerSpacerStyle = computed(() => ({
	    height: `${headerHeight.value}px`,
	}))
let headerVisibilityRaf = null
let headerLastScrollY = 0
let headerResizeObserver = null
const isSearchFocused = ref(false)
const isFetchingSuggestions = ref(false)
const selectedSuggestionIndex = ref(-1)
const searchSuggestions = ref({products: [], categories: []})
const recentSearches = ref([])
const recentSearchStorageKey = 'simbazu_recent_searches'
const maxRecentSearches = 6
let searchSuggestionsTimer = null
let searchSuggestionsAbortController = null
const enableMobileHeaderCompact = false
let mobileHeaderStateRaf = null

const selectedLocation = ref('Abidjan')
const locationOpen = ref(false)

	// --- Auth / storefront / cart ---
	const authUser = computed(() => page.props.auth?.user ?? null)
	const storefront = computed(() => page.props.storefront ?? {})
	const cartSummary = computed(() => page.props.cart ?? {lines: [], count: 0, subtotal: 0})



	const cartLines = computed(() => cartSummary.value.lines ?? [])
const cartCount = computed(() => cartSummary.value.count ?? 0)
const cartSubtotal = computed(() => Number(cartSummary.value.subtotal ?? 0))

const wishlistCount = computed(() => Number(page.props.wishlist?.count ?? 0))
const unreadNotifications = computed(() => {
    const prop = page.props.notifications
    if (prop && !Array.isArray(prop) && typeof prop === 'object' && 'unreadCount' in prop) {
        return Number(prop.unreadCount ?? 0)
    }
    return Number(page.props.unreadCount ?? 0)
})

const notices = computed(() => {
    const flash = page.props.flash ?? {}
    const entries = []
    if (flash.cart_notice) entries.push({key: 'cart', message: flash.cart_notice})
    if (flash.wishlist_notice) entries.push({key: 'wishlist', message: flash.wishlist_notice})
    return entries
})

const popupBanners = computed(() =>
    Array.isArray(page.props.popupBanners) ? page.props.popupBanners : []
)
const newsletterPopupSettings = computed(() => page.props.storefront ?? {})

// --- Links / footer ---
const fallbackHeaderLinks = [
    {label: t('Shop'), href: '/products'},
    {label: t('Collections'), href: '/collections'},
    {label: t('Promotions'), href: '/promotions'},
    {label: t('Track order'), href: '/orders/track'},
    {label: t('Support'), href: '/support'},
    {label: t('FAQ'), href: '/faq'},
]

const fallbackFooterColumns = [
    {
        title: t('Shop'),
        links: [
            {label: t('All products'), href: '/products'},
            {label: t('Track order'), href: '/orders/track'},
            {label: t('Cart'), href: '/cart'},
            {label: t('Checkout'), href: '/checkout'},
        ],
    },
    {
        title: t('Support'),
        links: [
            {label: t('Contact'), href: '/support'},
            {label: t('FAQ'), href: '/faq'},
            {label: t('About'), href: '/about'},
            {label: t('My orders'), href: '/orders'},
        ],
    },
    {
        title: t('Account'),
        links: [
            {label: t('Overview'), href: '/account'},
            {label: t('Notifications'), href: '/account/notifications'},
            {label: t('Orders'), href: '/orders'},
            {label: t('Addresses'), href: '/account/addresses'},
            {label: t('Payment methods'), href: '/account/payments'},
            {label: t('Refunds'), href: '/account/refunds'},
            {label: t('Wallet'), href: '/account/wallet'},
        ],
    },
    {
        title: t('Legal'),
        links: [
            {label: t('Shipping policy'), href: '/legal/shipping-policy'},
            {label: t('Refund policy'), href: '/legal/refund-policy'},
            {label: t('Terms of service'), href: '/legal/terms-of-service'},
            {label: t('Privacy policy'), href: '/legal/privacy-policy'},
            {label: t('Cookie policy'), href: '/legal/cookie-policy'},
            {label: t('User data deletion'), href: '/legal/user-data-deletion'},
        ],
    },
]

const brandName = computed(() => storefront.value.brand_name ?? page.props.site?.site_name ?? 'Simbazu')

const logoUrl = computed(() => {
    const path = page.props.site?.logo_path
    return path ? `/storage/${path}` : null
})

const footerBlurb = computed(() => storefront.value.footer_blurb ?? page.props.site?.site_description ?? t('Global sourcing with local clarity.'))
const deliveryNotice = computed(() => storefront.value.delivery_notice ?? t("Delivery to Cote d'Ivoire with duties shown before checkout."))
const copyrightText = computed(() => storefront.value.copyright_text ?? brandName.value)
const headerLinks = computed(() => storefront.value.header_links ?? fallbackHeaderLinks)
const footerColumns = computed(() => storefront.value.footer_columns ?? fallbackFooterColumns)
const socialLinks = computed(() => storefront.value.social_links ?? [])
const mobilePrimaryLinks = computed(() => [
    { label: t('Shop'), href: '/products' },
    { label: t('Collections'), href: '/collections' },
    { label: t('Promotions'), href: '/promotions' },
    { label: t('Cart'), href: '/cart', badge: cartCount.value ? (cartCount.value > 99 ? '99+' : cartCount.value) : null },
])
const mobileSupportLinks = computed(() => [
    { label: t('Track order'), href: '/orders/track' },
    { label: t('Support'), href: '/support' },
    { label: t('FAQ'), href: '/faq' },
])
const mobileAccountLinks = computed(() => [
    { label: t('Overview'), href: '/account' },
    {
        label: t('Notifications'),
        href: '/account/notifications',
        badge: unreadNotifications.value ? (unreadNotifications.value > 99 ? '99+' : unreadNotifications.value) : null,
        badgeClass: 'rounded-full bg-rose-600 px-2 py-0.5 text-[0.6rem] font-semibold text-white',
    },
    { label: t('Orders'), href: '/orders' },
    { label: t('Wishlist'), href: '/account/wishlist', badge: wishlistCount.value ? (wishlistCount.value > 99 ? '99+' : wishlistCount.value) : null },
    { label: t('Addresses'), href: '/account/addresses' },
    { label: t('Payments'), href: '/account/payments' },
    { label: t('Wallet'), href: '/account/wallet' },
    { label: t('Refunds'), href: '/account/refunds' },
])

// --- Theme colors ---
const themeColors = computed(() => {
    const site = page.props.site ?? {}
    return {
        primary: site.primary_color || '#f59e0b',
        secondary: site.secondary_color || '#2563eb',
        accent: site.accent_color || '#9ca3af',
        strong: '#0f172a',
        background: '#ffffff',
    }
})

const themeStyle = computed(() => ({
    '--brand-primary': themeColors.value.primary,
    '--brand-primary-2': themeColors.value.secondary,
    '--brand-accent': themeColors.value.accent,
    '--brand-strong': themeColors.value.strong,
    '--brand-bg': themeColors.value.background,
    '--brand-glow-start': themeColors.value.primary,
    '--brand-glow-end': themeColors.value.secondary,
    '--brand-soft': 'color-mix(in srgb, ' + themeColors.value.primary + ' 12%, white)',
}))

const supportEmail = computed(() => page.props.site?.support_email ?? 'info@simbazu.net')

// --- SEO ---
const currentPath = computed(() => (page.url || '').split('?')[0])
const showStorefrontPopups = computed(() => {
    const path = currentPath.value || ''
    if (path.startsWith('/admin') || path.startsWith('/filament')) return false
    if (path.startsWith('/coming-soon')) return false
    return true
})
const appUrl = computed(() => {
    if (typeof window !== 'undefined' && window.location?.origin) {
        return String(window.location.origin).replace(/\/$/, '')
    }

    const configured = page.props.appUrl
    if (configured) return String(configured).replace(/\/$/, '')
    return ''
})

const resolveAssetUrl = (path) => {
    if (!path) return null
    const trimmed = String(path)
    if (/^https?:\/\//i.test(trimmed)) return trimmed
    return `${appUrl.value}/${trimmed.replace(/^\//, '')}`
}

const seoTitle = computed(() => page.props.seo?.title ?? `Discover Quality Essentials Worldwide | ${brandName.value}`)
const seoDescription = computed(() => page.props.seo?.description ?? t('Curated global essentials, delivered with clarity.'))
const seoImage = computed(() => resolveAssetUrl(page.props.seo?.image))
const canonicalUrl = computed(() => (appUrl.value ? `${appUrl.value}${currentPath.value || ''}` : null))
const frenchAlternateUrl = computed(() => {
    if (!canonicalUrl.value) return null

    return `${canonicalUrl.value.replace(/\/$/, '')}?locale=fr`
})

const organizationSchema = computed(() => {
  const schema = {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: brandName.value,
    url: appUrl.value,
    logo: logoUrl.value,
  }
  
  const site = page.props.site
  if (site?.support_email) {
    schema.contactPoint = {
      '@type': 'ContactPoint',
      email: site.support_email,
      contactType: 'customer service',
    }
  }
  
  if (site?.support_whatsapp) {
    schema.sameAs = [`https://wa.me/${site.support_whatsapp.replace(/[^\d]/g, '')}`]
  }
  
  return JSON.stringify(schema)
})

const websiteSchema = computed(() => {
  return JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: brandName.value,
    url: appUrl.value,
    potentialAction: {
      '@type': 'SearchAction',
      target: `${appUrl.value}/products?q={search_term_string}`,
      'query-input': 'required name=search_term_string',
    },
  })
})

useMultipleJsonLd([organizationSchema, websiteSchema])

watch(
    () => page.props.categories,
    (categories) => {
        categoryStore.hydrate(categories ?? [])
    },
    { immediate: true, deep: true },
)

// --- Search ---
const resolveSearch = () => page.props.query ?? page.props.filters?.q ?? ''
const search = ref(resolveSearch())
const normalizedSearchQuery = computed(() => String(search.value || '').trim())
const recentSuggestionItems = computed(() =>
    recentSearches.value.map((term) => ({
        type: 'recent',
        id: `recent-${term}`,
        label: term,
        meta: t('Recent'),
        href: `/search?q=${encodeURIComponent(term)}`,
    }))
)
const searchSuggestionItems = computed(() => {
    const query = normalizedSearchQuery.value
    if (query.length < 2) return recentSuggestionItems.value

    const categoryItems = (searchSuggestions.value.categories ?? []).map((category) => ({
        type: 'category',
        id: category.id,
        label: category.name,
        meta: t('Category'),
        href: category.href,
    }))

    const productItems = (searchSuggestions.value.products ?? []).map((product) => ({
        type: 'product',
        id: product.id,
        label: product.name,
        meta: product.category || t('Product'),
        href: product.href,
        image: product.image || null,
    }))

    return [
        ...categoryItems,
        ...productItems,
        {
            type: 'view_all',
            id: `view-all-${query}`,
            label: t('View all results for ":query"', {query}),
            meta: t('Search'),
            href: `/search?q=${encodeURIComponent(query)}`,
        },
    ]
})
const isShowingRecentSuggestions = computed(() => normalizedSearchQuery.value.length < 2)
const showNoSuggestionsState = computed(() => {
    if (isShowingRecentSuggestions.value || isFetchingSuggestions.value) {
        return false
    }

    return (searchSuggestions.value.products?.length ?? 0) === 0 && (searchSuggestions.value.categories?.length ?? 0) === 0
})
const showSearchSuggestions = computed(() => {
    const queryLength = normalizedSearchQuery.value.length
    if (!isSearchFocused.value) return false

    if (queryLength < 2) {
        return searchSuggestionItems.value.length > 0
    }

    return isFetchingSuggestions.value || searchSuggestionItems.value.length > 0 || showNoSuggestionsState.value
})

const persistRecentSearches = () => {
    if (typeof window === 'undefined') return
    window.localStorage.setItem(recentSearchStorageKey, JSON.stringify(recentSearches.value))
}

const loadRecentSearches = () => {
    if (typeof window === 'undefined') return
    try {
        const raw = window.localStorage.getItem(recentSearchStorageKey)
        const parsed = JSON.parse(raw || '[]')
        recentSearches.value = Array.isArray(parsed)
            ? parsed
                .map((term) => String(term || '').trim())
                .filter((term) => term.length >= 2)
                .slice(0, maxRecentSearches)
            : []
    } catch {
        recentSearches.value = []
    }
}

const addRecentSearch = (term) => {
    const value = String(term || '').trim()
    if (value.length < 2) return

    recentSearches.value = [
        value,
        ...recentSearches.value.filter((item) => item.toLowerCase() !== value.toLowerCase()),
    ].slice(0, maxRecentSearches)
    persistRecentSearches()
}

const clearRecentSearches = () => {
    recentSearches.value = []
    persistRecentSearches()
}

const fetchSearchSuggestions = async (query) => {
    console.log('fetchSearchSuggestions called for:', query)
    
    // Simple failure tracking to prevent spam
    const failureKey = `search_failure_${query}`
    const recentFailure = localStorage.getItem(failureKey)
    if (recentFailure && (Date.now() - parseInt(recentFailure)) < 5000) {
        console.log('Recent failure detected, skipping request for:', query)
        return
    }
    
    if (searchSuggestionsAbortController) {
        console.log('Aborting previous request')
        searchSuggestionsAbortController.abort()
    }

    const controller = new AbortController()
    searchSuggestionsAbortController = controller
    isFetchingSuggestions.value = true

    try {
        console.log('Making fetch request for:', query)
        const response = await fetch(`/search/suggest?q=${encodeURIComponent(query)}&products_limit=5&categories_limit=4`, {
            headers: {
                Accept: 'application/json',
            },
            signal: controller.signal,
        })

        console.log('Fetch response received:', {
            status: response.status,
            ok: response.ok,
            query: query
        })

        if (!response.ok) {
            if (response.status === 500) {
                console.error('Server error during search suggestions for:', query)
                // Mark failure time to prevent spam
                localStorage.setItem(failureKey, Date.now().toString())
                searchSuggestions.value = {
                    products: [],
                    categories: [],
                    error: 'Server error - please try again'
                }
                return
            }
            throw new Error(`suggestions_request_failed: ${response.status}`)
        }

        // Clear failure on success
        localStorage.removeItem(failureKey)

        const payload = await response.json()
        console.log('Response payload received for:', query, {
            productsCount: payload?.products?.length || 0,
            categoriesCount: payload?.categories?.length || 0
        })
        
        if (controller !== searchSuggestionsAbortController) {
            console.log('Request aborted due to newer request, ignoring response for:', query)
            return
        }

        searchSuggestions.value = {
            products: Array.isArray(payload?.products) ? payload.products : [],
            categories: Array.isArray(payload?.categories) ? payload.categories : [],
        }
        
        console.log('Search suggestions updated successfully:', {
            query,
            products: searchSuggestions.value.products.length,
            categories: searchSuggestions.value.categories.length
        })
    } catch (error) {
        if (error.name === 'AbortError') {
            console.log('Search request aborted for:', query)
            return // Don't treat abort as error
        }
        
        console.error('Search suggestions error for:', query, {
            name: error.name,
            message: error.message,
            aborted: error.name === 'AbortError'
        })
        
        if (error?.name !== 'AbortError') {
            searchSuggestions.value = {products: [], categories: []}
        }
    } finally {
        if (controller === searchSuggestionsAbortController) {
            console.log('Request completed for:', query)
            isFetchingSuggestions.value = false
        }
    }
}

const closeSearchSuggestions = () => {
    isSearchFocused.value = false
    selectedSuggestionIndex.value = -1
}

const handleSearchFocus = () => {
    isSearchFocused.value = true
    const query = String(search.value || '').trim()
    if (query.length >= 2) {
        fetchSearchSuggestions(query)
    }
}

const handleSuggestionNext = () => {
    const total = searchSuggestionItems.value.length
    if (!total) return
    selectedSuggestionIndex.value = (selectedSuggestionIndex.value + 1 + total) % total
}

const handleSuggestionPrev = () => {
    const total = searchSuggestionItems.value.length
    if (!total) return
    selectedSuggestionIndex.value = (selectedSuggestionIndex.value - 1 + total) % total
}

const selectSuggestion = (item) => {
    if (!item?.href) return
    if (item.type === 'recent') {
        search.value = item.label
    }
    addRecentSearch(normalizedSearchQuery.value || item.label)
    closeSearchSuggestions()
    router.get(item.href)
}

const handleSearchEnter = () => {
    const total = searchSuggestionItems.value.length
    if (showSearchSuggestions.value && total > 0 && selectedSuggestionIndex.value >= 0 && selectedSuggestionIndex.value < total) {
        selectSuggestion(searchSuggestionItems.value[selectedSuggestionIndex.value])
        return
    }
    submitSearch()
}

watch(
    () => [page.props.query, page.props.filters?.q],
    () => {
        search.value = resolveSearch()
    }
)

watch(search, (value) => {
    const query = String(value || '').trim()
    selectedSuggestionIndex.value = -1

    console.log('Search input changed:', {
        value,
        query,
        queryLength: query.length,
        currentSuggestions: {
            products: searchSuggestions.value.products.length,
            categories: searchSuggestions.value.categories.length
        },
        isFetching: isFetchingSuggestions.value,
        hasTimer: !!searchSuggestionsTimer
    })

    if (searchSuggestionsTimer) {
        console.log('Clearing previous timer')
        clearTimeout(searchSuggestionsTimer)
        searchSuggestionsTimer = null
    }

    if (query.length < 2) {
        console.log('Query too short, clearing suggestions')
        searchSuggestions.value = {products: [], categories: []}
        isFetchingSuggestions.value = false
        return
    }

    console.log('Setting timer for fetchSearchSuggestions')
    searchSuggestionsTimer = setTimeout(() => {
        console.log('Timer fired, fetching suggestions for:', query)
        fetchSearchSuggestions(query)
    }, 350)
})

const submitSearch = () => {
    const value = String(search.value || '').trim()
    closeSearchSuggestions()
    if (!value) {
        router.get('/products')
        return
    }
    addRecentSearch(value)
    router.get('/search', {q: value}, {preserveState: true, replace: true})
}

const mobileTabs = computed(() => [
    {
        key: 'home',
        href: '/',
        label: t('Home'),
        icon: 'home',
    },
    {
        key: 'categories',
        href: '/products',
        label: t('Categories'),
        icon: 'categories',
    },
    {
        key: 'download',
        href: '/download',
        label: t('Download'),
        icon: 'download',
    },
    {
        key: 'search',
        href: '/search',
        label: t('Search'),
        icon: 'search',
    },
    {
        key: 'cart',
        href: '/cart',
        label: t('Cart'),
        icon: 'cart',
    },
    {
        key: 'account',
        href: authUser.value ? '/account' : route('login'),
        label: t('Account'),
        icon: 'account',
    },
])

const isMobileTabActive = (tab) => {
    const path = currentPath.value || '/'
    if (tab.key === 'home') return path === '/'
    if (tab.key === 'categories') return path.startsWith('/products') || path.startsWith('/categories')
    if (tab.key === 'download') return path.startsWith('/download')
    if (tab.key === 'search') return path.startsWith('/search')
    if (tab.key === 'cart') return path.startsWith('/cart') || path.startsWith('/checkout')
    if (tab.key === 'account') return path.startsWith('/account') || path.startsWith('/orders')
    return false
}

const updateMobileHeaderState = () => {
    if (typeof window === 'undefined') return

    if (!enableMobileHeaderCompact) {
        mobileHeaderCompact.value = false
        return
    }

    const isMobileViewport = window.innerWidth < 1024
    if (!isMobileViewport) {
        mobileHeaderCompact.value = false
        return
    }

    const scrollY = window.scrollY
    const enterThreshold = Math.max(120, Math.round(window.innerHeight * 0.16))
    const exitThreshold = Math.max(56, Math.round(enterThreshold * 0.55))

    if (mobileHeaderCompact.value) {
        mobileHeaderCompact.value = scrollY > exitThreshold
        return
    }

    mobileHeaderCompact.value = scrollY > enterThreshold
}

const scheduleMobileHeaderStateUpdate = () => {
    if (mobileHeaderStateRaf) return
    mobileHeaderStateRaf = window.requestAnimationFrame(() => {
        mobileHeaderStateRaf = null
        updateMobileHeaderState()
    })
}

// --- Header hide on scroll ---
const measureHeaderHeight = () => {
    if (typeof window === 'undefined') return
    const el = headerRef.value
    if (!el) return
    const h = Math.round(el.getBoundingClientRect().height || 0)
    if (h > 0) headerHeight.value = h
}

const updateHeaderVisibility = () => {
    if (typeof window === 'undefined') return
    const y = window.scrollY || 0
    const dy = y - headerLastScrollY

    if (mobileOpen.value || megaMenuOpen.value || accountOpen.value || cartOpen.value || locationOpen.value) {
        headerHidden.value = false
        headerLastScrollY = y
        return
    }

    const hideAfter = Math.max(80, headerHeight.value + 24)

    if (y <= 8) {
        headerHidden.value = false
    } else if (dy > 10 && y > hideAfter) {
        headerHidden.value = true
    } else if (dy < -10) {
        headerHidden.value = false
    }

    headerLastScrollY = y
}

const scheduleHeaderVisibilityUpdate = () => {
    if (typeof window === 'undefined') return
    if (headerVisibilityRaf) return
    headerVisibilityRaf = window.requestAnimationFrame(() => {
        headerVisibilityRaf = null
        updateHeaderVisibility()
    })
}

watch([mobileOpen, megaMenuOpen, accountOpen, cartOpen, locationOpen], (vals) => {
    if (Array.isArray(vals) && vals.some(Boolean)) {
        headerHidden.value = false
    }
})




// --- Router loader ---
onMounted(() => {
    const start = () => {
        isNavigating.value = true
    }
    const finish = () => {
        isNavigating.value = false
    }

    const offStart = router.on('start', start)
    const offFinish = router.on('finish', finish)
    const offError = router.on('error', finish)
    const offInvalid = router.on('invalid', finish)

    onBeforeUnmount(() => {
        offStart();
        offFinish();
        offError();
        offInvalid()
    })
})

// --- Account/Cart dropdowns ---
const toggleAccount = () => {
    accountOpen.value = !accountOpen.value
    if (accountOpen.value) cartOpen.value = false
}
const toggleCart = () => {
    cartOpen.value = !cartOpen.value
    if (cartOpen.value) accountOpen.value = false
}

// --- Locale ---
// Note: setLocale is now provided by useUserPreferences composable

// --- Outside click close ---
const handleDocumentClick = (event) => {
    const target = event.target
    if (accountOpen.value && accountRef.value && !accountRef.value.contains(target)) accountOpen.value = false
    if (cartOpen.value && cartRef.value && !cartRef.value.contains(target)) cartOpen.value = false

    const inDesktopSearch = desktopSearchRef.value && desktopSearchRef.value.contains(target)
    const inMobileSearch = mobileSearchRef.value && mobileSearchRef.value.contains(target)
    if (!inDesktopSearch && !inMobileSearch) {
        closeSearchSuggestions()
    }
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick)
    loadRecentSearches()

    if (!categoryStore.initialized) {
        categoryStore.fetchCategories()
    }

    window.addEventListener('scroll', scheduleHeaderVisibilityUpdate, {passive: true})
    window.addEventListener('resize', measureHeaderHeight)

    nextTick(() => {
        measureHeaderHeight()
        headerLastScrollY = typeof window !== 'undefined' ? (window.scrollY || 0) : 0
        scheduleHeaderVisibilityUpdate()

        if (typeof window !== 'undefined' && 'ResizeObserver' in window && headerRef.value) {
            headerResizeObserver = new ResizeObserver(() => {
                measureHeaderHeight()
            })
            headerResizeObserver.observe(headerRef.value)
        }
    })

    if (enableMobileHeaderCompact) {
        window.addEventListener('scroll', scheduleMobileHeaderStateUpdate, {passive: true})
        window.addEventListener('resize', updateMobileHeaderState)
        requestAnimationFrame(updateMobileHeaderState)
    }
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick)
    window.removeEventListener('scroll', scheduleHeaderVisibilityUpdate)
    window.removeEventListener('resize', measureHeaderHeight)

    if (headerResizeObserver) {
        try {
            headerResizeObserver.disconnect()
        } catch {
            // ignore
        }
        headerResizeObserver = null
    }

    if (headerVisibilityRaf) {
        window.cancelAnimationFrame(headerVisibilityRaf)
        headerVisibilityRaf = null
    }

    if (enableMobileHeaderCompact) {
        window.removeEventListener('scroll', scheduleMobileHeaderStateUpdate)
        window.removeEventListener('resize', updateMobileHeaderState)
    }

    if (mobileHeaderStateRaf) {
        window.cancelAnimationFrame(mobileHeaderStateRaf)
        mobileHeaderStateRaf = null
    }

    if (searchSuggestionsTimer) {
        clearTimeout(searchSuggestionsTimer)
    }
    if (searchSuggestionsAbortController) {
        searchSuggestionsAbortController.abort()
    }
})
</script>

<style scoped>
/* Header hide-on-scroll animation */
.storefront-header {
    will-change: transform;
    transform: translateY(0);
    transition: transform 220ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.storefront-header--hidden {
    transform: translateY(calc(-100% - 10px));
    pointer-events: none;
}
.storefront-header-spacer {
    transition: height 220ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
@media (prefers-reduced-motion: reduce) {
    .storefront-header,
    .storefront-header-spacer {
        transition: none;
    }
}

.brand-theme {
    --brand-primary: #f59e0b;
    --brand-primary-2: #2563eb;
    --brand-accent: #9ca3af;
    --brand-strong: #0f172a;
    --brand-bg: #ffffff;
    --brand-glow-start: #f59e0b;
    --brand-glow-end: #2563eb;
    --brand-soft: color-mix(in srgb, var(--brand-primary) 12%, white);
}

/* Hide horizontal scrollbar cross-browser (keeps scrolling) */
.hide-scrollbar {
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/old Edge */
}

.hide-scrollbar::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
}

.bg-brand-glow {
    background: linear-gradient(
        90deg,
        rgba(240, 236, 214, 1) 0%,
        rgba(246, 225, 109, 1) 50%,
        rgba(245, 149, 15, 1) 100%
    );
}

.text-brand-strong {
    color: var(--brand-strong);
}

.border-brand {
    border-color: var(--brand-primary);
}

.bg-brand-soft {
    background-color: var(--brand-soft);
}

.hover\:border-brand:hover {
    border-color: var(--brand-primary);
}

.hover\:border-brand-strong:hover {
    border-color: var(--brand-strong);
}

.mobile-app-nav {
    padding-bottom: max(0.45rem, env(safe-area-inset-bottom));
}

.mobile-app-tab {
    display: inline-flex;
    min-width: 4.2rem;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.2rem;
    border-radius: 0.9rem;
    padding: 0.5rem 0.35rem;
    transition: all 150ms ease;
}

.mobile-app-tab-idle {
    color: rgb(100 116 139);
}

.mobile-app-tab-active {
    color: rgb(15 23 42);
    background: linear-gradient(180deg, rgba(245, 158, 11, 0.18) 0%, rgba(245, 158, 11, 0.08) 100%);
}

.mobile-search-wrap,
.mobile-categories-wrap {
    overflow: hidden;
    transition: max-height 200ms ease, opacity 180ms ease, transform 200ms ease, padding 200ms ease;
}

.mobile-search-wrap {
    max-height: 5.5rem;
}

.mobile-categories-wrap {
    max-height: 6.5rem;
}

.mobile-search-wrap-collapsed {
    max-height: 0;
    min-height: 0;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    overflow: hidden;
    transform: translateY(-6px);
    padding-top: 0;
    padding-bottom: 0;
    border-top-width: 0;
}

.mobile-categories-wrap-collapsed {
    max-height: 0;
    min-height: 0;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    overflow: hidden;
    transform: translateY(-8px);
    padding-top: 0;
    padding-bottom: 0;
    border-top-width: 0;
}

/* .nav-link utility classes should be used directly in the template. */

/* Header gradient background */
.bg-gradient-header {
    background: linear-gradient(90deg, rgba(240, 236, 214, 1) 0%, rgba(246, 225, 109, 1) 50%, rgba(245, 149, 15, 1) 100%);
}
</style>
