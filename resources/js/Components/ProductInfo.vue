<template>
  <div class="space-y-5">
    <div class="space-y-2">
      <div class="flex items-center gap-2 text-xs text-slate-500">
        <Link
          v-if="product.category_href"
          :href="product.category_href"
          class="font-semibold text-red-500 hover:text-red-600"
        >
          {{ product.category ?? t('Simbazu') }}
        </Link>
        <span v-else>{{ product.category ?? t('Simbazu') }}</span>
        <ProductPromotionBadge
          v-if="productPromotion || promoCountdown"
          :promotion="productPromotion"
          :countdown="promoCountdown"
          :display-value="displayPromotionValue"
        />
      </div>

      <h1 class="text-2xl font-bold tracking-tight text-slate-900 lg:text-3xl">
        {{ product.name }}
      </h1>

      <p v-if="descriptionText" class="line-clamp-2 text-sm text-slate-500">
        {{ descriptionText }}
      </p>
    </div>

    <div class="flex flex-wrap items-baseline gap-3">
      <span class="price-red text-3xl font-black">{{ displayPriceFormatted }}</span>
      <span
        v-if="compareAtForDisplay"
        class="price-strikethrough text-lg text-slate-400 line-through"
      >
        {{ compareAtFormatted }}
      </span>
      <span
        v-if="productPromotion?.apply_hint && !promotionPriceDiscountable"
        class="text-xs text-slate-500"
      >
        {{ productPromotion.apply_hint }}
      </span>
      <span
        v-if="stockBadge.label"
        :class="stockBadge.class"
        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[0.65rem] font-semibold"
      >
        <span class="h-1.5 w-1.5 rounded-full" :class="stockBadge.dot" />
        {{ stockBadge.label }}
      </span>
    </div>

    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
      <span v-if="reviewSummary.count" class="inline-flex items-center gap-1.5">
        <span class="flex items-center gap-0.5">
          <svg
            v-for="n in 5"
            :key="n"
            class="h-3.5 w-3.5"
            :class="n <= Math.round(reviewSummary.average) ? 'text-slate-900' : 'text-slate-200'"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path d="M12 3.5l2.6 5.4 6 .9-4.3 4.1 1 5.8L12 16.9 6.7 19.7l1-5.8-4.3-4.1 6-.9L12 3.5z"/>
          </svg>
        </span>
        <span class="font-semibold text-slate-800">{{ reviewSummary.average }}</span>
        <span>({{ reviewSummary.count }} {{ t('reviews') }})</span>
      </span>
      <span class="inline-flex items-center gap-1">
        <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7.5 4.5v9L12 21l-7.5-4.5v-9L12 3z"/>
        </svg>
        {{ t('Ships in :days days', { days: product.lead_time_days ?? '7-21' }) }}
      </span>
    </div>

    <div
      v-if="productCode || shouldShowVariantSku"
      class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500"
    >
      <span v-if="productCode">
        {{ t('Product code') }}: <span class="font-semibold text-slate-800">{{ productCode }}</span>
      </span>
      <span v-if="shouldShowVariantSku">
        {{ t('Variant SKU') }}: <span class="font-semibold text-slate-800">{{ variantSku }}</span>
      </span>
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'
import ProductPromotionBadge from '@/Components/ProductPromotionBadge.vue'

const props = defineProps({
  product: { type: Object, required: true },
  displayPriceFormatted: { type: String, default: '' },
  compareAtFormatted: { type: String, default: '' },
  compareAtForDisplay: { type: [Number, null], default: null },
  productPromotion: { type: Object, default: null },
  promoCountdown: { type: String, default: '' },
  promotionPriceDiscountable: { type: Boolean, default: false },
  stockBadge: { type: Object, default: () => ({}) },
  reviewSummary: { type: Object, default: () => ({ count: 0, average: 0 }) },
  productCode: { type: String, default: null },
  variantSku: { type: String, default: null },
  shouldShowVariantSku: { type: Boolean, default: false },
  displayPromotionValue: { type: Function, default: (v) => v },
})

const { t } = useTranslations()

const descriptionText = computed(() => {
  const raw = String(props.product.description ?? '').trim()
  if (!raw) return ''
  return raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
})
</script>
