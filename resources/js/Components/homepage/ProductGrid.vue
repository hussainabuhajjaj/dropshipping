<template>
  <section class="space-y-4">
    <div class="flex flex-col gap-2.5 md:flex-row md:items-end md:justify-between">
      <div>
        <p class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#ff6b35]">{{ subtitle }}</p>
        <h2 class="text-[1.4rem] font-black tracking-[-0.03em] text-slate-950 sm:text-2xl">{{ title }}</h2>
      </div>

      <div v-if="pills.length" class="flex gap-1.5 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:gap-2">
        <button
          v-for="pill in pills"
          :key="pill.key"
          type="button"
          class="shrink-0 rounded-full px-2.5 py-1.5 text-[0.66rem] font-bold uppercase tracking-[0.12em] transition sm:px-3 sm:py-2 sm:text-[0.72rem] sm:tracking-[0.15em]"
          :class="activePill === pill.key ? 'bg-[#111111] text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)]' : 'bg-white text-slate-500 ring-1 ring-[#e7ddcf] hover:text-slate-900'"
          @click="setActivePill(pill.key)"
        >
          {{ pill.label }}
        </button>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-2.5 sm:gap-3 lg:grid-cols-4 xl:grid-cols-5">
      <ProductCard
        v-for="product in visibleItems"
        :key="product.id"
        :product="product"
        :currency="currency"
      />

      <div
        v-if="isLoading"
        v-for="index in 4"
        :key="`skeleton-${index}`"
        class="overflow-hidden rounded-[1.6rem] border border-[#efe7da] bg-white p-2.5 shadow-[0_16px_38px_rgba(15,23,42,0.04)]"
      >
        <div class="aspect-[0.82] animate-pulse rounded-[1.25rem] bg-[#f3eee7]"></div>
        <div class="space-y-2 px-1 pb-1 pt-3">
          <div class="h-4 w-2/3 animate-pulse rounded-full bg-[#f3eee7]"></div>
          <div class="h-3 w-full animate-pulse rounded-full bg-[#f3eee7]"></div>
          <div class="h-3 w-4/5 animate-pulse rounded-full bg-[#f3eee7]"></div>
          <div class="h-10 animate-pulse rounded-full bg-[#efe7da]"></div>
        </div>
      </div>
    </div>

    <div ref="sentinel" class="h-4"></div>
  </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import ProductCard from '@/Components/homepage/ProductCard.vue'

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, required: true },
  products: { type: Array, default: () => [] },
  currency: { type: String, default: 'USD' },
  pills: { type: Array, default: () => [] },
})

const sentinel = ref(null)
const observer = ref(null)
const activePill = ref('all')
const visibleCount = ref(10)
const isLoading = ref(false)

const filteredProducts = computed(() => {
  if (activePill.value === 'all') return props.products
  return props.products.filter((product) => product.feedTag === activePill.value)
})

const visibleItems = computed(() => filteredProducts.value.slice(0, visibleCount.value))
const canLoadMore = computed(() => visibleCount.value < filteredProducts.value.length)

const loadMore = () => {
  if (!canLoadMore.value || isLoading.value) return
  isLoading.value = true
  window.setTimeout(() => {
    visibleCount.value += 8
    isLoading.value = false
  }, 340)
}

const connectObserver = async () => {
  await nextTick()
  if (typeof window === 'undefined' || !sentinel.value) return

  observer.value?.disconnect()
  observer.value = new IntersectionObserver(
    (entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        loadMore()
      }
    },
    { rootMargin: '260px 0px 260px 0px' }
  )

  observer.value.observe(sentinel.value)
}

const setActivePill = (key) => {
  activePill.value = key
}

watch(
  () => [props.products, activePill.value],
  async () => {
    visibleCount.value = 10
    await connectObserver()
  },
  { deep: true }
)

onMounted(connectObserver)
onBeforeUnmount(() => observer.value?.disconnect())
</script>
