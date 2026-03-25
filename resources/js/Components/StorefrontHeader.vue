<template>
    <header class="sticky top-0 z-50 shadow-md bg-slate-950 text-white">
        <!-- Top row -->
        <div class="container mx-auto px-4">
            <div class="flex items-center gap-3 py-3">
                <!-- Mobile Menu Toggle -->
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center text-white transition hover:text-[#f59e0b] lg:hidden"
                    @click="mobileOpen = true"
                >
                    <span class="sr-only">Open menu</span>
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
                        <div class="text-xs text-slate-400">Deliver to</div>
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
                            placeholder="What are you looking for?"
                            class="w-full rounded-lg border-2 border-slate-600 bg-white px-5 py-3 pl-12 text-sm text-slate-900 placeholder-slate-500 focus:border-[#f59e0b] focus:outline-none focus:ring-2 focus:ring-[#f59e0b]/20"
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
                            Search
                        </button>
                    </div>
                </form>

                <!-- Right Side Icons -->
                <div class="ml-auto flex items-center gap-3">
                    <!-- Language Toggle -->
                    <div class="hidden items-center gap-1 lg:flex">
                        <button
                            v-for="option in localeOptions"
                            :key="option.code"
                            type="button"
                            class="rounded px-2 py-1 text-xs font-semibold uppercase transition"
                            :class="option.code === locale ? 'bg-[#f59e0b] text-white' : 'text-slate-300 hover:text-white'"
                            @click="setLocale(option.code)"
                        >
                            {{ option.code }}
                        </button>
                    </div>

                    <!-- Currency selector -->
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
                    <div ref="accountRef" class="relative">
                        <button
                            type="button"
                            class="relative inline-flex h-10 w-10 items-center justify-center text-white transition hover:text-[#f59e0b]"
                            @click.stop="toggleAccount"
                        >
                            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                                />
                            </svg>
                        </button>

                        <!-- Account Dropdown -->
                        <div
                            v-if="accountOpen"
                            class="absolute right-0 top-full mt-2 w-56 rounded-2xl border border-slate-200 bg-white p-3 text-sm shadow-lg"
                        >
                            <div class="grid gap-2">
                                <Link href="/login" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                    Sign in
                                </Link>
                                <Link href="/register" class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                    Create account
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Cart -->
                    <div ref="cartRef" class="relative">
                        <Link href="/cart" class="relative inline-flex h-10 w-10 items-center justify-center text-white transition hover:text-[#f59e0b]">
                            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
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
                        </Link>
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
                        placeholder="What are you looking for?"
                        class="w-full rounded-lg border-2 border-slate-600 bg-white px-4 py-2 pl-10 text-sm text-slate-900 placeholder-slate-500 focus:border-[#f59e0b] focus:outline-none"
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
                <button type="submit" class="rounded-md bg-[#f59e0b] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#d97706]">
                    Go
                </button>
            </form>
        </div>

        <!-- Categories Navigation -->
        <div class="relative bg-gradient-header">
            <div class="container mx-auto px-4">
                <div class="relative py-3">
                    <div class="flex items-center gap-2 overflow-x-auto hide-scrollbar px-10">
                        <Link
                            v-for="category in rootCategories"
                            :key="category.slug || category.name"
                            :href="`/categories/${category.slug}`"
                            class="group relative whitespace-nowrap px-4 py-2 text-sm font-semibold text-black transition hover:text-[#f59e0b]"
                        >
                            {{ category.name }}
                            <span class="absolute bottom-0 left-0 h-0.5 w-0 bg-[#f59e0b] transition-all group-hover:w-full"></span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'

// Props
const props = defineProps({
    logoUrl: {
        type: String,
        default: null
    },
    brandName: {
        type: String,
        default: 'Simbazu'
    }
})

// State
const mobileOpen = ref(false)
const accountOpen = ref(false)
const locationOpen = ref(false)
const accountRef = ref(null)
const cartRef = ref(null)
const search = ref('')
const selectedLocation = ref('Abidjan')
const selectedCurrency = ref('JOD')
const locale = ref('en')
const wishlistCount = ref(0)
const cartCount = ref(1) // Example value

// Mock data
const currencyOptions = ref(['JOD', 'USD', 'EUR'])
const localeOptions = ref([
    { code: 'en', label: 'English' },
    { code: 'ar', label: 'Arabic' }
])

const rootCategories = ref([
    { name: 'Electronics', slug: 'electronics' },
    { name: 'Fashion', slug: 'fashion' },
    { name: 'Home & Kitchen', slug: 'home-kitchen' },
    { name: 'Beauty & Health', slug: 'beauty-health' },
    { name: 'Sports & Outdoor', slug: 'sports-outdoor' },
    { name: 'Baby & Kids', slug: 'baby-kids' }
])

const csrfHeaders = () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    return token ? { 'X-CSRF-TOKEN': token } : {}
}

// Methods
const toggleAccount = () => {
    accountOpen.value = !accountOpen.value
}

const setLocale = (code) => {
    if (!code || code === locale.value) return
    router.post('/language', { language: code }, {
        preserveScroll: true,
        headers: csrfHeaders(),
        onSuccess: () => {
            console.log('Language preference saved successfully')
        },
        onError: (errors) => {
            console.error('Failed to save language preference:', errors)
        }
    })
}

const onCurrencyChange = () => {
    // Handle currency change
    console.log('Currency changed to:', selectedCurrency.value)
    router.post('/currency', { currency: selectedCurrency.value }, {
        preserveScroll: true,
        headers: csrfHeaders(),
        onSuccess: () => {
            console.log('Currency preference saved successfully')
        },
        onError: (errors) => {
            console.error('Failed to save currency preference:', errors)
        }
    })
}

const submitSearch = () => {
    if (search.value.trim()) {
        router.get('/search', { q: search.value })
    }
}

// Click outside handler
const handleDocumentClick = (event) => {
    if (accountOpen.value && accountRef.value && !accountRef.value.contains(event.target)) {
        accountOpen.value = false
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
.hide-scrollbar {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.hide-scrollbar::-webkit-scrollbar {
    display: none;
}

.bg-gradient-header {
    background: linear-gradient(90deg, rgba(240,236,214,1) 0%, rgba(246,225,109,1) 50%, rgba(245,149,15,1) 100%);
}
</style>
