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
              v-for="(image, idx) in galleryImages"
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
            v-for="(image, idx) in galleryImages"
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
            <span
              v-if="productPromotion"
              class="ml-2 inline-flex items-center gap-1 rounded bg-yellow-200 px-2 py-0.5 font-bold text-yellow-900"
            >
              {{ productPromotion.badge_text || productPromotion.name }}
              <span v-if="productPromotion.value_type === 'percentage'">-{{ productPromotion.value }}%</span>
              <span v-else-if="productPromotion.value_type === 'fixed'">-{{ displayPromotionValue(productPromotion.value) }}</span>
            </span>
            <span v-if="promoCountdown" class="ml-2 text-[10px] font-semibold text-amber-700">
              {{ t('Ends in') }} {{ promoCountdown }}
            </span>
          </p>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900">{{ product.name }}</h1>
          <p class="text-sm text-slate-600">{{ descriptionText }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <span class="text-2xl font-semibold text-slate-900">
            {{ displayPriceFormatted }}
          </span>
          <span v-if="compareAtForDisplay" class="text-sm text-slate-400 line-through">
            {{ compareAtFormatted }}
          </span>
          <span v-if="productPromotion?.apply_hint && !promotionPriceDiscountable" class="text-xs text-slate-500">
            {{ productPromotion.apply_hint }}
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

        <div v-if="useGroupedVariantPicker" class="space-y-5">
          <div
            v-for="group in optionGroups"
            :key="group.key"
            class="space-y-3"
          >
            <div class="flex items-center justify-between gap-3">
              <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ group.label }}</label>
              <span v-if="selectedOptions[group.key]" class="text-sm font-semibold text-slate-900">{{ selectedOptions[group.key] }}</span>
            </div>

            <div v-if="group.presentation === 'image'" class="flex flex-wrap gap-3">
              <button
                v-for="choice in getGroupChoices(group.key)"
                :key="`${group.key}-${choice.value}`"
                type="button"
                class="w-24 overflow-hidden rounded-2xl border bg-white text-left transition"
                :class="choice.selected ? 'border-slate-900 ring-2 ring-slate-200' : choice.disabled ? 'cursor-not-allowed border-slate-200 opacity-40' : 'border-slate-200 hover:border-slate-400'"
                :disabled="choice.disabled"
                @click="updateOptionSelection(group.key, choice.value)"
              >
                <div class="aspect-square bg-slate-50">
                  <img
                    v-if="choice.image"
                    :src="choice.image"
                    :alt="choice.label"
                    class="h-full w-full object-cover"
                  />
                  <div v-else class="flex h-full items-center justify-center px-2 text-center text-xs font-semibold text-slate-500">
                    {{ choice.label }}
                  </div>
                </div>
                <div class="border-t border-slate-100 px-2 py-2 text-xs font-semibold text-slate-800">
                  {{ choice.label }}
                </div>
              </button>
            </div>

            <select
              v-else-if="group.presentation === 'dropdown'"
              :value="selectedOptions[group.key] ?? ''"
              class="input-base w-full max-w-sm"
              @change="updateOptionSelection(group.key, $event.target.value)"
            >
              <option
                v-for="choice in getGroupChoices(group.key)"
                :key="`${group.key}-${choice.value}`"
                :value="choice.value"
                :disabled="choice.disabled"
              >
                {{ choice.label }}{{ choice.outOfStock ? ` (${t('Out of stock')})` : '' }}
              </option>
            </select>

            <div v-else class="flex flex-wrap gap-2">
              <button
                v-for="choice in getGroupChoices(group.key)"
                :key="`${group.key}-${choice.value}`"
                type="button"
                class="rounded-2xl border px-4 py-2 text-sm font-semibold transition"
                :class="choice.selected ? 'border-slate-900 bg-slate-900 text-white' : choice.disabled ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-300' : choice.outOfStock ? 'border-slate-200 bg-slate-50 text-slate-500' : 'border-slate-200 text-slate-800 hover:border-slate-400'"
                :disabled="choice.disabled"
                @click="updateOptionSelection(group.key, choice.value)"
              >
                {{ choice.label }}
              </button>
            </div>
          </div>
        </div>

        <div v-else-if="product.variants?.length" class="space-y-3">
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
        <ProductCard
          v-for="item in relatedProducts"
          :key="item.id"
          :product="item"
          :currency="currency"
          :promotions="(page && page.props && (page.props.promotions || page.props.homepagePromotions)) ? (page.props.promotions || page.props.homepagePromotions) : []"
        />
      </div>
      <div v-else class="card-muted p-5 text-sm text-slate-600">
        {{ t('Explore more products with predictable delivery and upfront customs details.') }}
      </div>
    </section>

    <LoginRequiredModal
      :show="showLoginPrompt"
      :title="t('You must log in first to continue shopping.')"
      :message="t('Log in to add this item to your cart and continue checkout.')"
      @close="showLoginPrompt = false"
      @login="router.visit('/login')"
    />
  </StorefrontLayout>
</template>

<script setup>
// Promotion logic for product details
function productPromotionForDetails(product, promotions) {
  if (!promotions?.length) return null
  const targeted = promotions.find(p =>
    (p.targets || []).some(t => {
      if (t.target_type === 'product') return t.target_id == product.id
      if (t.target_type === 'category') return t.target_id == product.category_id
      return false
    })
  )
  if (targeted) return targeted
  return promotions.find(p => p.is_sitewide) ?? null
}

import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import LoginRequiredModal from '@/Components/LoginRequiredModal.vue'
import ProductCard from '@/Components/ProductCard.vue'
import Modal from '@/Components/Modal.vue'
import { useTranslations } from '@/i18n'
import { useProductCartForm } from '@/composables/useProductCartForm.js'
import { usePromoNow, formatCountdown } from '@/composables/usePromoCountdown.js'
import { useUserPreferences } from '@/composables/useUserPreferences.js'
import {usePage} from '@inertiajs/vue3'

const page = usePage();
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
const now = usePromoNow()
const { currentCurrency, formatCurrency, convertCurrency } = useUserPreferences()
const displayCurrency = computed(() => currentCurrency.value || props.currency)

const promotionPriceDiscountable = computed(() => {
  const promo = productPromotion.value
  if (!promo) return false
  if (promo.value_type !== 'percentage' && promo.value_type !== 'fixed') return false
  if (promo.has_conditions || promo.is_sitewide) return false
  return true
})

const {
  decrementQty,
  form,
  incrementQty,
  isOutOfStock,
  selectVariant,
  selectedVariant,
  selectedVariantId,
  showLoginPrompt,
  stockBadge,
  submit,
  successMessage,
} = useProductCartForm({
  product: props.product,
  t,
})

const basePriceForDisplay = computed(() => Number(selectedVariant.value?.price ?? props.product.price ?? 0))

const compareAtForDisplay = computed(() => {
  const compareAt = selectedVariant.value?.compare_at_price
  if (compareAt) return compareAt
  if (promotionPriceDiscountable.value) return basePriceForDisplay.value
  return null
})

const displayPrice = computed(() => {
  const base = basePriceForDisplay.value
  if (!promotionPriceDiscountable.value || base <= 0) {
    return base
  }
  if (selectedVariant.value?.compare_at_price) {
    return base
  }

  const promo = productPromotion.value
  if (promo?.value_type === 'percentage') {
    const pct = Number(promo.value ?? 0)
    const discounted = base * (1 - pct / 100)
    return Math.max(0, Number(discounted.toFixed(2)))
  }

  const amount = Number(promo?.value ?? 0)
  return Math.max(0, Number((base - amount).toFixed(2)))
})

const displayPriceFormatted = computed(() =>
  formatCurrency(convertCurrency(displayPrice.value, 'USD', displayCurrency.value), displayCurrency.value)
)
const compareAtFormatted = computed(() =>
  formatCurrency(convertCurrency(Number(compareAtForDisplay.value ?? 0), 'USD', displayCurrency.value), displayCurrency.value)
)

const displayPromotionValue = (amount) =>
  formatCurrency(convertCurrency(Number(amount ?? 0), 'USD', displayCurrency.value), displayCurrency.value)

const visualOptionPattern = /(color|colour|finish|shade|pattern|style)/i
const dropdownOptionPattern = /(bundle|pack|quantity|set)/i
const sizeOptionPattern = /^(xxxs|xxs|xs|s|m|l|xl|xxl|xxxl|xxxxl|one size|free size|small|medium|large|us\s*\d+.*|eu\s*\d+.*|uk\s*\d+.*|\d{2,3}([-/]\d{2,3})?)$/i

const normalizeOptionKey = (value) => String(value ?? '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_')
const splitCompoundValue = (value, separator) =>
  String(value ?? '')
    .split(separator)
    .map((part) => part.trim())
    .filter(Boolean)

const inferCompoundOptionConfig = (variants) => {
  if (variants.length < 2) {
    return null
  }

  const rawVariantOptions = variants.map((variant) => {
    const rawOptions = variant?.options && typeof variant.options === 'object' ? Object.entries(variant.options) : []
    return rawOptions.length === 1 ? rawOptions[0] : null
  })

  if (rawVariantOptions.some((entry) => !entry)) {
    return null
  }

  const separators = [/\s*\/\s*/, /\s*\|\s*/, /\s*-\s*/, /\s*,\s*/]

  for (const separator of separators) {
    const partsList = rawVariantOptions.map(([, value]) => splitCompoundValue(value, separator))
    const partCount = partsList[0]?.length ?? 0

    if (partCount < 2 || partsList.some((parts) => parts.length !== partCount)) {
      continue
    }

    const labels = Array.from({ length: partCount }, (_, index) => {
      const values = partsList.map((parts) => parts[index])
      if (values.every((value) => sizeOptionPattern.test(value))) {
        return 'Size'
      }
      if (index === 0) {
        return 'Color'
      }
      return `Option ${index + 1}`
    })

    return {
      separator,
      labels,
    }
  }

  return null
}

const normalizedVariants = computed(() => {
  const variants = Array.isArray(props.product.variants) ? props.product.variants : []
  const compoundConfig = inferCompoundOptionConfig(variants)

  return variants.map((variant) => {
    const rawOptions = variant?.options && typeof variant.options === 'object' ? variant.options : {}
    const normalizedOptions = {}
    const optionLabels = {}

    Object.entries(rawOptions).forEach(([key, value]) => {
      const normalizedKey = normalizeOptionKey(key)
      const label = String(key ?? '').trim()
      const normalizedValue = String(value ?? '').trim()

      if (!normalizedKey || !normalizedValue) {
        return
      }

      normalizedOptions[normalizedKey] = normalizedValue
      optionLabels[normalizedKey] = label || normalizedKey
    })

    if (compoundConfig && Object.keys(normalizedOptions).length === 1 && normalizedOptions.option) {
      const parts = splitCompoundValue(normalizedOptions.option, compoundConfig.separator)

      if (parts.length === compoundConfig.labels.length) {
        Object.keys(normalizedOptions).forEach((key) => {
          delete normalizedOptions[key]
        })
        Object.keys(optionLabels).forEach((key) => {
          delete optionLabels[key]
        })

        parts.forEach((part, index) => {
          const label = compoundConfig.labels[index]
          const normalizedKey = normalizeOptionKey(label)
          normalizedOptions[normalizedKey] = part
          optionLabels[normalizedKey] = label
        })
      }
    }

    return {
      ...variant,
      normalizedOptions,
      optionLabels,
      variant_image: variant?.variant_image || null,
    }
  })
})

const optionGroups = computed(() => {
  const groups = new Map()

  normalizedVariants.value.forEach((variant) => {
    Object.entries(variant.normalizedOptions).forEach(([key, value]) => {
      if (!groups.has(key)) {
        groups.set(key, {
          key,
          label: variant.optionLabels[key] || key,
          values: new Map(),
        })
      }

      const group = groups.get(key)
      if (!group.values.has(value)) {
        group.values.set(value, {
          value,
          label: value,
          image: variant.variant_image || null,
        })
      } else if (!group.values.get(value).image && variant.variant_image) {
        group.values.get(value).image = variant.variant_image
      }
    })
  })

  const scoreGroup = (group) => {
    const label = group.label || group.key
    const hasImages = Array.from(group.values.values()).some((value) => Boolean(value.image))
    if (hasImages) return -30
    if (visualOptionPattern.test(label)) return -20
    if (dropdownOptionPattern.test(label)) return 15
    return 0
  }

  return Array.from(groups.values())
    .map((group) => {
      const values = Array.from(group.values.values())
      const presentation = values.length > 8 || dropdownOptionPattern.test(group.label)
        ? 'dropdown'
        : (values.some((value) => value.image) ? 'image' : 'button')

      return {
        key: group.key,
        label: group.label,
        presentation,
        values,
      }
    })
    .sort((a, b) => scoreGroup(a) - scoreGroup(b))
})

const useGroupedVariantPicker = computed(() =>
  optionGroups.value.length > 0 && optionGroups.value.some((group) => group.values.length > 1)
)

const selectedOptions = ref({})

const variantMatchesSelection = (variant, selection) => {
  return Object.entries(selection).every(([key, value]) => {
    if (!value) return true
    return variant.normalizedOptions[key] === value
  })
}

const choosePreferredValue = (groupKey, values, variants) => {
  return [...values]
    .sort((left, right) => {
      const leftVariants = variants.filter((variant) => variant.normalizedOptions[groupKey] === left)
      const rightVariants = variants.filter((variant) => variant.normalizedOptions[groupKey] === right)
      const leftScore = (leftVariants.some((variant) => Number(variant.stock_on_hand ?? 0) > 0) ? 100 : 0) + (leftVariants.some((variant) => variant.variant_image) ? 10 : 0)
      const rightScore = (rightVariants.some((variant) => Number(variant.stock_on_hand ?? 0) > 0) ? 100 : 0) + (rightVariants.some((variant) => variant.variant_image) ? 10 : 0)
      return rightScore - leftScore
    })[0] ?? null
}

const choosePreferredVariant = (variants) => {
  if (!variants.length) return null
  return variants.find((variant) => variant.id === selectedVariantId.value)
    ?? variants.find((variant) => Number(variant.stock_on_hand ?? 0) > 0)
    ?? variants[0]
}

const resolveVariantState = (desiredSelection = {}) => {
  let candidates = [...normalizedVariants.value]
  const resolvedSelection = {}

  optionGroups.value.forEach((group) => {
    const values = [...new Set(candidates.map((variant) => variant.normalizedOptions[group.key]).filter(Boolean))]
    if (!values.length) {
      return
    }

    const desiredValue = desiredSelection[group.key]
    const chosenValue = desiredValue && values.includes(desiredValue)
      ? desiredValue
      : choosePreferredValue(group.key, values, candidates)

    if (!chosenValue) {
      return
    }

    resolvedSelection[group.key] = chosenValue
    candidates = candidates.filter((variant) => variant.normalizedOptions[group.key] === chosenValue)
  })

  return {
    selection: resolvedSelection,
    variant: choosePreferredVariant(candidates),
  }
}

const getGroupChoices = (groupKey) => {
  const group = optionGroups.value.find((entry) => entry.key === groupKey)
  if (!group) {
    return []
  }

  const baseSelection = { ...selectedOptions.value }
  delete baseSelection[groupKey]

  return group.values.map((choice) => {
    const matchingVariants = normalizedVariants.value.filter((variant) =>
      variantMatchesSelection(variant, { ...baseSelection, [groupKey]: choice.value }),
    )

    return {
      ...choice,
      disabled: matchingVariants.length === 0,
      outOfStock: matchingVariants.length > 0 && !matchingVariants.some((variant) => Number(variant.stock_on_hand ?? 0) > 0),
      selected: selectedOptions.value[groupKey] === choice.value,
    }
  })
}

const updateOptionSelection = (groupKey, value) => {
  if (!value) {
    return
  }

  const next = {
    ...selectedOptions.value,
    [groupKey]: value,
  }

  const resolved = resolveVariantState(next)
  selectedOptions.value = resolved.selection

  if (resolved.variant && resolved.variant.id !== selectedVariantId.value) {
    selectVariant(resolved.variant.id)
  }
}

const galleryImages = computed(() => {
  const images = [
    selectedVariant.value?.variant_image ?? null,
    ...(Array.isArray(props.product.media) ? props.product.media : []),
  ].filter(Boolean)

  return [...new Set(images)]
})

const selectedImage = ref(null)
const activeTab = ref('description')

const activePromotions = computed(() => page.props.promotions || page.props.homepagePromotions || [])
const productPromotion = computed(() => productPromotionForDetails(props.product, activePromotions.value))
const promoCountdown = computed(() => formatCountdown(productPromotion.value?.end_at, now.value))

const authUser = computed(() => page.props.auth?.user ?? null)
const reviewNotice = ref(page.props.flash?.review_notice ?? '')
const reviewsState = ref([...(props.reviews ?? [])])
const votedHelpfulIds = ref(new Set())
const helpfulLoadingId = ref(null)
const imagesError = ref('')

watch(
  [normalizedVariants, optionGroups],
  () => {
    if (!useGroupedVariantPicker.value) {
      return
    }

    const currentVariant = normalizedVariants.value.find((variant) => variant.id === selectedVariantId.value)
      ?? normalizedVariants.value[0]

    const resolved = resolveVariantState(currentVariant?.normalizedOptions ?? {})
    selectedOptions.value = resolved.selection

    if (resolved.variant && resolved.variant.id !== selectedVariantId.value) {
      selectVariant(resolved.variant.id)
    }
  },
  { immediate: true },
)

watch(
  selectedVariantId,
  (variantId) => {
    if (!useGroupedVariantPicker.value) {
      return
    }

    const currentVariant = normalizedVariants.value.find((variant) => variant.id === variantId)
    if (currentVariant) {
      selectedOptions.value = { ...currentVariant.normalizedOptions }
    }
  },
  { immediate: true },
)

watch(
  galleryImages,
  (images) => {
    if (!images.length) {
      selectedImage.value = null
      return
    }

    if (selectedVariant.value?.variant_image && images.includes(selectedVariant.value.variant_image)) {
      selectedImage.value = selectedVariant.value.variant_image
      return
    }

    if (!selectedImage.value || !images.includes(selectedImage.value)) {
      selectedImage.value = images[0]
    }
  },
  { immediate: true },
)

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
