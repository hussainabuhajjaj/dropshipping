import axios from 'axios'
import { ref } from 'vue'

const cache = new Map()
const TTL_MS = 5 * 60 * 1000

const buildKey = (slug, page, perPage) => `${slug}:${page}:${perPage}`

export function useCategoryProducts() {
    const loading = ref(false)
    const error = ref('')

    const fetchCategoryProducts = async (slug, page = 1, perPage = 10) => {
        const key = buildKey(slug, page, perPage)
        const cached = cache.get(key)
        const now = Date.now()

        if (cached && now - cached.timestamp < TTL_MS) {
            return cached.payload
        }

        loading.value = true
        error.value = ''

        try {
            const { data } = await axios.get(`/api/storefront/categories/${slug}`, {
                params: { page, per_page: perPage },
            })

            const payload = {
                items: data?.products ?? [],
                pagination: data?.pagination ?? { currentPage: page, lastPage: page, total: data?.products?.length ?? 0 },
                category: data?.category ?? null,
            }

            cache.set(key, { timestamp: now, payload })
            return payload
        } catch (err) {
            error.value = err?.response?.data?.message || err?.message || 'Unable to load products'
            throw err
        } finally {
            loading.value = false
        }
    }

    const clearCategoryCache = () => cache.clear()

    return { fetchCategoryProducts, clearCategoryCache, loading, error }
}
