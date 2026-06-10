<template>
  <Link :href="product.href || `/products/${product.slug}`" class="group block">
    <div class="relative overflow-hidden bg-white">
      <div
        v-if="discount"
        class="absolute left-0 top-0 z-10 bg-red-500 px-1.5 py-0.5 text-[0.6rem] font-bold text-white"
      >
        -{{ discount }}%
      </div>
      <img
        v-if="product.media?.[0] || product.image"
        :src="product.media?.[0] || product.image"
        :alt="product.name"
        class="aspect-[0.75] w-full object-cover transition duration-300 group-hover:scale-105"
        loading="lazy"
      />
      <div v-else class="aspect-[0.75] bg-gradient-to-br from-slate-100 to-slate-200" />
    </div>
    <p class="mt-1.5 text-[0.65rem] font-medium text-slate-700 leading-tight line-clamp-2">{{ product.name }}</p>
    <div class="mt-0.5 flex items-center gap-1.5">
      <span class="text-xs font-bold text-red-500">{{ priceFormatted }}</span>
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
