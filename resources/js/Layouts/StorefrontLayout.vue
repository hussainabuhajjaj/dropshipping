<template>
    <div class="brand-theme min-h-screen text-slate-900" :style="themeStyle">
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

                <p class="text-sm text-slate-600 dark:text-slate-300">Loading...</p>
            </div>
        </Transition>

        <!-- SEO Head -->
        <Head :title="seoTitle">
            <meta name="description" head-key="description" :content="seoDescription"/>
            <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl"/>

            <meta property="og:title" :content="seoTitle"/>
            <meta property="og:description" :content="seoDescription"/>
            <meta property="og:type" content="website"/>
            <meta v-if="canonicalUrl" property="og:url" :content="canonicalUrl"/>
            <meta v-if="seoImage" property="og:image" :content="seoImage"/>

            <meta name="twitter:card" content="summary_large_image"/>
            <meta name="twitter:title" :content="seoTitle"/>
            <meta name="twitter:description" :content="seoDescription"/>
            <meta v-if="seoImage" name="twitter:image" :content="seoImage"/>
        </Head>

        <!-- Marketplace Header -->
        <header class="sticky top-0 z-50 shadow-md"
                style="background:linear-gradient(90deg,rgba(240,236,214,1) 0%,rgba(246,225,109,1) 50%,rgba(245,149,15,1) 100%);">
            <!-- Top row -->
            <div class="container mx-auto px-4">
                <div class="flex items-center gap-3 py-3">
                    <!-- Mobile Menu Toggle -->
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center text-white transition hover:text-[#f59e0b] lg:hidden"
                        @click="mobileOpen = true"
                    >
                        <span class="sr-only">{{ t('Open menu') }}</span>
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <!-- Brand Logo -->
                    <Link href="/" class="flex items-center gap-2">
                        <img v-if="logoUrl" :src="logoUrl" :alt="brandName" class="h-10 w-auto"/>
                        <span v-else class="text-xl font-bold text-white">{{ brandName }}</span>
                    </Link>

                    <!-- Location Selector (desktop) -->
                    <button
                        type="button"
                        class="hidden items-center gap-2 rounded-lg px-3 py-2 text-sm text-white transition hover:bg-slate-800 lg:flex"
                        @click="locationOpen = !locationOpen"
                    >
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"
                            />
                        </svg>
                        <div class="text-left leading-tight">
                            <div class="text-xs text-slate-400">{{ t('Deliver to') }}</div>
                            <div class="font-semibold">{{ selectedLocation }}</div>
                        </div>
                        <svg viewBox="0 0 20 20" class="h-4 w-4" fill="currentColor">
                            <path
                                fill-rule="evenodd"
                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>

                    <!-- Large Search Bar (desktop) -->
                    <form class="hidden flex-1 items-center lg:flex" @submit.prevent="submitSearch">
                        <div class="relative mx-auto w-full max-w-3xl">
                            <input
                                v-model="search"
                                type="search"
                                :placeholder="t('What are you looking for?')"
                                class="w-full rounded-lg border-2 border-slate-600 bg-white px-5 py-3 pl-12 text-sm text-slate-900 placeholder-slate-500 focus:border-[#f59e0b] focus:outline-none focus:ring-2 focus:ring-[#f59e0b]/20"
                                :aria-label="t('Search products')"
                            />
                            <svg
                                viewBox="0 0 24 24"
                                class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z"/>
                            </svg>

                            <button
                                type="submit"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md bg-[#f59e0b] px-6 py-2 text-sm font-semibold text-white transition hover:bg-[#d97706]"
                            >
                                {{ t('Search') }}
                            </button>
                        </div>
                    </form>

                    <!-- Right Side Icons -->
                    <div class="ml-auto flex items-center gap-3">
                        <!-- Language Toggle (desktop) -->
                        <div class="hidden items-center gap-1 lg:flex">
                            <button
                                v-for="option in localeOptions"
                                :key="option.code"
                                type="button"
                                class="rounded px-2 py-1 text-xs font-semibold uppercase transition"
                                :class="option.code === locale ? 'bg-[#f59e0b] text-white' : 'text-slate-300 hover:text-white'"
                                :title="option.label"
                                @click="setLocale(option.code)"
                            >
                                {{ option.code }}
                            </button>
                        </div>

                        <!-- Currency selector (desktop) -->
                        <select
                            v-model="selectedCurrency"
                            @change="onCurrencyChange"
                            class="hidden rounded border border-slate-600 bg-slate-800 px-2 py-1 text-xs text-white focus:border-[#f59e0b] focus:outline-none lg:block"
                        >
                            <option v-for="currency in currencyOptions" :key="currency" :value="currency">
                                {{ currency }}
                            </option>
                        </select>

                        <!-- Wishlist -->
                        <Link
                            href="/account/wishlist"
                            class="relative inline-flex h-10 w-10 items-center justify-center text-white transition hover:text-[#f59e0b]"
                            aria-label="Wishlist"
                        >
                            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"
                                />
                            </svg>
                            <span
                                v-if="wishlistCount"
                                class="absolute -right-1 -top-1 inline-flex min-w-[1.1rem] items-center justify-center rounded-full bg-[#f59e0b] px-1.5 text-[0.6rem] font-semibold text-white"
                            >
                {{ wishlistCount }}
              </span>
                        </Link>

                        <!-- Account -->
                        <div ref="accountRef" class="relative z-[100]">
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center text-white transition hover:text-[#f59e0b]"
                                aria-label="Account"
                                :aria-expanded="accountOpen"
                                @click.stop="toggleAccount"
                            >
                                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor"
                                     stroke-width="2">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                                    />
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
                                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400">{{
                                                t('Signed in')
                                            }}</p>
                                        <p class="font-semibold text-slate-900">{{ authUser.name }}</p>
                                        <p class="text-xs text-slate-500">{{ authUser.email }}</p>
                                    </div>

                                    <div class="mt-3 grid gap-2">
                                        <Link v-if="authUser" :href="route('account.index')"
                                              class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                            {{ t('Account overview') }}
                                        </Link>
                                        <Link v-if="authUser" href="/account/notifications"
                                              class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                      <span class="flex items-center justify-between">
                        <span>{{ t('Notifications') }}</span>
                        <span v-if="unreadNotifications"
                              class="ml-2 rounded-full bg-rose-600 px-2 py-0.5 text-[0.6rem] font-semibold text-white">
                          {{ unreadNotifications }}
                        </span>
                      </span>
                                        </Link>
                                        <Link v-if="authUser" href="/orders"
                                              class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                            {{ t('My orders') }}
                                        </Link>
                                        <Link v-if="authUser" href="/account/addresses"
                                              class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                            {{ t('Addresses') }}
                                        </Link>
                                        <Link v-if="authUser" href="/account/payments"
                                              class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                            {{ t('Payments') }}
                                        </Link>
                                        <Link v-if="authUser" href="/account/wallet"
                                              class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                            {{ t('Wallet') }}
                                        </Link>
                                        <Link v-if="authUser" href="/account/refunds"
                                              class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                            {{ t('Refunds') }}
                                        </Link>
                                        <Link v-if="authUser" href="/account/wishlist"
                                              class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                      <span class="flex items-center justify-between">
                        <span>{{ t('Wishlist') }}</span>
                        <span v-if="wishlistCount"
                              class="ml-2 rounded-full bg-slate-900 px-2 py-0.5 text-[0.6rem] font-semibold text-white">
                          {{ wishlistCount }}
                        </span>
                      </span>
                                        </Link>

                                        <Link v-if="!authUser" :href="route('login')"
                                              class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                            {{ t('Sign in') }}
                                        </Link>
                                        <Link v-if="!authUser" :href="route('register')"
                                              class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                            {{ t('Create account') }}
                                        </Link>

                                        <Link
                                            v-if="authUser"
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                            class="rounded-lg px-3 py-2 text-left text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                                        >
                                            {{ t('Sign out') }}
                                        </Link>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <!-- Cart -->
                        <div ref="cartRef" class="relative z-[100]">
                            <button
                                type="button"
                                class="relative inline-flex h-10 w-10 items-center justify-center text-white transition hover:text-[#f59e0b]"
                                aria-label="Cart"
                                :aria-expanded="cartOpen"
                                @click.stop="toggleCart"
                            >
                                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor"
                                     stroke-width="2">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"
                                    />
                                </svg>

                                <span
                                    v-if="cartCount"
                                    class="absolute -right-1 -top-1 inline-flex min-w-[1.1rem] items-center justify-center rounded-full bg-[#f59e0b] px-1.5 text-[0.6rem] font-semibold text-white"
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
                                    class="absolute right-0 top-full mt-2 w-72 rounded-2xl border border-slate-200 bg-white p-4 text-sm shadow-lg"
                                >
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ t('Cart') }}</p>

                                    <div v-if="cartLines.length" class="mt-3 space-y-3">
                                        <div v-for="line in cartLines" :key="line.id" class="flex items-center gap-3">
                                            <div
                                                class="h-12 w-12 overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                                <img v-if="line.media?.[0]" :src="line.media[0]" :alt="line.name"
                                                     class="h-full w-full object-cover"/>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-xs font-semibold text-slate-900">{{ line.name }}</p>
                                                <p class="text-[0.7rem] text-slate-500">
                                                    {{ line.variant || t('Standard') }} ·
                                                    {{ t('Qty :quantity', {quantity: line.quantity}) }}
                                                </p>
                                            </div>
                                            <div class="text-xs font-semibold text-slate-800">
                                                {{ line.currency }} {{ Number(line.price).toFixed(2) }}
                                            </div>
                                        </div>

                                        <div
                                            class="flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-600">
                                            <span>{{ t('Subtotal') }}</span>
                                            <span class="font-semibold text-slate-900">{{
                                                    cartCurrency
                                                }} {{ cartSubtotal.toFixed(2) }}</span>
                                        </div>

                                        <div class="grid gap-2">
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
            <div class="border-t border-slate-700/50 px-4 py-3 lg:hidden">
                <form class="flex items-center gap-2" @submit.prevent="submitSearch">
                    <div class="relative w-full">
                        <input
                            v-model="search"
                            type="search"
                            :placeholder="t('What are you looking for?')"
                            class="w-full rounded-lg border-2 border-slate-600 bg-white px-4 py-2 pl-10 text-sm text-slate-900 placeholder-slate-500 focus:border-[#f59e0b] focus:outline-none"
                            :aria-label="t('Search products')"
                        />
                        <svg
                            viewBox="0 0 24 24"
                            class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z"/>
                        </svg>
                    </div>

                    <button type="submit"
                            class="rounded-md bg-[#f59e0b] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#d97706]">
                        {{ t('Go') }}
                    </button>
                </form>
            </div>

            <!-- Categories Navigation Row (scrollbar hidden + arrows) -->
            <div class="relative"
                 style="background:linear-gradient(90deg,rgba(240,236,214,1) 0%,rgba(246,225,109,1) 50%,rgba(245,149,15,1) 100%);">
                <div class="container mx-auto px-4">
                    <div class="relative py-3">
                        <!-- Left arrow -->
                        <button
                            v-show="canScrollLeft"
                            type="button"
                            class="absolute left-0 top-1/2 z-10 -translate-y-1/2 rounded-full bg-slate-900/70 p-2 text-dark shadow hover:text-[#f59e0b]"
                            @click="scrollCategories('left')"
                            aria-label="Scroll left"
                        >
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>

                        <!-- Scroll container -->
                        <div
                            ref="categoriesScrollRef"
                            class="hide-scrollbar flex items-center gap-2 overflow-x-auto px-10"
                            @scroll.passive="updateScrollArrows"
                        >
                            <Link
                                v-for="category in rootCategories"
                                :key="category.slug || category.name"
                                :href="categoryHref(category)"
                                class="group relative whitespace-nowrap px-4 py-2 text-sm font-semibold text-black transition hover:text-[#f59e0b]"
                                @mouseenter="openMegaMenu(category)"
                                @mouseleave="scheduleMegaMenuClose"
                            >
                                {{ category.name }}
                                <span
                                    class="absolute bottom-0 left-0 h-0.5 w-0 bg-[#f59e0b] transition-all group-hover:w-full"></span>
                            </Link>
                        </div>

                        <!-- Right arrow -->
                        <button
                            v-show="canScrollRight"
                            type="button"
                            class="absolute right-0 top-1/2 z-10 -translate-y-1/2 rounded-full bg-slate-900/70 p-2 text-white shadow hover:text-[#f59e0b]"
                            @click="scrollCategories('right')"
                            aria-label="Scroll right"
                        >
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Mega Menu Dropdown -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div
                        v-if="megaMenuOpen && selectedCategory"
                        class="absolute left-0 right-0 top-full z-40 border-t border-slate-200 bg-white/75 shadow-2xl backdrop-blur-xl supports-[backdrop-filter]:bg-white/60 relative"
                        @mouseenter="cancelMegaMenuClose"
                        @mouseleave="scheduleMegaMenuClose"
                    >
                        <div aria-hidden="true" class="pointer-events-none absolute inset-0 z-0 overflow-hidden">
                            <div class="absolute -top-28 left-[-7rem] h-80 w-80 rounded-full bg-[var(--brand-primary)] opacity-20 blur-3xl"/>
                            <div class="absolute -top-32 right-[-7rem] h-80 w-80 rounded-full bg-[var(--brand-primary-2)] opacity-15 blur-3xl"/>
                            <div class="absolute -bottom-40 left-1/3 h-96 w-96 rounded-full bg-[var(--brand-primary)] opacity-10 blur-3xl"/>
                        </div>

                        <div class="container mx-auto px-4 py-8 relative z-10">
                            <div class="grid grid-cols-1 gap-8 lg:grid-cols-5">
                                <!-- 4 Columns of Links -->
                                <div v-for="(section, idx) in selectedCategory.sections" :key="'section-' + idx"
                                      class="space-y-3">
                                    <h3 class="text-sm font-bold uppercase tracking-wider text-[#0f172a]">
                                        {{ section.title }}
                                    </h3>
                                    <ul class="space-y-2">
                                        <li v-for="item in section.items" :key="item">
                                            <Link
                                                :href="`/products?category=${encodeURIComponent(selectedCategory.slug || selectedCategory.name)}&subcategory=${encodeURIComponent(item)}`"
                                                class="block text-sm text-slate-600 transition hover:text-[#2563eb]"
                                            >
                                                {{ item }}
                                            </Link>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Subcategories -->
                                <div v-if="selectedCategory.children && selectedCategory.children.length"
                                     class="space-y-4 lg:col-span-4 lg:max-h-[60vh] lg:overflow-y-auto lg:pr-3">
                                    <ul class="grid grid-cols-2 gap-4">
                                        <li v-for="child in selectedCategory.children" :key="child.slug || child.name"
                                            class="rounded-xl border border-slate-200 bg-slate-50/60 p-3 transition hover:bg-white">
                                            <Link
                                                :href="categoryHref(child)"
                                                class="flex items-center gap-3"
                                            >
                                                <img
                                                    v-if="child.image"
                                                    :src="child.image"
                                                    alt=""
                                                    class="h-8 w-8 shrink-0 rounded-lg border border-slate-200 bg-white object-cover"
                                                />
                                                <img
                                                    v-else
                                                    src="/images/category-default.png"
                                                    alt=""
                                                    class="h-8 w-8 shrink-0 rounded-lg border border-slate-200 bg-white object-cover"
                                                />
                                                <span
                                                    class="min-w-0 truncate text-sm font-semibold text-slate-800 transition hover:text-[#2563eb]"
                                                >
                                                    {{ child.name }}
                                                </span>
                                            </Link>

                                            <ul
                                                v-if="child.children && child.children.length"
                                                class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 border-t border-slate-200 pt-3"
                                            >
                                                <li
                                                    v-for="grandChild in child.children"
                                                    :key="grandChild.slug || grandChild.name"
                                                >
                                                    <Link
                                                        :href="categoryHref(grandChild)"
                                                        class="block text-xs leading-5 text-slate-600 transition hover:text-[#2563eb]"
                                                    >
                                                        {{ grandChild.name }}
                                                    </Link>
                                                </li>
                                            </ul>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Promo Block -->
                                <div
                                    v-if="selectedCategory.promo"
                                    class="relative overflow-hidden rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 lg:col-span-1"
                                >
                                    <img v-if="selectedCategory.promo.image" :src="selectedCategory.promo.image"
                                         alt="Featured" class="h-full w-full object-cover"/>
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                        <h4 class="text-center text-lg font-bold text-white">
                                            {{ selectedCategory.promo.title }}
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
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

        <main class="container-base pb-16 pt-10">
            <slot/>
        </main>

        <footer class="border-t border-slate-200 bg-white/90">
            <div class="container-base grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-5">
                <div class="space-y-3">
                    <p class="text-lg font-semibold text-slate-900">{{ brandName }}</p>
                    <p class="text-sm text-slate-600">{{ footerBlurb }}</p>
                    <div class="text-xs text-slate-500">
                        {{ t('Support: :email', {email: supportEmail}) }}
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
                class="fixed inset-y-0 left-0 z-[70] w-[85%] max-w-xs overflow-y-auto border-r border-slate-200 bg-white p-5"
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

                <div class="mt-6 space-y-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{
                                t('Categories')
                            }}</p>
                        <div class="mt-3 space-y-4">
                            <div v-for="category in categories" :key="category.slug || category.name" class="space-y-2">
                                <Link
                                    :href="categoryHref(category)"
                                    class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-semibold text-slate-800"
                                    @click="mobileOpen = false"
                                >
                  <span
                      class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500"
                  >
                    {{ category.short }}
                  </span>
                                    {{ category.name }}
                                </Link>

                                <div v-if="category.children?.length" class="space-y-2 pl-8 text-xs text-slate-600">
                                    <div v-for="child in category.children" :key="child.slug || child.name" class="space-y-1">
                                        <Link
                                            :href="categoryHref(child)"
                                            class="block rounded-lg text-xs font-semibold text-slate-600 transition hover:text-slate-900"
                                            @click="mobileOpen = false"
                                        >
                                            {{ child.name }}
                                        </Link>

                                        <div v-if="child.children?.length" class="space-y-1 pl-4">
                                            <Link
                                                v-for="grandChild in child.children"
                                                :key="grandChild.slug || grandChild.name"
                                                :href="categoryHref(grandChild)"
                                                class="block rounded-lg text-[0.7rem] font-medium text-slate-500 transition hover:text-slate-900"
                                                @click="mobileOpen = false"
                                            >
                                                {{ grandChild.name }}
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 text-sm text-slate-600">
                        <Link
                            v-for="link in headerLinks"
                            :key="link.href + link.label + '-mobile'"
                            :href="link.href"
                            class="block hover:text-slate-900"
                            @click="mobileOpen = false"
                        >
                            {{ link.label }}
                        </Link>
                        <Link href="/cart" class="block hover:text-slate-900" @click="mobileOpen = false">{{
                                t('Cart')
                            }}
                        </Link>
                    </div>

                    <div class="space-y-2 text-sm text-slate-600">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{
                                t('Account')
                            }}</p>

                        <template v-if="authUser">
                            <Link href="/account" class="block hover:text-slate-900" @click="mobileOpen = false">
                                {{ t('Overview') }}
                            </Link>
                            <Link href="/account/notifications" class="block hover:text-slate-900"
                                  @click="mobileOpen = false">
                <span class="inline-flex items-center gap-2">
                  {{ t('Notifications') }}
                  <span v-if="unreadNotifications"
                        class="rounded-full bg-rose-600 px-2 py-0.5 text-[0.6rem] font-semibold text-white">
                    {{ unreadNotifications }}
                  </span>
                </span>
                            </Link>
                            <Link href="/orders" class="block hover:text-slate-900" @click="mobileOpen = false">
                                {{ t('Orders') }}
                            </Link>
                            <Link href="/account/addresses" class="block hover:text-slate-900"
                                  @click="mobileOpen = false">{{ t('Addresses') }}
                            </Link>
                            <Link href="/account/payments" class="block hover:text-slate-900"
                                  @click="mobileOpen = false">{{ t('Payments') }}
                            </Link>
                            <Link href="/account/refunds" class="block hover:text-slate-900"
                                  @click="mobileOpen = false">{{ t('Refunds') }}
                            </Link>
                            <Link href="/account/wishlist" class="block hover:text-slate-900"
                                  @click="mobileOpen = false">
                <span class="inline-flex items-center gap-2">
                  {{ t('Wishlist') }}
                  <span v-if="wishlistCount"
                        class="rounded-full bg-slate-900 px-2 py-0.5 text-[0.6rem] font-semibold text-white">
                    {{ wishlistCount }}
                  </span>
                </span>
                            </Link>
                            <Link href="/account/wallet" class="block hover:text-slate-900" @click="mobileOpen = false">
                                {{ t('Wallet') }}
                            </Link>
                        </template>

                        <template v-else>
                            <Link :href="route('login')" class="block hover:text-slate-900" @click="mobileOpen = false">
                                {{ t('Sign in') }}
                            </Link>
                            <Link :href="route('register')" class="block hover:text-slate-900"
                                  @click="mobileOpen = false">{{ t('Create account') }}
                            </Link>
                        </template>
                    </div>

                    <div class="space-y-2 text-sm text-slate-600">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{
                                t('Language')
                            }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="option in localeOptions"
                                :key="option.code"
                                type="button"
                                class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold uppercase text-slate-700 transition"
                                :class="option.code === locale ? 'bg-slate-900 text-white' : 'hover:border-slate-300'"
                                @click="setLocale(option.code)"
                            >
                                {{ option.code }}
                            </button>
                        </div>

                        <div class="mt-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{
                                    t('Currency')
                                }}</p>
                            <select
                                v-model="selectedCurrency"
                                @change="onCurrencyChange"
                                class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#f59e0b] focus:outline-none"
                            >
                                <option v-for="currency in currencyOptions" :key="currency" :value="currency">
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
    </div>
</template>

<script setup>

// ,
import {computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import {Head, Link, router, usePage} from '@inertiajs/vue3'
import {DotLottieVue} from '@lottiefiles/dotlottie-vue'
import {useTranslations} from '@/i18n'
import {usePersistentCart} from '@/composables/usePersistentCart.js'
import PopupBannerModal from '@/Components/PopupBannerModal.vue'
import NewsletterPopup from '@/Components/NewsletterPopup.vue'

import { toastAlert } from "@/utils/toast";



/** Persistent cart (kept here, remove if unused) */
const {cart: persistentCart, setCart, addLine, updateLine, removeLine, clearCart} = usePersistentCart()

// --- Multi-currency support ---
const currencyOptions = ['USD', 'XOF']
const CURRENCY_KEY = 'dropshipping_currency'
const selectedCurrency = ref(localStorage.getItem(CURRENCY_KEY) || 'USD')

function onCurrencyChange() {
    localStorage.setItem(CURRENCY_KEY, selectedCurrency.value)
}

// --- App / page ---
const page = usePage()
const {t, locale, availableLocales} = useTranslations()

// --- UI state ---
const mobileOpen = ref(false)
const accountOpen = ref(false)
const cartOpen = ref(false)
const accountRef = ref(null)
const cartRef = ref(null)
const isNavigating = ref(false)

const selectedLocation = ref('Abidjan')
const locationOpen = ref(false)

const megaMenuOpen = ref(false)
const selectedCategory = ref(null)
const megaMenuCloseTimer = ref(null)

// --- Categories scroller (hide scrollbar + arrows) ---
const categoriesScrollRef = ref(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)

const updateScrollArrows = () => {
    const el = categoriesScrollRef.value
    if (!el) return
    const maxScrollLeft = el.scrollWidth - el.clientWidth
    canScrollLeft.value = el.scrollLeft > 2
    canScrollRight.value = el.scrollLeft < maxScrollLeft - 2
}

const scrollCategories = (dir) => {
    const el = categoriesScrollRef.value
    if (!el) return
    const amount = Math.round(el.clientWidth * 0.75)
    el.scrollBy({left: dir === 'left' ? -amount : amount, behavior: 'smooth'})
    window.setTimeout(updateScrollArrows, 200)
}

// --- Auth / storefront / cart ---
const authUser = computed(() => page.props.auth?.user ?? null)
const storefront = computed(() => page.props.storefront ?? {})
const cartSummary = computed(() => page.props.cart ?? {lines: [], count: 0, subtotal: 0})

const cartLines = computed(() => cartSummary.value.lines ?? [])
const cartCount = computed(() => cartSummary.value.count ?? 0)
const cartSubtotal = computed(() => Number(cartSummary.value.subtotal ?? 0))
const cartCurrency = computed(() => cartLines.value[0]?.currency ?? 'USD')

const wishlistCount = computed(() => Number(page.props.wishlist?.count ?? 0))
const unreadNotifications = computed(() => {
    const prop = page.props.notifications
    if (prop && !Array.isArray(prop) && typeof prop === 'object' && 'unreadCount' in prop) {
        return Number(prop.unreadCount ?? 0)
    }
    return Number(page.props.unreadCount ?? 0)
})

const localeOptions = computed(() => {
    const entries = Object.entries(availableLocales.value ?? {})
    return entries.map(([code, label]) => ({code, label}))
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
    const configured = page.props.appUrl
    if (configured) return String(configured).replace(/\/$/, '')
    if (typeof window !== 'undefined') return window.location.origin
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

// --- Categories (mega menu needs sections/promo) ---
const fallbackCategories = computed(() => ([
    {
        name: t('Electronics'),
        slug: 'electronics',
        sections: [
            {title: t('Mobile Phones'), items: [t('Smartphones'), t('Feature Phones'), t('Accessories')]},
            {title: t('Computers'), items: [t('Laptops'), t('Desktops'), t('Tablets')]},
            {title: t('Audio'), items: [t('Headphones'), t('Speakers'), t('Home Theater')]},
            {title: t('Cameras'), items: [t('DSLR'), t('Action Cameras'), t('Accessories')]},
        ],
        promo: {title: t('LATEST TECH'), image: '/placeholder-tech.jpg'},
        children: [],
    },
    {
        name: t('Fashion'),
        slug: 'fashion',
        sections: [
            {title: t("Men's Fashion"), items: [t('Shirts'), t('Pants'), t('Shoes'), t('Accessories')]},
            {title: t("Women's Fashion"), items: [t('Dresses'), t('Tops'), t('Shoes'), t('Bags')]},
            {title: t("Kids' Fashion"), items: [t('Boys'), t('Girls'), t('Baby')]},
            {title: t('Sports'), items: [t('Activewear'), t('Sneakers')]},
        ],
        promo: {title: t('TRENDING NOW'), image: '/placeholder-fashion.jpg'},
        children: [],
    },
    {
        name: t('Home & Kitchen'),
        slug: 'home-kitchen',
        sections: [
            {title: t('Furniture'), items: [t('Living Room'), t('Bedroom'), t('Office')]},
            {title: t('Appliances'), items: [t('Kitchen'), t('Cleaning'), t('Cooling')]},
            {title: t('Decor'), items: [t('Lighting'), t('Textiles'), t('Wall Art')]},
            {title: t('Kitchen'), items: [t('Cookware'), t('Utensils')]},
        ],
        promo: {title: t('HOME ESSENTIALS'), image: '/placeholder-home.jpg'},
        children: [],
    },
    {
        name: t('Beauty & Health'),
        slug: 'beauty-health',
        sections: [
            {title: t('Skincare'), items: [t('Face Care'), t('Body Care'), t('Sun Care')]},
            {title: t('Makeup'), items: [t('Face'), t('Eyes'), t('Lips')]},
            {title: t('Hair Care'), items: [t('Shampoo'), t('Styling'), t('Treatment')]},
            {title: t('Health'), items: [t('Vitamins'), t('Personal Care')]},
        ],
        promo: {title: t('BEAUTY PICKS'), image: '/placeholder-beauty.jpg'},
        children: [],
    },
    {
        name: t('Sports & Outdoor'),
        slug: 'sports-outdoor',
        sections: [
            {title: t('Exercise'), items: [t('Fitness Equipment'), t('Yoga'), t('Cardio')]},
            {title: t('Outdoor'), items: [t('Camping'), t('Hiking'), t('Cycling')]},
            {title: t('Sports'), items: [t('Football'), t('Basketball'), t('Swimming')]},
            {title: t('Activewear'), items: [t('Clothing'), t('Shoes')]},
        ],
        promo: {title: t('GET ACTIVE'), image: '/placeholder-sports.jpg'},
        children: [],
    },
    {
        name: t('Baby & Kids'),
        slug: 'baby-kids',
        sections: [
            {title: t('Baby Care'), items: [t('Diapers'), t('Feeding'), t('Bath')]},
            {title: t('Toys'), items: [t('Educational'), t('Action Figures'), t('Dolls')]},
            {title: t('Kids Fashion'), items: [t('Boys'), t('Girls'), t('Shoes')]},
            {title: t('Nursery'), items: [t('Furniture'), t('Decor')]},
        ],
        promo: {title: t('FOR LITTLE ONES'), image: '/placeholder-kids.jpg'},
        children: [],
    },
]))

const makeShort = (name) => {
    const initials = String(name || '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase()
    return initials || String(name || '').slice(0, 2).toUpperCase()
}

const normalizeCategory = (entry) => {
    const name = entry?.name ?? String(entry || '')
    return {
        name,
        slug: entry?.slug ?? null,
        short: entry?.short ?? makeShort(name),
        children: Array.isArray(entry?.children) ? entry.children.map(normalizeCategory) : [],
        sections: Array.isArray(entry?.sections) ? entry.sections : [],
        promo: entry?.promo ?? null,
    }
}

const categories = computed(() => {
    const source =
        Array.isArray(page.props.categories) && page.props.categories.length
            ? page.props.categories
            : fallbackCategories.value
    return source.map((entry) => normalizeCategory(entry))
})

const rootCategories = computed(() => categories.value)

// --- Search ---
const resolveSearch = () => page.props.query ?? page.props.filters?.q ?? ''
const search = ref(resolveSearch())
watch(
    () => [page.props.query, page.props.filters?.q],
    () => {
        search.value = resolveSearch()
    }
)

const submitSearch = () => {
    const value = String(search.value || '').trim()
    if (!value) {
        router.get('/products')
        return
    }
    router.get('/search', {q: value}, {preserveState: true, replace: true})
}



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

// --- Mega menu ---
const openMegaMenu = (category) => {
    if (megaMenuCloseTimer.value) {
        clearTimeout(megaMenuCloseTimer.value)
        megaMenuCloseTimer.value = null
    }
    selectedCategory.value = category
    megaMenuOpen.value = true
}

const scheduleMegaMenuClose = () => {
    if (megaMenuCloseTimer.value) clearTimeout(megaMenuCloseTimer.value)
    megaMenuCloseTimer.value = setTimeout(() => {
        megaMenuOpen.value = false
        selectedCategory.value = null
    }, 200)
}

const cancelMegaMenuClose = () => {
    if (megaMenuCloseTimer.value) {
        clearTimeout(megaMenuCloseTimer.value)
        megaMenuCloseTimer.value = null
    }
}

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
const setLocale = (target) => {
    if (!target || target === locale.value) return
    router.get(`/locale/${target}`, {}, {preserveScroll: true})
}

// --- Outside click close ---
const handleDocumentClick = (event) => {
    const target = event.target
    if (accountOpen.value && accountRef.value && !accountRef.value.contains(target)) accountOpen.value = false
    if (cartOpen.value && cartRef.value && !cartRef.value.contains(target)) cartOpen.value = false
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick)
    requestAnimationFrame(updateScrollArrows)
    window.addEventListener('resize', updateScrollArrows)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick)
    window.removeEventListener('resize', updateScrollArrows)
})

// update arrows when categories change (async load)
watch(rootCategories, () => {
    requestAnimationFrame(updateScrollArrows)
})

// --- URL building ---
const categoryHref = (category) => {
    if (category?.slug) return `/categories/${encodeURIComponent(category.slug)}`
    return `/products?category=${encodeURIComponent(category?.name ?? '')}`
}
</script>

<style scoped>
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

/* .nav-link utility classes should be used directly in the template. */
</style>
/* Header gradient background */
.bg-gradient-header {
background: linear-gradient(90deg,rgba(240, 236, 214, 1) 0%, rgba(246, 225, 109, 1) 50%, rgba(245, 149, 15, 1) 100%);
}
