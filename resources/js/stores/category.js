import axios from 'axios'
import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

const productPreviewCache = new Map()
const MAX_PRELOADED_CATEGORIES = 3
let preloadedCount = 0
let hoverTimer = null
let closeTimer = null

const normalizeCategory = (entry) => {
    const name = entry?.name ?? ''
    const slug = entry?.slug ?? null

    return {
        id: entry?.id ?? null,
        name,
        slug,
        href: entry?.href ?? (slug ? `/categories/${encodeURIComponent(slug)}` : '/products'),
        parent_id: entry?.parent_id ?? null,
        image: entry?.image ?? entry?.hero_image ?? null,
        hero_image: entry?.hero_image ?? entry?.image ?? null,
        is_featured: Boolean(entry?.is_featured ?? false),
        product_count: Number(entry?.product_count ?? entry?.count ?? 0),
        children: Array.isArray(entry?.children) ? entry.children.map(normalizeCategory) : [],
    }
}

export const useCategoryStore = defineStore('category-discovery', () => {
    const categories = ref([])
    const activeSlug = ref(null)
    const mobileExpanded = ref([])
    const desktopOpen = ref(false)
    const loading = ref(false)
    const initialized = ref(false)

    const activeCategory = computed(() => (
        categories.value.find((category) => category.slug === activeSlug.value) ?? categories.value[0] ?? null
    ))

    const subcategories = computed(() => activeCategory.value?.children ?? [])

    const previewProducts = computed(() => {
        const slug = activeCategory.value?.slug
        return slug ? (productPreviewCache.get(slug) ?? []) : []
    })

    const hydrate = (payload) => {
        const next = Array.isArray(payload) ? payload.map(normalizeCategory) : []
        categories.value = next

        if (!activeSlug.value || !next.some((category) => category.slug === activeSlug.value)) {
            activeSlug.value = next[0]?.slug ?? null
        }

        initialized.value = next.length > 0
    }

    const fetchCategories = async (params = {}) => {
        if (loading.value) return

        loading.value = true

        try {
            const { data } = await axios.get('/api/storefront/categories', { params })
            hydrate(data?.categories ?? [])
        } finally {
            loading.value = false
        }
    }

    const preloadProducts = async (category, perPage = 8) => {
        const slug = typeof category === 'string' ? category : category?.slug

        if (!slug || productPreviewCache.has(slug)) {
            return
        }

        if (preloadedCount >= MAX_PRELOADED_CATEGORIES) {
            return
        }

        preloadedCount++

        try {
            const { data } = await axios.get(`/api/storefront/categories/${encodeURIComponent(slug)}`, {
                params: { per_page: perPage },
            })

            productPreviewCache.set(slug, Array.isArray(data?.products) ? data.products.slice(0, perPage) : [])
        } catch {
            productPreviewCache.set(slug, [])
        }
    }

    const setActiveCategory = async (category, options = {}) => {
        const nextSlug = typeof category === 'string' ? category : category?.slug

        if (!nextSlug) return

        activeSlug.value = nextSlug

        if (options.open !== false) {
            desktopOpen.value = true
        }

        if (options.prefetch !== false) {
            await preloadProducts(nextSlug, options.perPage ?? 8)
        }
    }

    const openRootMenu = async () => {
        desktopOpen.value = true

        if (!activeCategory.value && categories.value[0]) {
            await setActiveCategory(categories.value[0])
            return
        }

        if (activeCategory.value) {
            await preloadProducts(activeCategory.value)
        }
    }

    const scheduleOpen = (category, delay = 90) => {
        if (hoverTimer) window.clearTimeout(hoverTimer)
        if (closeTimer) {
            window.clearTimeout(closeTimer)
            closeTimer = null
        }

        hoverTimer = window.setTimeout(() => {
            setActiveCategory(category)
        }, delay)
    }

    const cancelScheduledClose = () => {
        if (hoverTimer) {
            window.clearTimeout(hoverTimer)
            hoverTimer = null
        }

        if (closeTimer) {
            window.clearTimeout(closeTimer)
            closeTimer = null
        }
    }

    const scheduleClose = (delay = 180) => {
        if (hoverTimer) {
            window.clearTimeout(hoverTimer)
            hoverTimer = null
        }

        if (closeTimer) {
            window.clearTimeout(closeTimer)
        }

        closeTimer = window.setTimeout(() => {
            desktopOpen.value = false
        }, delay)
    }

    const closeMenu = () => {
        cancelScheduledClose()
        desktopOpen.value = false
    }

    const toggleMobileCategory = (slug) => {
        if (!slug) return

        if (mobileExpanded.value.includes(slug)) {
            mobileExpanded.value = mobileExpanded.value.filter((item) => item !== slug)
            return
        }

        mobileExpanded.value = [...mobileExpanded.value, slug]
    }

    return {
        categories,
        activeSlug,
        activeCategory,
        subcategories,
        previewProducts,
        mobileExpanded,
        desktopOpen,
        loading,
        initialized,
        hydrate,
        fetchCategories,
        preloadProducts,
        setActiveCategory,
        openRootMenu,
        scheduleOpen,
        scheduleClose,
        cancelScheduledClose,
        closeMenu,
        toggleMobileCategory,
    }
})
