<template>
  <div class="brand-theme min-h-screen text-slate-900" :style="themeStyle">
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
          <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <img v-if="logoUrl" :src="logoUrl" :alt="brandName" class="h-12 w-12 object-contain" />
            <span v-else class="text-lg font-semibold text-slate-800 dark:text-slate-100">{{ brandName }}</span>
          </div>
          <div class="text-xl font-semibold text-slate-900 dark:text-white">{{ brandName }}</div>
        </div>
        <DotLottieVue
          style="height: 240px; width: 240px"
          autoplay
          loop
          src="/lottie/loader.json"
        />
        <p class="text-sm text-slate-600 dark:text-slate-300">Loading...</p>
      </div>
    </Transition>
    <Head :title="seoTitle">
      <meta name="description" head-key="description" :content="seoDescription" />
      <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />
      <meta property="og:title" :content="seoTitle" />
      <meta property="og:description" :content="seoDescription" />
      <meta property="og:type" content="website" />
      <meta v-if="canonicalUrl" property="og:url" :content="canonicalUrl" />
      <meta v-if="seoImage" property="og:image" :content="seoImage" />
      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" :content="seoTitle" />
      <meta name="twitter:description" :content="seoDescription" />
      <meta v-if="seoImage" name="twitter:image" :content="seoImage" />
    </Head>
    <header class="sticky top-0 z-50 border-b border-slate-200/70 bg-brand-glow backdrop-blur">
      <div class="container-base">
        <div class="flex items-center gap-3 py-3 sm:py-4">
          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:border-brand lg:hidden"
            @click="mobileOpen = true"
          >
            <span class="sr-only">{{ t('Open menu') }}</span>
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h10" />
            </svg>
          </button>

          <Link href="/" class="flex items-center gap-2 text-lg font-semibold tracking-tight text-brand-strong">
            <img v-if="logoUrl" :src="logoUrl" :alt="brandName" class="h-10 w-auto drop-shadow-sm" />
            <!-- {{ brandName }} -->
          </Link>

          <form class="hidden flex-1 items-center sm:flex" @submit.prevent="submitSearch">
            <div class="relative w-full">
              <input
                v-model="search"
                type="search"
                :placeholder="t('Search products')"
                class="input-base w-full pl-10"
                :aria-label="t('Search products')"
              />
              <svg
                viewBox="0 0 24 24"
                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
              </svg>
            </div>
            <button type="submit" class="ml-3 rounded-full border border-brand bg-brand-soft px-4 py-2 text-xs font-semibold text-brand-strong transition hover:border-brand-strong hover:text-brand-strong">
              {{ t('Search') }}
            </button>
          </form>

          <div class="ml-auto flex items-center gap-2">
            <div class="hidden items-center gap-1 rounded-full border border-slate-200 bg-white/70 p-1 text-[0.6rem] font-semibold text-slate-600 sm:flex">
              <button
                v-for="option in localeOptions"
                :key="option.code"
                type="button"
                class="rounded-full px-2 py-1 uppercase transition"
                :class="option.code === locale ? 'bg-slate-900 text-white' : 'hover:bg-slate-100'"
                :title="option.label"
                :aria-current="option.code === locale ? 'true' : 'false'"
                @click="setLocale(option.code)"
              >
                {{ option.code }}
              </button>
            </div>
            <div ref="accountRef" class="relative">
              <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:border-brand"
                aria-label="Account"
                :aria-expanded="accountOpen"
                @click.stop="toggleAccount"
              >
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25a7.5 7.5 0 0 1 15 0" />
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
                  class="absolute right-0 mt-3 w-56 rounded-2xl border border-slate-200 bg-white p-3 text-sm shadow-lg"
                >
                  <div v-if="authUser" class="space-y-1 border-b border-slate-100 pb-3">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ t('Signed in') }}</p>
                    <p class="font-semibold text-slate-900">{{ authUser.name }}</p>
                    <p class="text-xs text-slate-500">{{ authUser.email }}</p>
                  </div>
                  <div class="mt-3 grid gap-2">
                    <Link v-if="authUser" :href="route('account.index')" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                      {{ t('Account overview') }}
                    </Link>
                    <Link v-if="authUser" href="/account/notifications" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                      {{ t('Notifications') }}
                    </Link>
                    <Link v-if="authUser" href="/orders" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                      {{ t('My orders') }}
                    </Link>
                    <Link v-if="authUser" href="/account/addresses" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                      {{ t('Addresses') }}
                    </Link>
                    <Link v-if="authUser" href="/account/payments" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                      {{ t('Payments') }}
                    </Link>
                    <Link v-if="authUser" href="/account/wallet" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                      {{ t('Wallet') }}
                    </Link>
                    <Link v-if="authUser" href="/account/refunds" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                      {{ t('Refunds') }}
                    </Link>
                    <Link v-if="authUser" href="/account/wishlist" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                      <span class="flex items-center justify-between">
                        <span>{{ t('Wishlist') }}</span>
                        <span v-if="wishlistCount" class="ml-2 rounded-full bg-slate-900 px-2 py-0.5 text-[0.6rem] font-semibold text-white">
                          {{ wishlistCount }}
                        </span>
                      </span>
                    </Link>
                    <Link v-if="! authUser" :href="route('login')" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                      {{ t('Sign in') }}
                    </Link>
                    <Link v-if="! authUser" :href="route('register')" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
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

            <div ref="cartRef" class="relative">
              <button
                type="button"
                class="relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:border-brand"
                aria-label="Cart"
                :aria-expanded="cartOpen"
                @click.stop="toggleCart"
              >
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.5 12h10.5l2-8H7.5" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM17 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                </svg>
                <span
                  v-if="cartCount"
                  class="absolute -right-1 -top-1 inline-flex min-w-[1.1rem] items-center justify-center rounded-full bg-slate-900 px-1.5 text-[0.6rem] font-semibold text-white"
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
                  class="absolute right-0 mt-3 w-72 rounded-2xl border border-slate-200 bg-white p-4 text-sm shadow-lg"
                >
                  <p class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ t('Cart') }}</p>
                  <div v-if="cartLines.length" class="mt-3 space-y-3">
                    <div v-for="line in cartLines" :key="line.id" class="flex items-center gap-3">
                      <div class="h-12 w-12 overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                        <img v-if="line.media?.[0]" :src="line.media[0]" :alt="line.name" class="h-full w-full object-cover" />
                      </div>
                      <div class="flex-1">
                        <p class="text-xs font-semibold text-slate-900">{{ line.name }}</p>
                        <p class="text-[0.7rem] text-slate-500">{{ line.variant || t('Standard') }} · {{ t('Qty :quantity', { quantity: line.quantity }) }}</p>
                      </div>
                      <div class="text-xs font-semibold text-slate-800">
                        {{ line.currency }} {{ Number(line.price).toFixed(2) }}
                      </div>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-600">
                      <span>{{ t('Subtotal') }}</span>
                      <span class="font-semibold text-slate-900">{{ cartCurrency }} {{ cartSubtotal.toFixed(2) }}</span>
                    </div>
                    <div class="grid gap-2">
                      <Link href="/cart" class="btn-primary w-full text-center">{{ t('View cart') }}</Link>
                      <Link href="/checkout" class="btn-secondary w-full text-center">{{ t('Checkout') }}</Link>
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

        <div class="pb-3 sm:hidden">
          <form class="flex items-center gap-2" @submit.prevent="submitSearch">
            <div class="relative w-full">
              <input
                v-model="search"
                type="search"
                :placeholder="t('Search products')"
                class="input-base w-full pl-10"
                :aria-label="t('Search products')"
              />
              <svg
                viewBox="0 0 24 24"
                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
              </svg>
            </div>
            <button type="submit" class="btn-secondary px-4 py-2 text-xs">
              {{ t('Go') }}
            </button>
          </form>
        </div>

        <nav class="hidden items-center gap-6 border-t border-slate-100 py-3 text-sm font-medium text-slate-700 lg:flex">
          <div
            class="relative"
            @mouseenter="openCategories"
            @mouseleave="closeCategories"
          >
            <button type="button" class="inline-flex items-center gap-2 text-slate-700">
              {{ t('Categories') }}
              <svg viewBox="0 0 20 20" class="h-4 w-4" fill="currentColor">
                <path fill-rule="evenodd" d="M5.25 7.5l4.5 4.5 4.5-4.5" clip-rule="evenodd" />
              </svg>
            </button>

            <div
              v-if="categoriesOpen"
              class="absolute left-0 top-7 z-20 w-[560px] max-h-[70vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5"
              @mouseenter="openCategories"
              @mouseleave="closeCategories"
            >
              <div class="space-y-2">
                <div
                  v-for="category in rootCategories"
                  :key="category.name"
                  class="space-y-1"
                >
                  <!-- Root category -->
                  <Link
                    :href="categoryHref(category)"
                    class="flex items-center gap-3 rounded-lg border border-transparent p-2 text-sm font-semibold text-slate-900 transition hover:border-slate-200 hover:bg-slate-50"
                  >
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-xs font-bold text-slate-600">
                      {{ category.short }}
                    </span>
                    {{ category.name }}
                  </Link>

                  <!-- Subcategories (nested) -->
                  <CategoryTreeNode
                    v-if="category.children.length"
                    :categories="category.children"
                    :level="1"
                  />
                </div>
              </div>
            </div>
          </div>

          <Link
            v-for="link in headerLinks"
            :key="link.href + link.label"
            :class="navClass(link.href)"
            :href="link.href"
          >
            {{ link.label }}
          </Link>
          <template v-if="authUser">
            <Link :class="navClass('/orders')" href="/orders">{{ t('Orders') }}</Link>
            <Link :class="navClass('/account')" href="/account">{{ t('Account') }}</Link>
            <Link :class="navClass('/account/wishlist')" href="/account/wishlist">
              <span class="inline-flex items-center gap-2">
                {{ t('Wishlist') }}
                <span v-if="wishlistCount" class="rounded-full bg-slate-900 px-2 py-0.5 text-[0.6rem] font-semibold text-white">
                  {{ wishlistCount }}
                </span>
              </span>
            </Link>
          </template>
          <template v-else>
            <Link :class="navClass('/login')" :href="route('login')">{{ t('Sign in') }}</Link>
          </template>
        </nav>
      </div>
    </header>

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
      <slot />
    </main>

    <footer class="border-t border-slate-200 bg-white/90">
        <div class="container-base grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-5">
        <div class="space-y-3">
          <p class="text-lg font-semibold text-slate-900">{{ brandName }}</p>
          <p class="text-sm text-slate-600">
            {{ footerBlurb }}
          </p>
          <div class="text-xs text-slate-500">
            {{ t('Support: :email', { email: supportEmail }) }}
          </div>
        </div>
        <div
          v-for="column in footerColumns"
          :key="column.title"
          class="space-y-2 text-sm text-slate-600"
        >
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ column.title }}</p>
          <Link
            v-for="link in column.links || []"
            :key="link.href + link.label"
            :href="link.href"
            class="block hover:text-slate-900"
          >
            {{ link.label }}
          </Link>
        </div>
      </div>

      <div class="border-t border-slate-100">
          <div class="container-base flex flex-col gap-2 py-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ copyrightText }} (c) {{ new Date().getFullYear() }}</span>
            <span>{{ deliveryNotice }}</span>
          </div>
      </div>
    </footer>

    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="mobileOpen" class="fixed inset-0 z-[60]">
        <div class="absolute inset-0 bg-slate-900/20" @click="mobileOpen = false" />
      </div>
    </Transition>

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
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6l-12 12" />
            </svg>
          </button>
        </div>

          <div class="mt-6 space-y-6">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Categories') }}</p>
              <div class="mt-3 space-y-4">
                <div v-for="category in categories" :key="category.name" class="space-y-2">
                  <Link
                    :href="categoryHref(category)"
                    class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-semibold text-slate-800"
                    @click="mobileOpen = false"
                  >
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500">
                      {{ category.short }}
                    </span>
                    {{ category.name }}
                  </Link>
                  <div v-if="category.children.length" class="space-y-1 pl-8 text-xs text-slate-600">
                    <Link
                      v-for="child in category.children"
                      :key="child.slug ?? child.name"
                      :href="categoryHref(child)"
                      class="block rounded-lg text-xs font-semibold text-slate-600 transition hover:text-slate-900"
                      @click="mobileOpen = false"
                    >
                      {{ child.name }}
                    </Link>
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
            <Link href="/cart" class="block hover:text-slate-900" @click="mobileOpen = false">{{ t('Cart') }}</Link>
          </div>
          <div class="space-y-2 text-sm text-slate-600">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Account') }}</p>
            <template v-if="authUser">
              <Link href="/account" class="block hover:text-slate-900" @click="mobileOpen = false">{{ t('Overview') }}</Link>
              <Link href="/account/notifications" class="block hover:text-slate-900" @click="mobileOpen = false">{{ t('Notifications') }}</Link>
              <Link href="/orders" class="block hover:text-slate-900" @click="mobileOpen = false">{{ t('Orders') }}</Link>
              <Link href="/account/addresses" class="block hover:text-slate-900" @click="mobileOpen = false">{{ t('Addresses') }}</Link>
              <Link href="/account/payments" class="block hover:text-slate-900" @click="mobileOpen = false">{{ t('Payments') }}</Link>
              <Link href="/account/refunds" class="block hover:text-slate-900" @click="mobileOpen = false">{{ t('Refunds') }}</Link>
              <Link href="/account/wishlist" class="block hover:text-slate-900" @click="mobileOpen = false">
                <span class="inline-flex items-center gap-2">
                  {{ t('Wishlist') }}
                  <span v-if="wishlistCount" class="rounded-full bg-slate-900 px-2 py-0.5 text-[0.6rem] font-semibold text-white">
                    {{ wishlistCount }}
                  </span>
                </span>
              </Link>
              <Link href="/account/wallet" class="block hover:text-slate-900" @click="mobileOpen = false">{{ t('Wallet') }}</Link>
            </template>
            <template v-else>
              <Link :href="route('login')" class="block hover:text-slate-900" @click="mobileOpen = false">{{ t('Sign in') }}</Link>
              <Link :href="route('register')" class="block hover:text-slate-900" @click="mobileOpen = false">{{ t('Create account') }}</Link>
            </template>
          </div>
          <div class="space-y-2 text-sm text-slate-600">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Language') }}</p>
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
          </div>
        </div>
      </aside>
    </Transition>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { DotLottieVue } from '@lottiefiles/dotlottie-vue'
import { useTranslations } from '@/i18n'
import CategoryTreeNode from '@/Components/CategoryTreeNode.vue'

const page = usePage()
const { t, locale, availableLocales } = useTranslations()
const mobileOpen = ref(false)
const categoriesOpen = ref(false)
const categoriesCloseTimer = ref(null)
const accountOpen = ref(false)
const cartOpen = ref(false)
const accountRef = ref(null)
const cartRef = ref(null)
const isNavigating = ref(false)

const authUser = computed(() => page.props.auth?.user ?? null)
const storefront = computed(() => page.props.storefront ?? {})
const cartSummary = computed(() => page.props.cart ?? { lines: [], count: 0, subtotal: 0 })
const cartLines = computed(() => cartSummary.value.lines ?? [])
const cartCount = computed(() => cartSummary.value.count ?? 0)
const cartSubtotal = computed(() => Number(cartSummary.value.subtotal ?? 0))
const cartCurrency = computed(() => cartLines.value[0]?.currency ?? 'USD')
const wishlistCount = computed(() => Number(page.props.wishlist?.count ?? 0))
const localeOptions = computed(() => {
  const entries = Object.entries(availableLocales.value ?? {})
  return entries.map(([code, label]) => ({ code, label }))
})
const notices = computed(() => {
  const flash = page.props.flash ?? {}
  const entries = []
  if (flash.cart_notice) {
    entries.push({ key: 'cart', message: flash.cart_notice })
  }
  if (flash.wishlist_notice) {
    entries.push({ key: 'wishlist', message: flash.wishlist_notice })
  }
  return entries
})

const fallbackHeaderLinks = [
  { label: t('Shop'), href: '/products' },
  { label: t('Track order'), href: '/orders/track' },
  { label: t('Support'), href: '/support' },
  { label: t('FAQ'), href: '/faq' },
]
const fallbackFooterColumns = [
  {
    title: t('Shop'),
    links: [
      { label: t('All products'), href: '/products' },
      { label: t('Track order'), href: '/orders/track' },
      { label: t('Cart'), href: '/cart' },
      { label: t('Checkout'), href: '/checkout' },
    ],
  },
  {
    title: t('Support'),
    links: [
      { label: t('Contact'), href: '/support' },
      { label: t('FAQ'), href: '/faq' },
      { label: t('About'), href: '/about' },
      { label: t('My orders'), href: '/orders' },
    ],
  },
  {
    title: t('Account'),
    links: [
      { label: t('Overview'), href: '/account' },
      { label: t('Notifications'), href: '/account/notifications' },
      { label: t('Orders'), href: '/orders' },
      { label: t('Addresses'), href: '/account/addresses' },
      { label: t('Payment methods'), href: '/account/payments' },
      { label: t('Refunds'), href: '/account/refunds' },
      { label: t('Wallet'), href: '/account/wallet' },
    ],
  },
  {
    title: t('Legal'),
    links: [
      { label: t('Shipping policy'), href: '/legal/shipping-policy' },
      { label: t('Refund policy'), href: '/legal/refund-policy' },
      { label: t('Terms of service'), href: '/legal/terms-of-service' },
      { label: t('Privacy policy'), href: '/legal/privacy-policy' },
    ],
  },
]

const brandName = computed(() => storefront.value.brand_name ?? page.props.site?.site_name ?? 'Azura')
const logoUrl = computed(() => {
  const path = page.props.site?.logo_path
  return path ? `/storage/${path}` : null
})
const footerBlurb = computed(
  () => storefront.value.footer_blurb ?? page.props.site?.site_description ?? t('Global sourcing with local clarity.')
)
const deliveryNotice = computed(
  () => storefront.value.delivery_notice ?? t("Delivery to Cote d'Ivoire with duties shown before checkout.")
)
const copyrightText = computed(() => storefront.value.copyright_text ?? brandName.value)
const headerLinks = computed(() => storefront.value.header_links ?? fallbackHeaderLinks)
const footerColumns = computed(() => storefront.value.footer_columns ?? fallbackFooterColumns)
const themeColors = computed(() => {
  const site = page.props.site ?? {}
  return {
    primary: site.primary_color || '#FACC15', // Warm golden yellow
    secondary: site.secondary_color || '#F97316', // Warm orange accent
    accent: site.accent_color || '#9CA3AF', // Neutral gray for UI balance
    strong: '#2B2B2B', // Charcoal text
    background: '#FFFFFF',
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

const supportEmail = computed(() => page.props.site?.support_email ?? 'support@dispatch.store')
const currentPath = computed(() => (page.url || '').split('?')[0])
const appUrl = computed(() => {
  const configured = page.props.appUrl
  if (configured) {
    return String(configured).replace(/\/$/, '')
  }
  if (typeof window !== 'undefined') {
    return window.location.origin
  }
  return ''
})

const resolveAssetUrl = (path) => {
  if (! path) {
    return null
  }
  const trimmed = String(path)
  if (/^https?:\/\//i.test(trimmed)) {
    return trimmed
  }
  return `${appUrl.value}/${trimmed.replace(/^\//, '')}`
}

const seoTitle = computed(() => page.props.seo?.title ?? 'Azura')
const seoDescription = computed(() => page.props.seo?.description ?? t('Curated global essentials, delivered with clarity.'))
const seoImage = computed(() => resolveAssetUrl(page.props.seo?.image))
const canonicalUrl = computed(() => {
  if (! appUrl.value) {
    return null
  }
  return `${appUrl.value}${currentPath.value || ''}`
})

const navClass = (path) => [
  'text-slate-600 transition hover:text-slate-900',
  currentPath.value.startsWith(path) ? 'font-semibold text-slate-900' : '',
]

const fallbackCategories = computed(() => ([
  t('Home and Kitchen'),
  t('Tech and Gadgets'),
  t('Beauty and Care'),
  t('Fashion'),
  t('Baby and Kids'),
  t('Fitness and Outdoor'),
]))

  const buildCategory = (entry) => {
    const name = typeof entry === 'string' ? entry : entry?.name
    const slug = typeof entry === 'string' ? null : entry?.slug
    const initials = String(name || '')
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((word) => word[0])
      .join('')
      .toUpperCase()
    const short = initials || String(name || '').slice(0, 2).toUpperCase()
    const node = { name, slug, short, children: [] }

    if (Array.isArray(entry?.children) && entry.children.length) {
      node.children = entry.children.map((child) => buildCategory(child))
    }

    return node
  }

  const categories = computed(() => {
    const source = Array.isArray(page.props.categories) && page.props.categories.length
      ? page.props.categories
      : fallbackCategories.value
    return source.map((entry) => buildCategory(entry))
  })

const rootCategories = computed(() => {
  return categories.value.filter(cat => !cat.parent_id)
})

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
  if (! value) {
    router.get('/products')
    return
  }
  router.get('/search', { q: value }, { preserveState: true, replace: true })
}

onMounted(() => {
  const start = () => { isNavigating.value = true }
  const finish = () => { isNavigating.value = false }
  const offStart = router.on('start', start)
  const offFinish = router.on('finish', finish)
  const offError = router.on('error', finish)
  const offInvalid = router.on('invalid', finish)
  onBeforeUnmount(() => {
    offStart()
    offFinish()
    offError()
    offInvalid()
  })
})

const openCategories = () => {
  categoriesOpen.value = true
  if (categoriesCloseTimer.value) {
    clearTimeout(categoriesCloseTimer.value)
    categoriesCloseTimer.value = null
  }
}

const closeCategories = () => {
  if (categoriesCloseTimer.value) {
    clearTimeout(categoriesCloseTimer.value)
  }
  categoriesCloseTimer.value = setTimeout(() => {
    categoriesOpen.value = false
  }, 150)
}

const categoryHref = (category) => {
  if (category?.slug) {
    return `/categories/${encodeURIComponent(category.slug)}`
  }
  return `/products?category=${encodeURIComponent(category?.name ?? '')}`
}

const toggleAccount = () => {
  accountOpen.value = ! accountOpen.value
  if (accountOpen.value) {
    cartOpen.value = false
  }
}

const toggleCart = () => {
  cartOpen.value = ! cartOpen.value
  if (cartOpen.value) {
    accountOpen.value = false
  }
}

const setLocale = (target) => {
  if (! target || target === locale.value) {
    return
  }
  router.get(`/locale/${target}`, {}, { preserveScroll: true })
}

const handleDocumentClick = (event) => {
  const target = event.target
  if (accountOpen.value && accountRef.value && ! accountRef.value.contains(target)) {
    accountOpen.value = false
  }
  if (cartOpen.value && cartRef.value && ! cartRef.value.contains(target)) {
    cartOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleDocumentClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick)
})
</script>

<style scoped>
.brand-theme {
  --brand-primary: #facc15;
  --brand-primary-2: #f97316;
  --brand-accent: #9ca3af;
  --brand-strong: #2b2b2b;
  --brand-bg: #ffffff;
  --brand-glow-start: #facc15;
  --brand-glow-end: #f97316;
  --brand-soft: color-mix(in srgb, var(--brand-primary) 12%, white);
}

.bg-brand-glow {
  background: linear-gradient(90deg,rgba(240, 236, 214, 1) 0%, rgba(246, 225, 109, 1) 50%, rgba(245, 149, 15, 1) 100%);
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

.nav-link {
  @apply flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left transition hover:bg-white hover:shadow-sm;
}
</style>
