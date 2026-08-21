<template>
  <Link :href="product.href || `/products/${product.slug}`" class="group block rounded-lg bg-white p-2 transition hover:-translate-y-0.5 hover:shadow-lg">
    <div class="relative overflow-hidden rounded-lg bg-[#f8f7f5] ring-1 ring-[#eee6da]">
      <div
        v-if="discount"
        class="absolute left-1.5 top-1.5 z-10 rounded bg-[#f59e0b] px-1.5 py-0.5 text-[0.6rem] font-bold text-white"
      >
        -{{ discount }}%
      </div>
      <img
        v-if="product.media?.[0] || product.image"
        :src="product.media?.[0] || product.image"
        :alt="product.name"
        class="aspect-[0.82] w-full object-cover transition duration-300 group-hover:scale-105"
        loading="lazy"
      />
      <div v-else class="aspect-[0.82] bg-gradient-to-br from-slate-100 to-slate-200" />
      <span class="absolute bottom-2 right-2 inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-950 shadow-sm transition group-hover:border-[#f59e0b] group-hover:text-[#d97706]">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25h10.8c.56-1.16 1.08-2.35 1.55-3.58.34-.88-.3-1.82-1.24-1.82H6.07" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21a.75.75 0 100-1.5.75.75 0 000 1.5zm9.75 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
        </svg>
      </span>
    </div>
    <p class="mt-2 text-[0.72rem] font-semibold leading-tight text-slate-700 line-clamp-2">{{ product.name }}</p>
    <div class="mt-1 flex items-center gap-1.5">
      <span class="text-sm font-black text-[#f59e0b]">{{ priceFormatted }}</span>
      <span v-if="compareAtFormatted" class="text-[0.6rem] text-slate-400 line-through">{{ compareAtFormatted }}</span>
    </div>
  </Link>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useUserPreferences } from '@/composables/useUserPreferences.js'

const props = defineProps({
  product: { type: Object, required: true },
  currency: { type: String, default: 'USD' },
})

const { currentCurrency, formatCurrency, convertCurrency } = useUserPreferences()

const displayCurrency = computed(() => currentCurrency.value || props.currency)

const basePrice = computed(() => Number(props.product.price ?? 0))
const compareAt = computed(() => Number(props.product.compare_at_price ?? 0))

const priceFormatted = computed(() =>
  formatCurrency(convertCurrency(basePrice.value, 'USD', displayCurrency.value), displayCurrency.value)
)

const compareAtFormatted = computed(() => {
  if (!compareAt.value) return ''
  return formatCurrency(convertCurrency(compareAt.value, 'USD', displayCurrency.value), displayCurrency.value)
})

const discount = computed(() => {
  if (!compareAt.value || !basePrice.value) return 0
  const convertedCurrent = convertCurrency(basePrice.value, 'USD', displayCurrency.value)
  const convertedOriginal = convertCurrency(compareAt.value, 'USD', displayCurrency.value)
  if (!convertedOriginal || !convertedCurrent) return 0
  return Math.round(((convertedOriginal - convertedCurrent) / convertedOriginal) * 100)
})
</script>
