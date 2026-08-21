<template>
  <div class="group relative rounded-lg bg-white p-2 transition hover:-translate-y-0.5 hover:shadow-lg" :class="featured ? 'sm:col-span-2 sm:row-span-2' : ''">
    <Link :href="product.href || `/products/${product.slug}`" class="block">
      <div
        class="relative overflow-hidden rounded-lg bg-[#f8f7f5] ring-1 ring-[#eee6da] transition"
        :class="featured ? 'h-full' : ''"
      >
        <div
          v-if="discount"
          class="absolute left-1.5 top-1.5 z-10 rounded bg-[#f59e0b] px-1.5 py-1 text-[0.55rem] font-bold text-white"
        >
          -{{ discount }}%
        </div>
        <div
          v-if="product.review_rating"
          class="absolute right-1.5 top-1.5 z-10 flex items-center gap-0.5 rounded bg-white/90 px-1 py-0.5 text-[0.5rem] font-semibold text-slate-700 shadow-xs backdrop-blur"
        >
          <svg class="h-2.5 w-2.5 text-amber-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3.5l2.6 5.4 6 .9-4.3 4.1 1 5.8L12 16.9 6.7 19.7l1-5.8-4.3-4.1 6-.9L12 3.5z"/></svg>
          {{ product.review_rating }}
        </div>
        <div
          v-if="featured"
          class="absolute right-1.5 top-7 z-10 rounded bg-slate-950 px-1.5 py-0.5 text-[0.45rem] font-bold uppercase tracking-wider text-white shadow-xs"
        >
          {{ t("Featured") }}
        </div>
        <img
          v-if="product.media?.[0] || product.image"
          :src="product.media?.[0] || product.image"
          :alt="product.name"
          class="w-full object-cover transition duration-500 group-hover:scale-105"
          :class="featured ? 'aspect-[1/1] sm:aspect-[4/3]' : 'aspect-[0.75]'"
          loading="lazy"
        />
        <div v-else class="aspect-[0.75] bg-gradient-to-br from-slate-100 to-slate-200" />

        <div
          class="absolute inset-x-0 bottom-0 flex translate-y-full items-center justify-center bg-gradient-to-t from-black/60 to-transparent px-3 pb-3 pt-8 transition-transform duration-300 group-hover:translate-y-0"
        >
          <button
            type="button"
            class="w-full rounded-md bg-[#f59e0b] py-2 text-[0.6rem] font-bold text-slate-950 shadow-sm transition hover:bg-[#d97706] active:scale-95"
            @click.prevent="openQuickAdd"
          >
            {{ t('Quick add') }}
          </button>
        </div>
      </div>

      <div class="mt-2 space-y-0.5">
        <p
          class="font-semibold text-slate-700 leading-snug line-clamp-2"
          :class="featured ? 'text-xs sm:text-sm' : 'text-[0.72rem]'"
        >{{ product.name }}</p>
        <div class="flex items-center gap-1.5">
          <span class="font-black text-[#f59e0b]" :class="featured ? 'text-base' : 'text-sm'">{{ priceFormatted }}</span>
          <span v-if="compareAtFormatted" class="text-slate-400 line-through" :class="featured ? 'text-xs' : 'text-[0.6rem]'">{{ compareAtFormatted }}</span>
        </div>
        <div v-if="product.review_count" class="flex items-center gap-1 text-[0.55rem] text-slate-400">
          <span class="flex items-center gap-0.5">
            <svg v-for="n in 5" :key="n" class="h-2 w-2" :class="n <= Math.round(product.review_rating ?? 0) ? 'text-amber-400' : 'text-slate-200'" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3.5l2.6 5.4 6 .9-4.3 4.1 1 5.8L12 16.9 6.7 19.7l1-5.8-4.3-4.1 6-.9L12 3.5z"/></svg>
          </span>
          <span>({{ product.review_count }})</span>
        </div>
      </div>
    </Link>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useUserPreferences } from '@/composables/useUserPreferences.js'
import { useTranslations } from '@/i18n'

const props = defineProps({
  product: { type: Object, required: true },
  currency: { type: String, default: 'USD' },
  featured: { type: Boolean, default: false },
})

const emit = defineEmits(['quick-add'])

const { t } = useTranslations()
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

const openQuickAdd = () => {
  emit('quick-add', props.product)
}
</script>
