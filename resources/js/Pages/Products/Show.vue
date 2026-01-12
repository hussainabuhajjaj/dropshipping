<template>
  <StorefrontLayout>
    <Head :title="metaTitle">
      <meta name="description" head-key="description" :content="metaDescription" />
    </Head>
    <div class="grid gap-10 lg:grid-cols-[1.1fr,0.9fr]">
        <div class="space-y-4">
          <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
            <img
              v-if="selectedImage"
              :src="selectedImage"
              :alt="product.name"
              class="h-full w-full object-cover"
          />
          <div v-else class="flex aspect-[4/3] items-center justify-center text-xs text-slate-400">
            {{ t('Image coming soon') }}
          </div>
          <div class="absolute inset-x-0 bottom-3 flex items-center justify-center gap-2">
            <button
              v-for="(image, idx) in product.media"
              :key="idx"
              type="button"
              class="h-2 w-2 rounded-full border border-white/60 bg-white/60 transition"
              :class="image === selectedImage ? 'scale-110 border-slate-900 bg-slate-900' : 'hover:bg-white'"
              @click="selectedImage = image"
            />
          </div>
        </div>
        <div class="grid grid-cols-5 gap-3 sm:grid-cols-6">
          <button
            v-for="(image, idx) in product.media"
            :key="idx"
            type="button"
            class="aspect-square overflow-hidden rounded-xl border border-transparent bg-slate-50 transition"
            :class="image === selectedImage ? 'border-slate-900 ring-2 ring-slate-200' : 'hover:border-slate-300'"
            @click="selectedImage = image"
          >
            <img :src="image" :alt="product.name" class="h-full w-full object-cover" />
          </button>
        </div>

        <div v-if="productVideos.length" class="space-y-3">
          <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Videos') }}</h2>
          <div class="grid gap-3">
            <video
              v-for="(video, idx) in productVideos"
              :key="idx"
              class="w-full rounded-xl border border-slate-200 bg-black/90"
              controls
              preload="metadata"
              playsinline
            >
              <source :src="video" />
            </video>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="space-y-2">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-400">
            {{ product.category ?? 'Simbazu' }}
            <span v-if="productPromotion" class="ml-2 px-2 py-0.5 rounded bg-yellow-200 text-yellow-900 font-bold">Promo!</span>
          </p>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900">{{ product.name }}</h1>
          <p class="text-sm text-slate-600">{{ descriptionText }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <span class="text-2xl font-semibold text-slate-900">
            {{ currency }} {{ displayPrice.toFixed(2) }}
          </span>
          <span v-if="selectedVariant?.compare_at_price" class="text-sm text-slate-400 line-through">
            {{ currency }} {{ Number(selectedVariant.compare_at_price).toFixed(2) }}
          </span>
          <span v-if="stockBadge.label" :class="stockBadge.class" class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold">
            <span class="h-2 w-2 rounded-full" :class="stockBadge.dot" />
            {{ stockBadge.label }}
          </span>
          <span class="text-xs text-slate-500">{{ t('Ships in :days days', { days: product.lead_time_days ?? 7 }) }}</span>
          <span v-if="reviewSummary.count" class="inline-flex items-center gap-1 text-xs text-slate-600">
            <svg viewBox="0 0 24 24" class="h-4 w-4 text-slate-500" fill="currentColor">
              <path d="M12 3.5l2.6 5.4 6 .9-4.3 4.1 1 5.8L12 16.9 6.7 19.7l1-5.8-4.3-4.1 6-.9L12 3.5z" />
            </svg>
            {{ reviewSummary.average }} ({{ reviewSummary.count }})
          </span>
        </div>

        <div class="card-muted p-4 text-xs text-slate-600">
          {{ t('Customs and duties are shown before payment. Delivery timelines begin after dispatch and local clearance.') }}
        </div>

        <div v-if="product.variants?.length" class="space-y-3">
          <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Variant') }}</label>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="variant in product.variants"
              :key="variant.id"
              type="button"
              class="rounded-full border px-3 py-1 text-xs font-semibold transition"
              :class="variant.id === selectedVariantId ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-800 hover:border-slate-300'"
              @click="selectVariant(variant.id)"
            >
              {{ variant.title }}
            </button>
          </div>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
          <div class="flex flex-wrap items-center gap-3">
            <div class="inline-flex items-center rounded-full border border-slate-200 px-2 py-1">
              <button
                type="button"
                class="h-8 w-8 rounded-full text-slate-600 transition hover:bg-slate-100"
                @click="decrementQty"
              >
                -
              </button>
              <input
                v-model.number="form.quantity"
                type="number"
                min="1"
                class="w-14 border-0 bg-transparent text-center text-sm font-semibold text-slate-900 focus:ring-0"
              />
              <button
                type="button"
                class="h-8 w-8 rounded-full text-slate-600 transition hover:bg-slate-100"
                @click="incrementQty"
              >
                +
              </button>
            </div>
            <button type="submit" class="btn-primary" :disabled="isOutOfStock">
              {{ form.processing ? t('Adding...') : isOutOfStock ? t('Out of stock') : t('Add to cart') }}
            </button>
            <a
              :href="whatsappLink"
              class="btn-secondary"
              target="_blank"
              rel="noreferrer"
            >
              {{ t('WhatsApp to buy') }}
            </a>
          </div>
          <p v-if="successMessage" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
            {{ successMessage }}
          </p>
        </form>

        <div class="grid gap-3 text-xs text-slate-600 sm:grid-cols-3">
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
            <svg viewBox="0 0 24 24" class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7.5 4.5v9L12 21l-7.5-4.5v-9L12 3z" />
            </svg>
            {{ t('Tracked delivery') }}
          </div>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
            <svg viewBox="0 0 24 24" class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4l2 2M6.5 5.5h11a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2z" />
            </svg>
            {{ t('24 to 48h tracking') }}
          </div>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
            <svg viewBox="0 0 24 24" class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6l7-3z" />
            </svg>
            {{ t('Secure payment') }}
          </div>
        </div>

        <div class="space-y-3">
          <div class="flex border-b border-slate-200 text-sm font-semibold text-slate-600">
            <button
              type="button"
              class="border-b-2 px-4 py-2 transition"
              :class="activeTab === 'description' ? 'border-slate-900 text-slate-900' : 'border-transparent'"
              @click="activeTab = 'description'"
            >
              {{ t('Description') }}
            </button>
            <button
              type="button"
              class="border-b-2 px-4 py-2 transition"
              :class="activeTab === 'specs' ? 'border-slate-900 text-slate-900' : 'border-transparent'"
              @click="activeTab = 'specs'"
            >
              {{ t('Specs') }}
            </button>
            <button
              type="button"
              class="border-b-2 px-4 py-2 transition"
              :class="activeTab === 'reviews' ? 'border-slate-900 text-slate-900' : 'border-transparent'"
              @click="activeTab = 'reviews'"
            >
              {{ t('Reviews (:count)', { count: reviewSummary.count }) }}
            </button>
          </div>

          <div v-if="activeTab === 'description'" class="text-sm text-slate-600">
            <div v-if="descriptionHtml" class="space-y-3" v-html="descriptionHtml"></div>
            <p v-else class="text-slate-500">{{ t('Details coming soon.') }}</p>
            <div v-if="reviewHighlights.length" class="mt-6 grid gap-3 sm:grid-cols-3">
              <div
                v-for="highlight in reviewHighlights"
                :key="`${highlight.author}-${highlight.title}`"
                class="rounded-xl border border-slate-100 bg-white p-4 text-xs text-slate-600"
              >
                <p class="font-semibold text-slate-900">{{ highlight.title || t('Verified review') }}</p>
                <p class="mt-2 line-clamp-3 text-slate-600">{{ highlight.body }}</p>
                <p class="mt-3 text-[0.7rem] text-slate-500">{{ highlight.author }}</p>
              </div>
            </div>
          </div>
          <div v-else-if="activeTab === 'specs'" class="space-y-3 text-sm text-slate-600">
            <div v-if="specEntries.length" class="grid gap-2">
              <div v-for="(value, key) in specEntries" :key="key" class="flex justify-between border-b border-slate-100 pb-2">
                <span class="text-slate-500">{{ formatSpecKey(key) }}</span>
                <span class="font-semibold text-slate-900">{{ value }}</span>
              </div>
            </div>
            <p v-else class="text-slate-500">{{ t('Specs will appear once the supplier confirms details.') }}</p>
            <div class="text-xs text-slate-500">
              {{ t('Delivery estimate: :days days · Customs shown before checkout.', { days: product.lead_time_days ?? 7 }) }}
            </div>
          </div>
          <div v-else class="space-y-4 text-sm text-slate-600">
            <div
              v-if="authUser && reviewableItems.length"
              class="rounded-xl border border-slate-100 bg-white p-4"
            >
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Verified review') }}</p>
              <p class="mt-2 text-sm text-slate-600">
                {{ t('Share feedback on items delivered to you.') }}
              </p>
              <form class="mt-4 grid gap-3" @submit.prevent="submitReview">
                <div v-if="reviewableItems.length > 1">
                  <label class="text-xs font-semibold text-slate-600">{{ t('Delivered order') }}</label>
                  <select v-model="reviewForm.order_item_id" class="input-base mt-1 w-full">
                    <option v-for="item in reviewableItems" :key="item.id" :value="item.id">
                      {{ item.order_number ? t('Order #:number', { number: item.order_number }) : t('Delivered order') }} ·
                      {{ formatOrderDate(item.ordered_at) }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600">{{ t('Rating') }}</label>
                  <select v-model="reviewForm.rating" class="input-base mt-1 w-full">
                    <option v-for="rating in [5,4,3,2,1]" :key="rating" :value="rating">
                      {{ t(':count stars', { count: rating }) }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600">{{ t('Title') }}</label>
                  <input v-model="reviewForm.title" type="text" class="input-base mt-1 w-full" :placeholder="t('Great quality')" />
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600">{{ t('Review') }}</label>
                  <textarea v-model="reviewForm.body" rows="3" class="input-base mt-1 w-full" :placeholder="t('Tell us how it arrived.')" />
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600">{{ t('Photos (optional)') }}</label>
                  <input
                    type="file"
                    multiple
                    accept="image/*"
                    class="input-base mt-1 w-full"
                    @change="onImagesChange"
                  />
                  <p class="mt-1 text-[0.7rem] text-slate-500">{{ t('Up to 3 images, 3MB each') }}</p>
                  <p v-if="imagesError" class="mt-1 text-[0.75rem] text-red-600">{{ imagesError }}</p>
                </div>
                <button type="submit" class="btn-primary w-full sm:w-auto" :disabled="reviewForm.processing">
                  {{ reviewForm.processing ? t('Submitting...') : t('Submit review') }}
                </button>
                <p v-if="reviewNotice" class="text-xs text-emerald-600">{{ reviewNotice }}</p>
              </form>
            </div>
            <div v-else-if="authUser" class="rounded-xl border border-slate-100 bg-white p-4 text-xs text-slate-500">
              {{ t('Reviews unlock after delivery. Check back once your order arrives.') }}
            </div>
            <div v-else class="rounded-xl border border-slate-100 bg-white p-4 text-xs text-slate-500">
              {{ t('Sign in to leave a verified review after delivery.') }}
            </div>
            <div v-if="reviewSummary.count" class="rounded-xl border border-slate-100 bg-white p-4">
              <div class="flex items-center gap-4">
                <div>
                  <div class="text-2xl font-semibold text-slate-900">{{ reviewSummary.average }}</div>
                  <div class="text-xs text-slate-500">{{ t('Average rating') }}</div>
                </div>
                <div class="flex-1 space-y-1">
                  <div
                    v-for="rating in [5,4,3,2,1]"
                    :key="rating"
                    class="flex items-center gap-2 text-xs"
                  >
                    <span class="w-8 text-slate-500">{{ rating }}★</span>
                    <div class="h-2 flex-1 rounded-full bg-slate-100">
                      <div
                        class="h-2 rounded-full bg-slate-900"
                        :style="{ width: `${reviewBarWidth(rating)}%` }"
                      />
                    </div>
                    <span class="w-8 text-right text-slate-500">{{ reviewSummary.breakdown[rating] || 0 }}</span>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="reviewsState.length" class="space-y-4">
              <div v-for="review in reviewsState" :key="review.id" class="rounded-xl border border-slate-100 bg-white p-4">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <p class="text-sm font-semibold text-slate-900">{{ review.author }}</p>
                    <span v-if="review.verified_purchase" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-[0.7rem] font-semibold text-emerald-700">
                      <span class="h-2 w-2 rounded-full bg-emerald-500" />
                      {{ t('Verified purchase') }}
                    </span>
                  </div>
                  <span class="text-xs text-slate-500">{{ formatDate(review.created_at) }}</span>
                </div>
                <div class="mt-1 flex items-center gap-1 text-xs text-slate-600">
                  <span v-for="n in 5" :key="n" class="text-slate-300" :class="n <= review.rating ? 'text-slate-900' : ''">
                    ★
                  </span>
                  <span class="ml-2">{{ review.rating }}/5</span>
                </div>
                <p v-if="review.title" class="mt-2 text-sm font-semibold text-slate-900">{{ review.title }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ review.body }}</p>

                <div v-if="review.images?.length" class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                  <a
                    v-for="(image, idx) in review.images"
                    :key="idx"
                    :href="image"
                    target="_blank"
                    rel="noreferrer"
                    class="block overflow-hidden rounded-lg border border-slate-100"
                  >
                    <img :src="image" :alt="review.title || review.author" class="h-28 w-full object-cover" />
                  </a>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-600">
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 font-semibold transition hover:border-slate-300"
                    :class="isReviewVoted(review.id) ? 'bg-slate-50 text-slate-500' : 'bg-white text-slate-800'
                    "
                    :disabled="isReviewVoted(review.id) || helpfulLoadingId === review.id"
                    @click="voteHelpful(review)"
                  >
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M14 9V5a2 2 0 0 0-2-2l-2 6H6a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h8.5a4.5 4.5 0 0 0 4.5-4.5V11a2 2 0 0 0-2-2h-1Z" />
                    </svg>
                    <span>{{ t('Helpful') }}</span>
                    <span class="font-semibold">{{ review.helpful_count ?? 0 }}</span>
                  </button>
                  <span v-if="isReviewVoted(review.id)" class="text-[0.75rem] text-emerald-700">{{ t('Thanks for your feedback!') }}</span>
                </div>
              </div>
            </div>
            <p v-else class="text-slate-500">{{ t('No reviews yet. Verified reviews appear after delivery.') }}</p>
          </div>
        </div>
      </div>
    </div>

    <section class="mt-12 space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-slate-900">{{ t('Related products') }}</h2>
        <Link href="/products" class="btn-ghost">{{ t('Browse all') }}</Link>
      </div>
      <div v-if="relatedProducts.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <ProductCard v-for="item in relatedProducts" :key="item.id" :product="item" :currency="currency" :promotions="page.props.homepagePromotions || []" />
      </div>
      <div v-else class="card-muted p-5 text-sm text-slate-600">
        {{ t('Explore more products with predictable delivery and upfront customs details.') }}
      </div>
    </section>
  </StorefrontLayout>
</template>

<script setup>
// Promotion logic for product details
function productPromotionForDetails(product, promotions) {
  if (!promotions?.length) return null
  return promotions.find(p =>
    (p.targets || []).some(t => t.target_type === 'product' && (t.target_id == product.id || t.target_value == product.name))
  )
}

const productPromotion = computed(() => productPromotionForDetails(props.product, page.props.homepagePromotions || []))
import { computed, ref } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ProductCard from '@/Components/ProductCard.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  product: { type: Object, required: true },
  currency: { type: String, default: 'USD' },
  reviews: { type: Array, default: () => [] },
  reviewSummary: { type: Object, default: () => ({ count: 0, average: 0, breakdown: {} }) },
  reviewHighlights: { type: Array, default: () => [] },
  relatedProducts: { type: Array, default: () => [] },
  reviewableItems: { type: Array, default: () => [] },
})

const { t, locale } = useTranslations()

const form = useForm({
  product_id: props.product.id,
  variant_id: props.product.variants?.[0]?.id ?? null,
  quantity: 1,
})

const submit = () => {
  form.product_id = props.product.id
  form.variant_id = selectedVariantId.value
  form.post('/cart', {
    preserveScroll: true,
    onSuccess: () => {
      successMessage.value = t('Added to cart.')
      clearSuccessSoon()
    },
  })
}

const selectedVariantId = ref(props.product.variants?.[0]?.id ?? null)
const selectedVariant = computed(() => {
  return props.product.variants?.find((variant) => variant.id === selectedVariantId.value) ?? null
})

const displayPrice = computed(() => {
  return selectedVariant.value?.price ?? Number(props.product.price ?? 0)
})

const selectVariant = (id) => {
  selectedVariantId.value = id
}

const stockStatus = computed(() => {
  const stock = selectedVariant.value?.stock_on_hand
  const threshold = selectedVariant.value?.low_stock_threshold ?? 5
  if (stock === null || stock === undefined) {
    return { label: '', status: 'unknown' }
  }
  if (stock <= 0) {
    return { label: t('Out of stock'), status: 'out' }
  }
  if (stock <= threshold) {
    return { label: t('Low stock'), status: 'low' }
  }
  return { label: t('In stock'), status: 'in' }
})

const stockBadge = computed(() => {
  const { status, label } = stockStatus.value
  if (!label) return { label: '', class: '', dot: '' }
  if (status === 'out') return { label, class: 'border border-red-200 bg-red-50 text-red-700', dot: 'bg-red-500' }
  if (status === 'low') return { label, class: 'border border-amber-200 bg-amber-50 text-amber-700', dot: 'bg-amber-500' }
  return { label, class: 'border border-emerald-200 bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' }
})

const isOutOfStock = computed(() => stockStatus.value.status === 'out')

const selectedImage = ref(props.product.media?.[0] ?? null)
const activeTab = ref('description')

const page = usePage()
const authUser = computed(() => page.props.auth?.user ?? null)
const successMessage = ref(page.props.flash?.cart_notice ?? '')
const reviewNotice = ref(page.props.flash?.review_notice ?? '')
const reviewsState = ref([...(props.reviews ?? [])])
const votedHelpfulIds = ref(new Set())
const helpfulLoadingId = ref(null)
const imagesError = ref('')
let successTimeout = null

const clearSuccessSoon = () => {
  if (successTimeout) {
    clearTimeout(successTimeout)
  }
  successTimeout = setTimeout(() => {
    successMessage.value = ''
  }, 2400)
}

const incrementQty = () => {
  form.quantity = Math.max(1, Number(form.quantity || 1) + 1)
}

const decrementQty = () => {
  form.quantity = Math.max(1, Number(form.quantity || 1) - 1)
}

const specEntries = computed(() => {
  const specs = props.product.specs ?? {}
  if (Array.isArray(specs)) {
    return specs.reduce((carry, entry, idx) => {
      if (entry && typeof entry === 'object') {
        const key = entry.key ?? entry.name ?? t('Spec :number', { number: idx + 1 })
        carry[key] = entry.value ?? entry
        return carry
      }
      carry[t('Spec :number', { number: idx + 1 })] = entry
      return carry
    }, {})
  }
  if (specs && typeof specs === 'object') {
    return specs
  }
  return {}
})

const rawDescription = computed(() => String(props.product.description ?? '').trim())

const escapeHtml = (value) => {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

const stripHtml = (value) => {
  if (! value) {
    return ''
  }
  if (typeof DOMParser === 'undefined') {
    return String(value).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
  }
  const doc = new DOMParser().parseFromString(String(value), 'text/html')
  return (doc.body.textContent || '').replace(/\s+/g, ' ').trim()
}

const isSafeUrl = (value) => {
  if (! value) {
    return false
  }
  const trimmed = String(value).trim()
  return /^https?:\/\//i.test(trimmed) || /^mailto:/i.test(trimmed)
}

const sanitizeDescriptionHtml = (value) => {
  if (typeof DOMParser === 'undefined') {
    return escapeHtml(stripHtml(value)).replace(/\n/g, '<br>')
  }

  const doc = new DOMParser().parseFromString(String(value), 'text/html')
  const allowedTags = new Set([
    'P',
    'BR',
    'UL',
    'OL',
    'LI',
    'STRONG',
    'B',
    'EM',
    'I',
    'U',
    'A',
    'IMG',
    'DIV',
    'SPAN',
    'H1',
    'H2',
    'H3',
    'H4',
    'H5',
    'H6',
    'TABLE',
    'THEAD',
    'TBODY',
    'TR',
    'TH',
    'TD',
  ])

  const walk = (node) => {
    const children = Array.from(node.childNodes)
    for (const child of children) {
      if (child.nodeType === 8) {
        node.removeChild(child)
        continue
      }
      if (child.nodeType !== 1) {
        continue
      }

      const tag = child.tagName.toUpperCase()
      if (! allowedTags.has(tag)) {
        const textNode = doc.createTextNode(child.textContent || '')
        node.replaceChild(textNode, child)
        continue
      }

      const attrs = Array.from(child.attributes)
      const href = tag === 'A' ? child.getAttribute('href') : null
      const src = tag === 'IMG' ? child.getAttribute('src') : null
      const alt = tag === 'IMG' ? child.getAttribute('alt') : null

      for (const attr of attrs) {
        child.removeAttribute(attr.name)
      }

      if (tag === 'A' && isSafeUrl(href)) {
        child.setAttribute('href', href)
        child.setAttribute('target', '_blank')
        child.setAttribute('rel', 'noopener noreferrer')
      }

      if (tag === 'IMG') {
        if (! isSafeUrl(src)) {
          node.removeChild(child)
          continue
        }
        child.setAttribute('src', src)
        if (alt) {
          child.setAttribute('alt', alt)
        }
        child.setAttribute('loading', 'lazy')
        child.setAttribute('style', 'max-width: 100%; height: auto;')
      }

      walk(child)
    }
  }

  walk(doc.body)

  return doc.body.innerHTML
}

const productVideos = computed(() => {
  const videos = Array.isArray(props.product.videos) ? props.product.videos : []
  return videos.filter((video) => isSafeUrl(video))
})

const descriptionText = computed(() => stripHtml(rawDescription.value))
const descriptionHtml = computed(() => {
  const raw = rawDescription.value
  if (! raw) {
    return ''
  }
  if (! /<[^>]+>/.test(raw)) {
    return escapeHtml(raw).replace(/\n/g, '<br>')
  }
  return sanitizeDescriptionHtml(raw)
})

const whatsappLink = computed(() => {
  const text = encodeURIComponent(
    t('Hi, I am interested in :name (:price).', {
      name: props.product.name,
      price: `${props.currency} ${displayPrice.value}`,
    })
  )
  const phone = page.props.site?.support_whatsapp ?? '22500000000'
  const sanitized = phone.replace(/[^\d]/g, '')
  return `https://wa.me/${sanitized}?text=${text}`
})

const metaTitle = computed(() => props.product.meta_title || props.product.name)
const metaDescription = computed(() => props.product.meta_description || descriptionText.value || '')

const formatSpecKey = (value) => {
  return String(value).replace(/_/g, ' ')
}

const formatDate = (value) => {
  if (! value) {
    return '-'
  }
  return new Date(value).toLocaleDateString(locale.value || 'en')
}

const reviewBarWidth = (rating) => {
  if (! props.reviewSummary.count) {
    return 0
  }
  return Math.round(((props.reviewSummary.breakdown?.[rating] ?? 0) / props.reviewSummary.count) * 100)
}

const reviewForm = useForm({
  order_item_id: props.reviewableItems?.[0]?.id ?? null,
  rating: 5,
  title: '',
  body: '',
  images: [],
})

const onImagesChange = (event) => {
  const files = Array.from(event.target?.files ?? [])
  const images = files.filter((file) => file.type?.startsWith('image/'))

  if (images.length > 3) {
    imagesError.value = t('Attach up to 3 images')
  } else {
    imagesError.value = ''
  }

  const trimmed = images.slice(0, 3)
  const tooLarge = trimmed.find((file) => file.size > 3 * 1024 * 1024)
  if (tooLarge) {
    imagesError.value = t('Each image must be under 3MB')
  }

  reviewForm.images = tooLarge ? [] : trimmed
}

const submitReview = () => {
  if (! reviewForm.order_item_id) {
    return
  }
  reviewForm.post(route('products.reviews.store', { product: props.product.slug }), {
    preserveScroll: true,
    onSuccess: () => {
      reviewNotice.value = page.props.flash?.review_notice ?? t('Thanks for your review.')
      reviewForm.reset('title', 'body', 'images')
      imagesError.value = ''
    },
  })
}

const markVoted = (id) => {
  const next = new Set(votedHelpfulIds.value)
  next.add(id)
  votedHelpfulIds.value = next
}

const isReviewVoted = (id) => votedHelpfulIds.value.has(id)

const voteHelpful = async (review) => {
  if (! review?.id || isReviewVoted(review.id) || helpfulLoadingId.value === review.id) {
    return
  }
  helpfulLoadingId.value = review.id

  try {
    const { data } = await axios.post(route('reviews.helpful', { review: review.id }))
    reviewsState.value = reviewsState.value.map((r) =>
      r.id === review.id ? { ...r, helpful_count: data.helpful_count ?? r.helpful_count ?? 0 } : r,
    )
    markVoted(review.id)
  } catch (error) {
    if (error?.response?.status === 409) {
      markVoted(review.id)
    }
  } finally {
    helpfulLoadingId.value = null
  }
}

const formatOrderDate = (value) => {
  if (! value) {
    return t('Order date unavailable')
  }
  return new Date(value).toLocaleDateString(locale.value || 'en')
}
</script>
