<template>
  <article class="group flex h-full flex-col rounded-[1.2rem] border border-[#efe7da] bg-white p-2 shadow-[0_16px_38px_rgba(15,23,42,0.06)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_20px_42px_rgba(15,23,42,0.1)] sm:rounded-[1.6rem] sm:p-2.5">
    <div class="relative overflow-hidden rounded-[1rem] bg-[#f8f4ee] sm:rounded-[1.25rem]">
      <Link :href="product.href || `/products/${product.slug}`" class="block">
        <img
          v-if="product.media?.[0] || product.image"
          :src="product.media?.[0] || product.image"
          :alt="product.name"
          class="aspect-[0.82] w-full object-cover transition duration-300 group-hover:scale-[1.03]"
          loading="lazy"
        />
        <div v-else class="aspect-[0.82] w-full bg-gradient-to-br from-[#ffe5da] via-[#fff4e9] to-[#f1ecff]"></div>
      </Link>

      <div class="pointer-events-none absolute inset-x-1.5 top-1.5 flex items-start justify-between gap-1.5 sm:inset-x-2 sm:top-2 sm:gap-2">
        <div class="flex flex-col gap-1.5">
          <span v-if="product.badge" class="rounded-full bg-[#111111] px-2 py-1 text-[0.55rem] font-bold uppercase tracking-[0.12em] text-white shadow sm:px-2.5 sm:text-[0.62rem] sm:tracking-[0.15em]">
            {{ product.badge }}
          </span>
          <span v-if="product.urgencyLabel" class="rounded-full bg-[#fff2cc] px-2 py-1 text-[0.55rem] font-bold uppercase tracking-[0.12em] text-[#9a5b00] sm:px-2.5 sm:text-[0.62rem] sm:tracking-[0.14em]">
            {{ product.urgencyLabel }}
          </span>
        </div>
        <button
          type="button"
          class="pointer-events-auto inline-flex h-7 w-7 items-center justify-center rounded-full bg-white/88 text-slate-500 shadow-sm transition hover:bg-white hover:text-[#e0245e] sm:h-8 sm:w-8"
          :disabled="wishlistProcessing"
          @click.stop.prevent="addToWishlist"
        >
          <svg v-if="wishlisted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current text-[#e0245e]">
            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
          </svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 stroke-current fill-none" stroke-width="1.7">
            <path d="M12.1 20.55l-.1.1-.11-.1C7.14 16.24 4 13.39 4 9.5 4 6.42 6.42 4 9.5 4c1.74 0 3.41.81 4.6 2.1C15.09 4.81 16.76 4 18.5 4 21.58 4 24 6.42 24 9.5c0 3.89-3.14 6.74-7.9 11.05z"/>
          </svg>
        </button>
      </div>

      <div v-if="product.dealEndsAtLabel" class="absolute inset-x-0 bottom-0 bg-gradient-to-r from-[#ff6b35] to-[#ff8d63] px-2 py-1.5 text-[0.58rem] font-bold uppercase tracking-[0.12em] text-white sm:px-3 sm:py-2 sm:text-[0.68rem] sm:tracking-[0.16em]">
        {{ t('Ends in :time', { time: product.dealEndsAtLabel }) }}
      </div>
    </div>

    <div class="flex flex-1 flex-col px-0.5 pb-0.5 pt-2.5 sm:px-1 sm:pb-1 sm:pt-3">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <p class="line-clamp-2 text-[0.82rem] font-bold leading-4 text-slate-950 sm:text-[0.92rem] sm:leading-5">{{ product.name }}</p>
          <p v-if="product.category" class="mt-1 text-[0.68rem] font-medium uppercase tracking-[0.16em] text-slate-400">
            {{ product.category }}
          </p>
        </div>
        <span v-if="product.sectionLabel" class="shrink-0 rounded-full bg-[#f7f3eb] px-1.5 py-1 text-[0.52rem] font-bold uppercase tracking-[0.12em] text-slate-500 sm:px-2 sm:text-[0.58rem] sm:tracking-[0.16em]">
          {{ product.sectionLabel }}
        </span>
      </div>

      <div class="mt-2.5 flex items-baseline gap-1.5 sm:mt-3 sm:gap-2">
        <p class="text-[0.96rem] font-black tracking-[-0.03em] text-slate-950 sm:text-[1.05rem]">{{ displayPriceFormatted }}</p>
        <p v-if="hasDiscount" class="text-[0.72rem] font-semibold text-slate-400 line-through">{{ compareAtFormatted }}</p>
        <span v-if="hasDiscount" class="rounded-full bg-[#111111] px-1.5 py-0.5 text-[0.55rem] font-bold text-white sm:px-2 sm:text-[0.62rem]">
          -{{ discountPercent }}%
        </span>
      </div>

      <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[0.68rem] font-semibold text-slate-500 sm:mt-2 sm:gap-2 sm:text-[0.72rem]">
        <span v-if="rating" class="inline-flex items-center gap-1 text-slate-700">
          <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-[#ffb703]" fill="currentColor">
            <path d="M12 3.5l2.6 5.4 6 .9-4.3 4.1 1 5.8L12 16.9 6.7 19.7l1-5.8-4.3-4.1 6-.9L12 3.5z" />
          </svg>
          {{ rating }}
        </span>
        <span v-if="product.proofLabel">{{ product.proofLabel }}</span>
      </div>

      <p v-if="product.anchorLabel" class="mt-1.5 text-[0.66rem] font-semibold text-[#c55b24] sm:mt-2 sm:text-[0.72rem]">
        {{ product.anchorLabel }}
      </p>

      <div class="mt-auto flex gap-1.5 pt-3 sm:gap-2 sm:pt-4">
        <button
          type="button"
          class="flex-1 rounded-full bg-[#111111] px-2 py-2.5 text-[0.66rem] font-bold uppercase tracking-[0.1em] text-white transition hover:bg-[#2a2a2a] sm:px-3 sm:text-[0.76rem] sm:tracking-[0.14em]"
          @click.stop.prevent="isQuickAddOpen = true"
        >
          {{ t('Quick add') }}
        </button>
        <Link
          :href="product.href || `/products/${product.slug}`"
          class="inline-flex items-center justify-center rounded-full border border-[#e8dfd3] px-2.5 py-2.5 text-[0.66rem] font-bold uppercase tracking-[0.1em] text-slate-900 transition hover:border-slate-400 sm:px-3 sm:text-[0.76rem] sm:tracking-[0.14em]"
        >
          {{ t('View') }}
        </Link>
      </div>
    </div>

    <ProductQuickAddSheet
      :is-open="isQuickAddOpen"
      :product="product"
      :currency="currency"
      @close="isQuickAddOpen = false"
    />
  </article>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'
import { useUserPreferences } from '@/composables/useUserPreferences.js'
import ProductQuickAddSheet from '@/Components/ProductQuickAddSheet.vue'

const props = defineProps({
  product: { type: Object, required: true },
  currency: { type: String, default: 'USD' },
})

const { t } = useTranslations()
const { currentCurrency, formatCurrency, convertCurrency } = useUserPreferences()
const displayCurrency = computed(() => currentCurrency.value || props.currency)
const wishlisted = ref(Boolean(props.product.is_in_wishlist))
const wishlistProcessing = ref(false)
const isQuickAddOpen = ref(false)

const basePrice = computed(() => Number(props.product.price ?? 0))
const compareAt = computed(() => Number(props.product.compare_at_price ?? 0))
const hasDiscount = computed(() => compareAt.value > basePrice.value)
const discountPercent = computed(() => {
  if (!hasDiscount.value || compareAt.value <= 0) return 0
  return Math.max(1, Math.round((1 - basePrice.value / compareAt.value) * 100))
})

const displayPriceFormatted = computed(() =>
  formatCurrency(convertCurrency(basePrice.value, 'USD', displayCurrency.value), displayCurrency.value)
)
const compareAtFormatted = computed(() =>
  formatCurrency(convertCurrency(compareAt.value, 'USD', displayCurrency.value), displayCurrency.value)
)
const rating = computed(() => props.product.rating ?? null)

const addToWishlist = () => {
  wishlistProcessing.value = true

  router.post(
    '/wishlist',
    { product_id: props.product.id },
    {
      preserveScroll: true,
      onSuccess: () => {
        wishlisted.value = true
      },
      onFinish: () => {
        wishlistProcessing.value = false
      },
    }
  )
}
</script>
