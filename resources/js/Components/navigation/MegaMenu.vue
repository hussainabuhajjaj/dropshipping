<template>
    <Transition
        v-if="!mobile"
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
    >
        <div
            v-if="desktopOpen && activeCategory"
            class="absolute left-0 right-0 top-full z-40 max-h-[min(32rem,calc(100dvh-8.5rem))] overflow-hidden border-t border-slate-200 bg-white/95 shadow-2xl backdrop-blur-xl"
            @mouseenter="categoryStore.cancelScheduledClose()"
            @mouseleave="categoryStore.scheduleClose()"
        >
            <div class="container-base h-full overflow-y-auto py-5">
                <div class="grid gap-5 lg:grid-cols-[220px,1fr] lg:items-start">
                    <aside class="max-h-[min(27rem,calc(100dvh-12rem))] overflow-y-auto rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-3">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ t('Categories') }}</p>
                            <Link href="/products" class="text-xs font-semibold text-[#d97706]">
                                {{ t('View all') }}
                            </Link>
                        </div>

                        <div class="space-y-1">
                            <button
                                v-for="category in categories"
                                :key="category.slug || category.name"
                                type="button"
                                class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-[13px] font-semibold transition"
                                :class="activeCategory?.slug === category.slug ? 'bg-slate-950 text-white shadow-lg' : 'text-slate-700 hover:bg-white hover:text-slate-950'"
                                @mouseenter="categoryStore.setActiveCategory(category)"
                                @focus="categoryStore.setActiveCategory(category)"
                            >
                                <span class="truncate">{{ category.name }}</span>
                                <span class="text-xs opacity-70">{{ category.children?.length ?? 0 }}</span>
                            </button>
                        </div>
                    </aside>

                    <section class="max-h-[min(27rem,calc(100dvh-12rem))] space-y-4 overflow-y-auto pr-2">
                        <div class="flex items-start justify-between gap-6">
                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ t('Browse fast') }}</p>
                                <h3 class="text-xl font-semibold tracking-tight text-slate-950">{{ activeCategory.name }}</h3>
                                <p class="max-w-xl text-xs text-slate-500 sm:text-sm">
                                    {{ t('Jump into the most relevant subcategories without leaving the product flow.') }}
                                </p>
                            </div>

                            <Link
                                :href="activeCategory.href"
                                class="hidden rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-950 hover:text-slate-950 lg:inline-flex"
                            >
                                {{ t('Shop all') }}
                            </Link>
                        </div>

                        <div
                            v-if="subcategories.length"
                            class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5"
                        >
                            <Link
                                v-for="child in subcategories"
                                :key="child.slug || child.name"
                                :href="child.href"
                                class="group rounded-[1.25rem] border border-slate-200 bg-white p-3 text-center transition hover:-translate-y-1 hover:border-slate-950 hover:shadow-xl"
                            >
                                <div class="mx-auto flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200 sm:h-20 sm:w-20">
                                    <img
                                        v-if="child.image"
                                        :src="child.image"
                                        :alt="child.name"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    >
                                    <span v-else class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                        {{ initials(child.name) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-xs font-semibold text-slate-900 sm:text-sm">{{ child.name }}</p>
                                <p v-if="child.product_count" class="mt-1 text-[11px] text-slate-500">
                                    {{ t(':count items', { count: child.product_count }) }}
                                </p>
                                <div v-if="child.children?.length" class="mt-2 flex flex-wrap justify-center gap-1.5">
                                    <span
                                        v-for="grandChild in child.children.slice(0, 3)"
                                        :key="grandChild.slug || grandChild.name"
                                        class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500"
                                    >
                                        {{ grandChild.name }}
                                    </span>
                                </div>
                            </Link>
                        </div>

                        <div v-else class="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 p-8 text-sm text-slate-500">
                            {{ t('No subcategories are available yet for this section.') }}
                        </div>

                        <div v-if="previewProducts.length" class="space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ t('Trending now') }}</p>
                                <span class="text-xs text-slate-400">{{ t('Preloaded for instant browsing') }}</span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                                <Link
                                    v-for="product in previewProducts.slice(0, 4)"
                                    :key="product.id"
                                    :href="product.href || `/products/${encodeURIComponent(product.slug || product.id)}`"
                                    class="overflow-hidden rounded-[1.1rem] border border-slate-200 bg-white transition hover:-translate-y-1 hover:shadow-lg"
                                >
                                    <div class="aspect-[4/5] bg-slate-100">
                                        <img
                                            v-if="product.image"
                                            :src="product.image"
                                            :alt="product.name"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        >
                                    </div>
                                    <div class="space-y-1 p-2.5">
                                        <p class="line-clamp-2 text-xs font-semibold text-slate-900 sm:text-sm">{{ product.name }}</p>
                                        <p class="text-[11px] text-slate-500">{{ product.category || activeCategory.name }}</p>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </Transition>

    <div v-else class="mt-3 max-h-[calc(100dvh-16rem)] space-y-3 overflow-y-auto pr-1">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Shop by category') }}</p>
            <Link href="/products" class="text-xs font-semibold text-[#d97706]" @click="emitNavigate">
                {{ t('View all') }}
            </Link>
        </div>

        <div class="space-y-3">
            <div
                v-for="category in categories"
                :key="category.slug || category.name"
                class="overflow-hidden rounded-[1.1rem] border border-slate-200 bg-white"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 px-3 py-3 text-left"
                    @click="toggleMobile(category)"
                >
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200">
                            <img
                                v-if="category.image"
                                :src="category.image"
                                :alt="category.name"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            >
                            <span v-else class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                {{ initials(category.name) }}
                            </span>
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-[13px] font-semibold text-slate-900">{{ category.name }}</span>
                            <span class="block text-xs text-slate-500">{{ t(':count sections', { count: category.children?.length ?? 0 }) }}</span>
                        </span>
                    </span>
                    <span class="text-lg text-slate-400">{{ expanded(category.slug) ? '−' : '+' }}</span>
                </button>

                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-[960px] opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="max-h-[960px] opacity-100"
                    leave-to-class="max-h-0 opacity-0"
                >
                    <div v-if="expanded(category.slug)" class="border-t border-slate-100 px-4 py-4">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <Link
                                :href="category.href"
                                class="text-sm font-semibold text-[#d97706]"
                                @click="emitNavigate"
                            >
                                {{ t('Shop all :category', { category: category.name }) }}
                            </Link>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <Link
                                v-for="child in category.children"
                                :key="child.slug || child.name"
                                :href="child.href"
                                class="rounded-[1rem] border border-slate-200 bg-slate-50 p-2.5"
                                @click="emitNavigate"
                            >
                                <div class="mx-auto flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-white ring-1 ring-slate-200">
                                    <img
                                        v-if="child.image"
                                        :src="child.image"
                                        :alt="child.name"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    >
                                    <span v-else class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                        {{ initials(child.name) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-center text-xs font-semibold text-slate-900">{{ child.name }}</p>
                            </Link>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { storeToRefs } from 'pinia'
import { useCategoryStore } from '@/stores/category'

const props = defineProps({
    t: { type: Function, required: true },
    mobile: { type: Boolean, default: false },
})

const emit = defineEmits(['navigate'])

const categoryStore = useCategoryStore()
const { activeCategory, categories, desktopOpen, mobileExpanded, previewProducts, subcategories } = storeToRefs(categoryStore)

const initials = (value) => String(value || '')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase()

const expanded = (slug) => mobileExpanded.value.includes(slug)

const emitNavigate = () => {
    emit('navigate')
}

const toggleMobile = async (category) => {
    categoryStore.toggleMobileCategory(category.slug)
    await categoryStore.preloadProducts(category)
}
</script>
