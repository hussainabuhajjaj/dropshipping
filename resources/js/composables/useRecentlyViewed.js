import { ref, computed } from 'vue'

const STORAGE_KEY = 'simbazu_recently_viewed'
const MAX_ITEMS = 12

const products = ref([])
let loaded = false

function load() {
  if (loaded) return
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    products.value = raw ? JSON.parse(raw) : []
  } catch {
    products.value = []
  }
  loaded = true
}

function save() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(products.value.slice(0, MAX_ITEMS)))
  } catch {
    // storage full or unavailable
  }
}

export function useRecentlyViewed() {
  load()

  const recentlyViewed = computed(() => products.value)

  const recentCount = computed(() => products.value.length)

  function addProduct(product) {
    load()
    const existing = products.value.findIndex(p => p.id === product.id)
    if (existing !== -1) {
      products.value.splice(existing, 1)
    }
    products.value.unshift({
      id: product.id,
      name: product.name,
      slug: product.slug,
      price: product.price,
      compare_at_price: product.compare_at_price,
      image: product.image || (Array.isArray(product.media) && product.media[0]) || null,
      media: product.media,
      href: product.href || `/products/${product.slug}`,
    })
    save()
  }

  function clearRecentlyViewed() {
    products.value = []
    save()
  }

  return {
    recentlyViewed,
    recentCount,
    addProduct,
    clearRecentlyViewed,
  }
}
