<template>
  <article class="card group flex h-full flex-col justify-between p-4 transition hover:-translate-y-0.5 hover:shadow-lg/40">
    <div class="relative aspect-[4/3] w-full overflow-hidden rounded-xl bg-gradient-to-br from-[#dfff86]/60 via-white to-[#29ab87]/10">
      <img
        v-if="product.media?.[0]"
        :src="product.media[0]"
        :alt="product.name"
        class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
        loading="lazy"
      />
      <span v-if="hasDiscount" class="badge-accent absolute right-3 top-3">
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
        @click="addToWishlist"
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
        <div v-if="product.variants?.length > 1" class="space-y-1 text-xs text-slate-500">
          <p class="text-[0.6rem] uppercase tracking-[0.2em] text-slate-400">{{ t('Variant') }}</p>
          <select
            v-model="selectedVariantId"
            class="input-base text-xs"
          >
            <option
              v-for="variant in product.variants"
              :key="variant.id"
              :value="variant.id"
            >
              {{ variant.title }} — {{ currency }} {{ Number(variant.price ?? 0).toFixed(2) }}
            </option>
          </select>
        </div>
      </div>

    <div class="flex flex-col gap-2">
      <div>
        <div class="text-lg font-semibold text-slate-900">
          {{ currency }} {{ displayPrice.toFixed(2) }}
        </div>
        <div v-if="hasDiscount" class="text-xs text-slate-400 line-through">
          {{ currency }} {{ compareAt.toFixed(2) }}
        </div>
      </div>
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <label class="text-[0.6rem] font-semibold uppercase tracking-[0.2em] text-slate-400">
            {{ t('Qty') }}
          </label>
          <input
            v-model.number="form.quantity"
            type="number"
            min="1"
            class="input-base w-16 text-xs"
          />
        </div>
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

  <Transition
    enter-active-class="transition duration-150 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition duration-100 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="variantModalOpen"
      class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/60 px-4 py-6"
    >
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h3 class="mb-3 text-lg font-semibold text-slate-900">{{ t('Select a variant') }}</h3>
        <p class="mb-4 text-xs text-slate-500">
          {{ t('Confirm the variant you want to add before checkout. Shipping is estimated after an address is added.') }}
        </p>
        <div class="space-y-3 text-sm text-slate-700">
          <label class="block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Variant') }}</label>
          <select
            v-model="selectedVariantId"
            class="input-base w-full"
          >
            <option
              v-for="variant in product.variants"
              :key="variant.id"
              :value="variant.id"
            >
              {{ variant.title }} — {{ currency }} {{ Number(variant.price ?? 0).toFixed(2) }}
            </option>
          </select>
        </div>
        <div class="mt-6 flex items-center justify-between">
          <button
            type="button"
            class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500 transition hover:text-slate-900"
            @click="variantModalOpen = false"
          >
            {{ t('Cancel') }}
          </button>
          <button
            type="button"
            class="rounded-full bg-[#29ab87] px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#2aaa8a]"
            @click="confirmQuickAdd"
          >
            {{ t('Add to cart') }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
  </article>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const props = defineProps({
  product: { type: Object, required: true },
  currency: { type: String, default: 'USD' },
})

const { t } = useTranslations()
const initialVariantId = props.product.default_variant_id ?? props.product.variants?.[0]?.id ?? null
const selectedVariantId = ref(initialVariantId)
const selectedVariant = computed(() => {
  if (! props.product.variants?.length) {
    return null
  }
  return props.product.variants.find((variant) => variant.id === selectedVariantId.value) ?? props.product.variants[0]
})
const displayPrice = computed(() => Number(selectedVariant.value?.price ?? props.product.price ?? 0))
const compareAt = computed(() => Number(selectedVariant.value?.compare_at_price ?? 0))
const hasDiscount = computed(() => compareAt.value > displayPrice.value)
const discountPercent = computed(() => {
  if (! hasDiscount.value) {
    return 0
  }
  return Math.round((1 - displayPrice.value / compareAt.value) * 100)
})
const rating = computed(() => props.product.rating ?? null)
const ratingCount = computed(() => props.product.rating_count ?? null)

const form = useForm({
  product_id: props.product.id,
  variant_id: selectedVariantId.value,
  quantity: 1,
})

watch(selectedVariantId, (value) => {
  form.variant_id = value
})

const variantModalOpen = ref(false)
const wishlistProcessing = ref(false)
const wishlisted = ref(Boolean(props.product.is_in_wishlist))

const addToCart = () => {
  if (! props.product.is_active) {
    return
  }
  form.product_id = props.product.id
  form.post('/cart', { preserveScroll: true })
}

// Removed quick add logic. Add to cart only from product details page.

const addToWishlist = () => {
  if (wishlistProcessing.value) {
    return
  }

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

const confirmQuickAdd = () => {
  variantModalOpen.value = false
  addToCart()
}
</script>
