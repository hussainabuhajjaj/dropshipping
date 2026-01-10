<template>
  <Link :href="`/products/${product.slug}`" class="block group">
    <article class="card flex h-full flex-col justify-between p-4 transition hover:-translate-y-0.5 hover:shadow-lg/40 cursor-pointer">
      <div class="relative aspect-[4/3] w-full overflow-hidden rounded-xl bg-gradient-to-br from-[#dfff86]/60 via-white to-[#29ab87]/10">
        <img
          v-if="product.media?.[0]"
          :src="product.media[0]"
          :alt="product.name"
          class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
          loading="lazy"
        />
        <span v-if="productPromotion" class="badge-accent absolute right-3 top-3">
          {{ productPromotion.name }}
          <span v-if="productPromotion.value_type === 'percentage'">-{{ productPromotion.value }}%</span>
          <span v-else-if="productPromotion.value_type === 'fixed'">-{{ productPromotion.value }} {{ currency }}</span>
        </span>
        <span v-else-if="hasDiscount" class="badge-accent absolute right-3 top-3">
          {{ t('Save :percent%', { percent: discountPercent }) }}
        </span>
        <button
          type="button"
          class="absolute left-3 top-3 rounded-full bg-white/70 p-2 text-xs transition hover:bg-white"
          :class="{
            'text-[#e0245e]': wishlisted.value,
            'text-slate-400': !wishlisted.value,
          }"
          :disabled="wishlistProcessing.value"
          @click.stop="addToWishlist"
        >
          <svg v-if="wishlisted.value" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
          </svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 stroke-current fill-none" stroke-width="1.5">
            <path d="M12.1 20.55l-.1.1-.11-.1C7.14 16.24 4 13.39 4 9.5 4 6.42 6.42 4 9.5 4c1.74 0 3.41.81 4.6 2.1C15.09 4.81 16.76 4 18.5 4 21.58 4 24 6.42 24 9.5c0 3.89-3.14 6.74-7.9 11.05z"/>
          </svg>
        </button>
      </div>

    <div class="mt-4 flex flex-1 flex-col justify-between gap-4">
      <div class="space-y-2">
        <p v-if="product.category" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
          {{ product.category }}
        </p>
        <h3 class="text-base font-semibold leading-snug text-slate-900 line-clamp-2">
          <Link :href="`/products/${product.slug}`" class="hover:text-[#29ab87]">
            {{ product.name }}
          </Link>
        </h3>
        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
          <span v-if="rating" class="inline-flex items-center gap-1">
            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-slate-500" fill="currentColor">
              <path d="M12 3.5l2.6 5.4 6 .9-4.3 4.1 1 5.8L12 16.9 6.7 19.7l1-5.8-4.3-4.1 6-.9L12 3.5z" />
            </svg>
            {{ rating }}<span v-if="ratingCount"> ({{ ratingCount }})</span>
          </span>
          <span>{{ product.is_active ? t('In stock') : t('Unavailable') }}</span>
        </div>
      </div>

    <div class="flex flex-col gap-2">
      <div>
        <div class="text-lg font-semibold text-slate-900">
          {{ displayPriceFormatted }}
        </div>
        <div v-if="hasDiscount" class="text-xs text-slate-400 line-through">
          {{ compareAtFormatted }}
        </div>
      </div>
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <Link
            :href="`/products/${product.slug}`"
            class="btn-secondary px-3 py-2 text-xs bg-[#29ab87] text-white hover:bg-[#2aaa8a]"
          >
            {{ t('View product') }}
          </Link>
          <span
            class="text-xs font-semibold uppercase tracking-[0.2em] text-[#29ab87]"
            v-if="wishlistProcessing.value"
          >
            {{ t('Saving...') }}
          </span>
        </div>
      </div>
      <p class="text-[0.65rem] text-slate-500">
        {{ t('Shipping calculated after address entry; final totals refresh during checkout.') }}
      </p>
    </div>
  </div>

  <!-- Variant selection modal removed for cleaner UI -->
    </article>
  </Link>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'
import { convertCurrency, formatCurrency } from '@/utils/currency.js'

const props = defineProps({
  product: { type: Object, required: true },
  currency: { type: String, default: 'USD' },
  promotions: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const wishlisted = ref(Boolean(props.product.is_in_wishlist))
const wishlistProcessing = ref(false)

// Promotion logic
const productPromotion = computed(() => {
  if (!props.promotions?.length) return null
  return props.promotions.find(p =>
    (p.targets || []).some(t => t.target_type === 'product' && (t.target_id == props.product.id || t.target_value == props.product.name))
  )
})

// Price logic
const displayPrice = computed(() => Number(props.product.price ?? 0))
const compareAt = computed(() => Number(props.product.compare_at_price ?? 0))
const hasDiscount = computed(() => compareAt.value > displayPrice.value)
const discountPercent = computed(() => {
  if (!hasDiscount.value) return 0
  return Math.round((1 - displayPrice.value / compareAt.value) * 100)
})
const displayPriceFormatted = computed(() =>
  formatCurrency(convertCurrency(displayPrice.value, 'USD', props.currency), props.currency)
)
const compareAtFormatted = computed(() =>
  formatCurrency(convertCurrency(compareAt.value, 'USD', props.currency), props.currency)
)
const rating = computed(() => props.product.rating ?? null)
const ratingCount = computed(() => props.product.rating_count ?? null)

// Wishlist logic
const addToWishlist = () => {
  if (wishlistProcessing.value) return
  wishlistProcessing.value = true
  if (wishlisted.value) {
    router.delete(`/account/wishlist/${props.product.id}`, {
      preserveScroll: true,
      onFinish: () => {
        wishlistProcessing.value = false
        wishlisted.value = false
      },
    })
    return
  }
  router.post(
    '/account/wishlist',
    { product_id: props.product.id },
    {
      preserveScroll: true,
      onFinish: () => {
        wishlistProcessing.value = false
        wishlisted.value = true
      },
    },
  )
}
</script>
