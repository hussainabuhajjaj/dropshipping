<template>
    <div
        class="relative hidden lg:block"
        style="background: linear-gradient(90deg, rgba(240,236,214,1) 0%, rgba(246,225,109,1) 50%, rgba(245,149,15,1) 100%);"
    >
        <div class="container-base">
            <div class="relative py-3">
                <button
                    v-show="canScrollLeft"
                    type="button"
                    class="absolute left-0 top-1/2 z-10 -translate-y-1/2 rounded-full bg-slate-900/70 p-2 text-white shadow hover:text-[#f59e0b]"
                    :aria-label="t('Scroll left')"
                    @click="scrollCategories('left')"
                >
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <div
                    ref="scrollRef"
                    class="hide-scrollbar flex items-center gap-2 overflow-x-auto px-10"
                    @scroll.passive="updateScrollArrows"
                    @mouseleave="categoryStore.scheduleClose()"
                >
                    <button
                        type="button"
                        class="group relative shrink-0 whitespace-nowrap px-4 py-2 text-sm font-semibold text-slate-950 transition hover:text-[#f59e0b]"
                        @mouseenter="categoryStore.openRootMenu()"
                        @focus="categoryStore.openRootMenu()"
                    >
                        {{ t('Categories') }}
                        <span class="absolute bottom-0 left-0 h-0.5 w-full bg-slate-950/70 transition group-hover:bg-[#f59e0b]" />
                    </button>

                    <Link
                        v-for="category in categories"
                        :key="category.slug || category.name"
                        :href="category.href"
                        class="group relative shrink-0 whitespace-nowrap px-4 py-2 text-sm font-semibold text-slate-950 transition hover:text-[#f59e0b]"
                        @mouseenter="categoryStore.scheduleOpen(category)"
                        @focus="categoryStore.setActiveCategory(category)"
                    >
                        {{ category.name }}
                        <span
                            class="absolute bottom-0 left-0 h-0.5 bg-[#f59e0b] transition-all"
                            :class="activeCategory?.slug === category.slug && categoryStore.desktopOpen ? 'w-full' : 'w-0 group-hover:w-full'"
                        />
                    </Link>
                </div>

                <button
                    v-show="canScrollRight"
                    type="button"
                    class="absolute right-0 top-1/2 z-10 -translate-y-1/2 rounded-full bg-slate-900/70 p-2 text-white shadow hover:text-[#f59e0b]"
                    :aria-label="t('Scroll right')"
                    @click="scrollCategories('right')"
                >
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { storeToRefs } from 'pinia'
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useCategoryStore } from '@/stores/category'

const props = defineProps({
    t: { type: Function, required: true },
})

const categoryStore = useCategoryStore()
const { categories, activeCategory } = storeToRefs(categoryStore)

const scrollRef = ref(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)

const updateScrollArrows = () => {
    const element = scrollRef.value
    if (!element) return

    const maxScrollLeft = element.scrollWidth - element.clientWidth
    canScrollLeft.value = element.scrollLeft > 2
    canScrollRight.value = element.scrollLeft < maxScrollLeft - 2
}

const scrollCategories = (direction) => {
    const element = scrollRef.value
    if (!element) return

    const amount = Math.round(element.clientWidth * 0.75)
    element.scrollBy({
        left: direction === 'left' ? -amount : amount,
        behavior: 'smooth',
    })

    window.setTimeout(updateScrollArrows, 220)
}

onMounted(() => {
    nextTick(updateScrollArrows)
    window.addEventListener('resize', updateScrollArrows)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateScrollArrows)
})

watch(categories, () => {
    nextTick(updateScrollArrows)
}, { deep: true })
</script>
