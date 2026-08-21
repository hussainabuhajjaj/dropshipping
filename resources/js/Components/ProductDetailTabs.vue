<template>
  <div class="min-w-0 max-w-full space-y-4 overflow-hidden">
    <div class="flex max-w-full gap-1 overflow-x-auto border-b border-slate-200">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="border-b-2 px-4 py-2.5 text-xs font-semibold transition -mb-px"
        :class="tabClasses(tab.key)"
        @click="$emit('update:activeTab', tab.key)"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="activeTab === 'description'" class="min-w-0 max-w-full overflow-hidden text-sm text-slate-600">
      <div v-if="descriptionHtml" class="product-description-content space-y-3 leading-relaxed" v-html="descriptionHtml" />
      <p v-else class="text-slate-400">{{ t('Details coming soon.') }}</p>
      <div v-if="reviewHighlights.length" class="mt-6 grid gap-3 sm:grid-cols-2">
        <div
          v-for="highlight in reviewHighlights"
          :key="`${highlight.author}-${highlight.title}`"
          class="rounded-xl border border-slate-100 bg-white p-4 text-xs text-slate-600"
        >
          <p class="font-semibold text-slate-900">{{ highlight.title || t('Verified review') }}</p>
          <p class="mt-1.5 line-clamp-3 text-slate-500 leading-relaxed">{{ highlight.body }}</p>
          <p class="mt-2 text-[0.65rem] text-slate-400">{{ highlight.author }}</p>
        </div>
      </div>
    </div>

    <div v-else-if="activeTab === 'specs'" class="space-y-3 text-sm text-slate-600">
      <div v-if="specKeys.length" class="divide-y divide-slate-100">
        <div v-for="(value, key) in displaySpecEntries" :key="key" class="flex justify-between py-2.5">
          <span class="text-slate-500 capitalize">{{ formatSpecKey(key) }}</span>
          <span class="font-semibold text-slate-900 text-right ml-4">{{ value }}</span>
        </div>
      </div>
      <p v-else class="text-slate-400">{{ t('Specs will appear once the supplier confirms details.') }}</p>
      <div class="text-xs text-slate-400">
        {{ t('Delivery estimate: :days days · Customs shown before checkout.', { days: leadTime }) }}
      </div>
    </div>

    <div v-else class="space-y-4 text-sm text-slate-600">
      <div v-if="authUser && reviewableItems.length" class="rounded-xl border border-slate-100 bg-white p-4">
        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ t('Write a review') }}</p>
        <form class="mt-3 grid gap-3" @submit.prevent="$emit('submit-review')">
          <div v-if="reviewableItems.length > 1">
            <label class="text-xs font-semibold text-slate-600">{{ t('Delivered order') }}</label>
            <select
              v-model="reviewForm.order_item_id"
              class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
            >
              <option v-for="item in reviewableItems" :key="item.id" :value="item.id">
                {{ item.order_number ? t('Order #:number', { number: item.order_number }) : t('Delivered order') }}
                · {{ formatDate(item.ordered_at) }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-600">{{ t('Rating') }}</label>
            <select
              v-model="reviewForm.rating"
              class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
            >
              <option v-for="rating in [5,4,3,2,1]" :key="rating" :value="rating">{{ t(':count stars', { count: rating }) }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-600">{{ t('Title') }}</label>
            <input v-model="reviewForm.title" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" :placeholder="t('Great quality')" />
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-600">{{ t('Review') }}</label>
            <textarea v-model="reviewForm.body" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" :placeholder="t('Tell us how it arrived.')" />
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-600">{{ t('Photos (optional)') }}</label>
            <input type="file" multiple accept="image/*" class="mt-1 w-full text-sm" @change="$emit('images-change', $event)" />
            <p class="mt-1 text-[0.65rem] text-slate-400">{{ t('Up to 3 images, 3MB each') }}</p>
            <p v-if="imagesError" class="mt-1 text-[0.7rem] text-red-500">{{ imagesError }}</p>
          </div>
          <button type="submit" class="btn-red w-full sm:w-auto" :disabled="reviewForm.processing">
            {{ reviewForm.processing ? t('Submitting...') : t('Submit review') }}
          </button>
          <p v-if="reviewNotice" class="text-xs text-emerald-600">{{ reviewNotice }}</p>
        </form>
      </div>
      <div v-else-if="authUser" class="rounded-xl border border-slate-100 bg-white p-4 text-xs text-slate-400">
        {{ t('Reviews unlock after delivery. Check back once your order arrives.') }}
      </div>
      <div v-else class="rounded-xl border border-slate-100 bg-white p-4 text-xs text-slate-400">
        {{ t('Sign in to leave a verified review after delivery.') }}
      </div>

      <div v-if="reviewSummary.count" class="rounded-xl border border-slate-100 bg-white p-4">
        <div class="flex items-center gap-5">
          <div class="text-center">
            <div class="text-3xl font-black text-slate-900">{{ reviewSummary.average }}</div>
            <div class="mt-1 flex items-center gap-0.5 justify-center">
              <svg v-for="n in 5" :key="n" class="h-3 w-3" :class="n <= Math.round(reviewSummary.average) ? 'text-slate-900' : 'text-slate-200'" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3.5l2.6 5.4 6 .9-4.3 4.1 1 5.8L12 16.9 6.7 19.7l1-5.8-4.3-4.1 6-.9L12 3.5z"/></svg>
            </div>
            <div class="text-[0.65rem] text-slate-400 mt-1">{{ t('Average rating') }}</div>
          </div>
          <div class="flex-1 space-y-1.5">
            <div v-for="rating in [5,4,3,2,1]" :key="rating" class="flex items-center gap-2 text-xs">
              <span class="w-6 text-right text-slate-500">{{ rating }}</span>
              <div class="h-2 flex-1 rounded-full bg-slate-100">
                <div class="h-2 rounded-full bg-slate-900 transition-all" :style="{ width: `${reviewBarWidth(rating)}%` }" />
              </div>
              <span class="w-6 text-right text-slate-500">{{ reviewSummary.breakdown[rating] || 0 }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="reviews.length" class="space-y-3">
        <div v-for="review in reviews" :key="review.id" class="rounded-xl border border-slate-100 bg-white p-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <p class="text-sm font-semibold text-slate-900">{{ review.author }}</p>
              <span v-if="review.verified_purchase" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[0.6rem] font-semibold text-emerald-700">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                {{ t('Verified') }}
              </span>
            </div>
            <span class="text-xs text-slate-400">{{ formatDate(review.created_at) }}</span>
          </div>
          <div class="mt-1 flex items-center gap-1 text-xs text-slate-500">
            <span v-for="n in 5" :key="n" :class="n <= review.rating ? 'text-slate-900' : 'text-slate-200'">★</span>
            <span class="ml-1.5 font-semibold text-slate-700">{{ review.rating }}/5</span>
          </div>
          <p v-if="review.title" class="mt-2 text-sm font-semibold text-slate-900">{{ review.title }}</p>
          <p class="mt-1 text-sm text-slate-500 leading-relaxed">{{ review.body }}</p>
          <div v-if="review.images?.length" class="mt-3 flex gap-2 overflow-x-auto">
            <a v-for="(image, idx) in review.images" :key="idx" :href="image" target="_blank" rel="noreferrer" class="block shrink-0 overflow-hidden rounded-lg border border-slate-100">
              <img :src="image" :alt="review.title || review.author" class="h-20 w-20 object-cover" />
            </a>
          </div>
          <div class="mt-3 flex items-center gap-3 text-xs text-slate-500">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 font-semibold transition"
              :class="isReviewVoted(review.id) ? 'bg-slate-50 text-slate-400 cursor-default' : 'bg-white text-slate-700 hover:border-slate-300'"
              :disabled="isReviewVoted(review.id) || helpfulLoadingId === review.id"
              @click="$emit('vote-helpful', review)"
            >
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 9V5a2 2 0 0 0-2-2l-2 6H6a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h8.5a4.5 4.5 0 0 0 4.5-4.5V11a2 2 0 0 0-2-2h-1Z"/></svg>
              <span>{{ t('Helpful') }}</span>
              <span class="font-semibold">({{ review.helpful_count ?? 0 }})</span>
            </button>
            <span v-if="isReviewVoted(review.id)" class="text-[0.65rem] text-emerald-600">{{ t('Thanks!') }}</span>
          </div>
        </div>
      </div>
      <p v-else class="text-slate-400">{{ t('No reviews yet. Verified reviews appear after delivery.') }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  activeTab: { type: String, default: 'description' },
  descriptionHtml: { type: String, default: '' },
  descriptionText: { type: String, default: '' },
  reviewHighlights: { type: Array, default: () => [] },
  specEntries: { type: Object, default: () => ({}) },
  leadTime: { type: [String, Number], default: 7 },
  reviewSummary: { type: Object, default: () => ({ count: 0, average: 0, breakdown: {} }) },
  reviews: { type: Array, default: () => [] },
  authUser: { type: Object, default: null },
  reviewableItems: { type: Array, default: () => [] },
  reviewForm: { type: Object, default: null },
  reviewNotice: { type: String, default: '' },
  imagesError: { type: String, default: '' },
  helpfulLoadingId: { type: [Number, String], default: null },
  isReviewVoted: { type: Function, default: () => false },
  reviewBarWidth: { type: Function, default: () => 0 },
})

defineEmits(['update:activeTab', 'submit-review', 'images-change', 'vote-helpful'])

const { t, locale } = useTranslations()

const tabs = computed(() => [
  { key: 'description', label: t('Description') },
  { key: 'specs', label: t('Specs') },
  { key: 'reviews', label: t('Reviews (:count)', { count: props.reviewSummary.count }) },
])

const blockedSpecPattern = /(cj|cjdropshipping|payload|variant|inventory|image|video|sku|pid|supplier|source|token|secret|url|html|description)/i

const displaySpecEntries = computed(() => Object.entries(props.specEntries ?? {}).reduce((entries, [key, value]) => {
  if (blockedSpecPattern.test(String(key))) {
    return entries
  }

  const normalizedValue = normalizeSpecValue(value)
  if (!normalizedValue || blockedSpecPattern.test(normalizedValue)) {
    return entries
  }

  entries[key] = normalizedValue
  return entries
}, {}))

const specKeys = computed(() => Object.keys(displaySpecEntries.value))

function tabClasses(key) {
  return key === props.activeTab
    ? 'border-slate-900 text-slate-900'
    : 'border-transparent text-slate-500 hover:text-slate-700'
}

function formatSpecKey(value) {
  return String(value).replace(/_/g, ' ')
}

function normalizeSpecValue(value) {
  if (value === null || value === undefined || value === '') return ''
  if (typeof value === 'boolean') return value ? t('Yes') : t('No')
  if (typeof value === 'number') return String(value)
  if (typeof value !== 'string') return ''

  const trimmed = value.trim()
  if (!trimmed || trimmed.length > 180 || trimmed.includes('{') || trimmed.includes('[') || trimmed.includes('://') || trimmed.includes('<')) {
    return ''
  }

  return trimmed
}

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleDateString(locale.value || 'en')
}
</script>

<style scoped>
.product-description-content {
  max-width: 100%;
  overflow-x: hidden;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.product-description-content :deep(*) {
  max-width: 100% !important;
  box-sizing: border-box;
}

.product-description-content :deep(img),
.product-description-content :deep(video),
.product-description-content :deep(iframe) {
  display: block;
  height: auto !important;
  max-width: 100% !important;
}

.product-description-content :deep(table) {
  display: block;
  width: 100% !important;
  overflow-x: auto;
  border-collapse: collapse;
}

.product-description-content :deep(td),
.product-description-content :deep(th) {
  min-width: 0;
  white-space: normal;
}
</style>
