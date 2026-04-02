<template>
    <section class="category-product-rail" :data-category="category.slug" ref="sectionRef">
        <div class="section-head">
            <div>
                <h2 class="section-title">{{ category.name }}</h2>
            </div>
            <Link :href="viewAllHref" class="section-link">
                {{ t('View all') }}
            </Link>
        </div>

        <div class="rail-surface">
            <div
                ref="trackRef"
                class="rail-track"
                @scroll.passive="updateArrows"
            >
                <template v-if="isLoading">
                    <div v-for="n in skeletonCount" :key="`skeleton-${n}`" class="rail-card skeleton-card">
                        <div class="skeleton-media shimmer"></div>
                        <div class="skeleton-line shimmer w-4/5"></div>
                        <div class="skeleton-line shimmer w-3/5"></div>
                        <div class="skeleton-pill shimmer w-2/5"></div>
                    </div>
                </template>

                <template v-else>
                    <ProductCard
                        v-for="product in products"
                        :key="product.id"
                        :product="product"
                        :currency="currency"
                        :promotions="promotions"
                        dense
                        class="rail-card"
                    />

                    <button
                        v-if="canLoadMore"
                        type="button"
                        class="rail-card load-more-card"
                        :disabled="loadingMore"
                        @click="loadNextPage"
                    >
                        <span v-if="!loadingMore">{{ t('Load more from :category', { category: category.name }) }}</span>
                        <span v-else class="spinner">{{ t('Loading…') }}</span>
                    </button>
                </template>
            </div>
        </div>

        <p v-if="error" class="rail-error">{{ error }}</p>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import ProductCard from '@/Components/ProductCard.vue'
import { useTranslations } from '@/i18n'
import { useCategoryProducts } from '@/composables/useCategoryProducts'

const props = defineProps({
    category: { type: Object, required: true },
    currency: { type: String, default: 'USD' },
    promotions: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const { fetchCategoryProducts, loading, error } = useCategoryProducts()

const products = ref([])
const page = ref(1)
const lastPage = ref(1)
const loadingMore = ref(false)
const trackRef = ref(null)
const sectionRef = ref(null)
const observer = ref(null)
const skeletonCount = 10

const viewAllHref = computed(() => `/products?category=${encodeURIComponent(props.category.slug || props.category.name)}`)
const canLoadMore = computed(() => page.value < lastPage.value)
const isLoading = computed(() => loading.value && products.value.length === 0)

const canScrollLeft = ref(false)
const canScrollRight = ref(false)

const updateArrows = () => {
    const el = trackRef.value
    if (!el) return
    canScrollLeft.value = el.scrollLeft > 4
    canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 4
}

const scroll = (direction) => {
    const el = trackRef.value
    if (!el) return
    const amount = el.clientWidth * 0.7
    const delta = direction === 'left' ? -amount : amount
    el.scrollBy({ left: delta, behavior: 'smooth' })
}

const loadPage = async (pageToLoad = 1) => {
    const payload = await fetchCategoryProducts(props.category.slug, pageToLoad, 10)
    if (pageToLoad === 1) {
        products.value = payload.items
    } else {
        const ids = new Set(products.value.map((p) => p.id))
        const merged = [...products.value]
        payload.items.forEach((item) => {
            if (!ids.has(item.id)) {
                merged.push(item)
                ids.add(item.id)
            }
        })
        products.value = merged
    }

    page.value = payload.pagination?.currentPage ?? pageToLoad
    lastPage.value = payload.pagination?.lastPage ?? pageToLoad
    updateArrows()
}

const loadNextPage = async () => {
    if (!canLoadMore.value || loadingMore.value) return
    loadingMore.value = true
    try {
        await loadPage(page.value + 1)
    } catch (err) {
        console.error(err)
    } finally {
        loadingMore.value = false
    }
}

const startObserver = () => {
    if (!sectionRef.value || observer.value) return
    observer.value = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    loadPage().catch(() => {})
                    observer.value?.disconnect()
                }
            })
        },
        { threshold: 0.15 }
    )
    observer.value.observe(sectionRef.value)
}

onMounted(() => {
    startObserver()
    updateArrows()
})

onBeforeUnmount(() => {
    observer.value?.disconnect()
})
</script>

<style scoped>
.category-product-rail {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.rail-surface {
    position: relative;
}

.rail-track {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(180px, 48vw);
    gap: 12px;
    overflow-x: auto;
    scroll-snap-type: x proximity;
    padding: 4px;
    mask-image: linear-gradient(to right, transparent 0, black 10%, black 90%, transparent 100%);
    -webkit-mask-image: linear-gradient(to right, transparent 0, black 10%, black 90%, transparent 100%);
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
    touch-action: auto;
}

.rail-card {
    scroll-snap-align: start;
    min-height: 100%;
}

.skeleton-card {
    border-radius: 16px;
    padding: 12px;
    background: #fff;
    border: 1px solid #e2e8f0;
    display: grid;
    gap: 8px;
}

.skeleton-media {
    width: 100%;
    aspect-ratio: 4 / 3;
    border-radius: 12px;
    background: #f1f5f9;
}

.skeleton-line {
    height: 12px;
    border-radius: 999px;
    background: #f1f5f9;
}

.skeleton-pill {
    height: 10px;
    border-radius: 999px;
    background: #f1f5f9;
}

.shimmer {
    position: relative;
    overflow: hidden;
}

.shimmer::after {
    content: '';
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.65), transparent);
    animation: shimmer 1.4s infinite;
}

@keyframes shimmer {
    100% {
        transform: translateX(100%);
    }
}

.load-more-card {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    padding: 12px;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    background: #fff;
    font-size: 0.95rem;
    font-weight: 600;
    color: #334155;
}

.spinner {
    animation: pulse 1s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 0.4;
    }
    50% {
        opacity: 1;
    }
}

.rail-error {
    color: #b91c1c;
    font-size: 0.9rem;
}

@media (min-width: 640px) {
    .rail-track {
        grid-auto-columns: minmax(210px, 32vw);
    }
}

@media (min-width: 1024px) {
    .rail-track {
        grid-auto-columns: minmax(220px, 20vw);
    }
}

@media (min-width: 1280px) {
    .rail-track {
        grid-auto-columns: minmax(230px, 16vw);
    }
}
</style>
