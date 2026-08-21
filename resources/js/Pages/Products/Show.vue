<template>
  <StorefrontLayout>
    <Head :title="metaTitle">
      <meta name="description" head-key="description" :content="metaDescription" />
      <link rel="canonical" head-key="canonical" :href="productUrl" />
      <meta property="og:title" head-key="og:title" :content="metaTitle" />
      <meta property="og:description" head-key="og:description" :content="metaDescription" />
      <meta property="og:image" head-key="og:image" :content="productImage" />
      <meta property="og:url" head-key="og:url" :content="productUrl" />
      <meta property="og:type" head-key="og:type" content="product" />
      <meta property="og:site_name" head-key="og:site_name" content="Simbazu" />
      <meta name="twitter:card" head-key="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" head-key="twitter:title" :content="metaTitle" />
      <meta name="twitter:description" head-key="twitter:description" :content="metaDescription" />
      <meta name="twitter:image" head-key="twitter:image" :content="productImage" />
    </Head>

    <div class="min-h-screen bg-white pb-28 lg:pb-0">
      <Breadcrumbs :items="breadcrumbs" class="px-4 pt-4" />

      <div class="mx-auto mt-2 max-w-7xl px-4 lg:mt-4">
        <div class="lg:grid lg:grid-cols-[1.3fr,1fr] lg:gap-12">

          <ProductGallery
            :images="galleryImages"
            :selected-image="selectedImage"
            :image-alt="imageAltText"
            :videos="productVideos"
            @select-image="selectedImage = $event"
            @prev-image="setGalleryImageByIndex(selectedImageIndex - 1)"
            @next-image="setGalleryImageByIndex(selectedImageIndex + 1)"
          />

          <div class="mt-6 space-y-5 lg:mt-0 lg:sticky lg:top-28 lg:self-start">
            <ProductInfo
              :product="product"
              :display-price-formatted="displayPriceFormatted"
              :compare-at-formatted="compareAtFormatted"
              :compare-at-for-display="compareAtForDisplay"
              :product-promotion="productPromotion"
              :promo-countdown="promoCountdown"
              :promotion-price-discountable="promotionPriceDiscountable"
              :stock-badge="stockBadge"
              :review-summary="reviewSummary"
              :display-promotion-value="displayPromotionValue"
            />

            <div v-if="qualifiesForGiveaway" class="rounded-xl border border-amber-200 bg-gradient-to-r from-amber-50 to-yellow-50 px-4 py-3">
              <a :href="'/campaigns/' + qualifiesForGiveaway.slug" class="flex items-center gap-2 text-sm font-semibold text-amber-800 hover:text-amber-600 transition-colors">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M20 12v8H4v-8M2 8h20v4H2V8zM12 8v12M12 8H7.5a2.5 2.5 0 110-5C10.5 3 12 8 12 8zM12 8h4.5a2.5 2.5 0 100-5C13.5 3 12 8 12 8z" />
                </svg>
                <span>{{ t('Eligible for') }} {{ qualifiesForGiveaway.grand_prize }}</span>
                <svg class="h-3.5 w-3.5 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
              </a>
            </div>

            <ProductVariantPicker
              :product="product"
              :option-groups="optionGroups"
              :selected-options="selectedOptions"
              :use-grouped-variant-picker="useGroupedVariantPicker"
              :selected-variant-id="selectedVariantId"
              :get-group-choices="getGroupChoices"
              @update-option-selection="handleOptionSelection"
              @select-variant="selectVariant"
            />

            <ProductAddToCart
              :product="product"
              :disabled="isOutOfStock || form.processing"
              :label="ctaLabel"
              :whats-app-busy="creatingIntent"
              :whats-app-label="creatingIntent ? t('Preparing...') : t('Order via WhatsApp')"
              :success-message="successMessage"
              :quantity="form.quantity"
              @submit="submit"
              @decrement-qty="decrementQty"
              @increment-qty="incrementQty"
              @update-qty="form.quantity = Math.max(1, $event)"
              @whatsapp="orderViaWhatsApp"
            />

            <ProductConfidencePanel :lead-time-days="product.lead_time_days ?? 7" />

            <ProductDetailTabs
              :active-tab="activeTab"
              :description-html="descriptionHtml"
              :description-text="descriptionText"
              :review-highlights="reviewHighlights"
              :spec-entries="specEntries"
              :lead-time="product.lead_time_days ?? 7"
              :review-summary="reviewSummary"
              :reviews="reviewsState"
              :auth-user="authUser"
              :reviewable-items="reviewableItems"
              :review-form="reviewForm"
              :review-notice="reviewNotice"
              :images-error="imagesError"
              :helpful-loading-id="helpfulLoadingId"
              :is-review-voted="isReviewVoted"
              :review-bar-width="reviewBarWidth"
              @update:active-tab="activeTab = $event"
              @submit-review="submitReview"
              @images-change="onImagesChange"
              @vote-helpful="voteHelpful"
            />
          </div>
        </div>

        <section v-if="bundleProducts.length" class="mt-14 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">{{ t('Frequently bought together') }}</h2>
          </div>
          <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-3">
            <ProductCard
              v-for="item in bundleProducts"
              :key="item.id"
              :product="item"
              :currency="currency"
              :promotions="activePromotions"
            />
          </div>
        </section>

        <section class="mt-10 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">{{ t('Related products') }}</h2>
            <Link
              :href="relatedBrowseHref"
              class="text-xs font-semibold text-red-500 hover:text-red-600"
            >
              {{ t('Browse all') }}
            </Link>
          </div>
          <div v-if="relatedProducts.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <ProductCard
              v-for="item in relatedProducts"
              :key="item.id"
              :product="item"
              :currency="currency"
              :promotions="activePromotions"
            />
          </div>
          <div
            v-else
            class="rounded-xl border border-slate-100 p-5 text-sm text-slate-400"
          >
            {{ t('Explore more products with predictable delivery and upfront customs details.') }}
          </div>
        </section>
      </div>
    </div>

    <LoginRequiredModal
      :show="showLoginPrompt"
      :title="t('You must log in first to continue shopping.')"
      :message="t('Log in to add this item to your cart and continue checkout.')"
      @close="showLoginPrompt = false"
      @login="router.visit('/login')"
    />

    <ProductStickyBar
      :title="product.name"
      :price="displayPriceFormatted"
      :compare-at="compareAtForDisplay ? compareAtFormatted : ''"
      :cta-label="stickyCtaLabel"
      :disabled="stickyCtaDisabled"
      :rating="reviewSummary.count > 0 ? reviewSummary.average : null"
      :stock-label="stockBadge.label ?? ''"
      :in-stock="!isOutOfStock"
      @submit="submit"
      @whatsapp="orderViaWhatsApp"
    />
  </StorefrontLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useMultipleJsonLd } from '@/composables/useJsonLd.js'
import { useProductCartForm } from '@/composables/useProductCartForm.js'
import { useProductVariantSelection } from '@/composables/useProductVariantSelection.js'
import { useProductPromotion } from '@/composables/useProductPromotion.js'
import { useProductReviews } from '@/composables/useProductReviews.js'
import { useWhatsAppCheckout } from '@/composables/useWhatsAppCheckout.js'
import { useUserPreferences } from '@/composables/useUserPreferences.js'
import { useTranslations } from '@/i18n'

import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import Breadcrumbs from '@/Components/Breadcrumbs.vue'
import ProductGallery from '@/Components/ProductGallery.vue'
import ProductInfo from '@/Components/ProductInfo.vue'
import ProductVariantPicker from '@/Components/ProductVariantPicker.vue'
import ProductAddToCart from '@/Components/ProductAddToCart.vue'
import ProductConfidencePanel from '@/Components/ProductConfidencePanel.vue'
import ProductDetailTabs from '@/Components/ProductDetailTabs.vue'
import ProductCard from '@/Components/ProductCard.vue'
import ProductStickyBar from '@/Components/ProductStickyBar.vue'
import LoginRequiredModal from '@/Components/LoginRequiredModal.vue'
import { useRecentlyViewed } from '@/composables/useRecentlyViewed.js'

const page = usePage()
const { t } = useTranslations()
const { formatCurrency, convertCurrency, currentCurrency } = useUserPreferences()

const props = defineProps({
  product: { type: Object, required: true },
  currency: { type: String, default: 'USD' },
  reviews: { type: Array, default: () => [] },
  reviewSummary: { type: Object, default: () => ({ count: 0, average: 0, breakdown: {} }) },
  reviewHighlights: { type: Array, default: () => [] },
  relatedProducts: { type: Array, default: () => [] },
  bundleProducts: { type: Array, default: () => [] },
  reviewableItems: { type: Array, default: () => [] },
  breadcrumbs: { type: Array, default: () => [] },
})

const displayCurrency = computed(() => currentCurrency.value || props.currency)

const { addProduct } = useRecentlyViewed()
addProduct(props.product)

const activeTab = ref('description')
const selectedImage = ref(null)

// ---- Product ref for composables ----
const productRef = computed(() => props.product)

// ---- Cart ----
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
} = useProductCartForm({ product: props.product, t })

// ---- WhatsApp ----
const { creatingIntent, startWhatsAppCheckout } = useWhatsAppCheckout({ t })

// ---- Variant selection ----
const {
  normalizedVariants,
  optionGroups,
  useGroupedVariantPicker,
  selectedOptions,
  getGroupChoices,
  updateOptionSelection,
  resolveVariantState,
} = useProductVariantSelection(productRef, selectedVariantId)

// Auto-resolve initial variant selection when options are ready
watch(optionGroups, (groups) => {
  if (groups.length > 0) {
    const resolved = resolveVariantState()
    selectedOptions.value = resolved.selection
    if (resolved.variant?.id) {
      selectVariant(resolved.variant.id)
    }
  }
}, { immediate: true })

const handleOptionSelection = (groupKey, value) => {
  updateOptionSelection(groupKey, value, selectVariant)
}

// ---- Promotion ----
const {
  activePromotions,
  productPromotion,
  promoCountdown,
  promotionPriceDiscountable,
  compareAtForDisplay,
  compareAtFormatted,
  displayPriceFormatted,
  displayPromotionValue,
} = useProductPromotion(productRef, selectedVariant, displayCurrency, formatCurrency, convertCurrency)

// ---- Reviews ----
const {
  reviewForm,
  reviewNotice,
  reviewsState,
  helpfulLoadingId,
  imagesError,
  onImagesChange,
  submitReview,
  voteHelpful,
  isReviewVoted,
  reviewBarWidth,
} = useProductReviews(productRef, t)

// ---- Computed helpers ----

const breadcrumbs = computed(() => props.breadcrumbs ?? [])
const relatedBrowseHref = computed(() => props.product.category_href || '/products')
const authUser = computed(() => page.props.auth?.user ?? null)

const stickyCtaDisabled = computed(() => form.processing || isOutOfStock.value)
const stickyCtaLabel = computed(() => {
  if (form.processing) return t('Adding...')
  if (isOutOfStock.value) return t('Out of stock')
  return t('Add to cart')
})
const ctaLabel = computed(() => {
  if (form.processing) return t('Adding...')
  if (isOutOfStock.value) return t('Out of stock')
  return t('Add to cart')
})

const qualifiesForGiveaway = computed(() => {
  const campaign = page.props.luckyDraw || null
  if (!campaign || !campaign.accepting_entries) return null

  const price = selectedVariant.value?.price ?? props.product.selling_price ?? 0
  const thresholdRaw = campaign.min_order_amount_usd ?? campaign.min_order_amount
  const threshold = Number(thresholdRaw || 0)

  if (threshold <= 0 || Number(price) < threshold) return null

  return campaign
})

// ---- Gallery ----
const galleryImages = computed(() => {
  const images = [
    selectedVariant.value?.variant_image ?? null,
    ...(Array.isArray(props.product.media) ? props.product.media : []),
  ].filter(Boolean)
  return [...new Set(images)]
})

const selectedImageIndex = computed(() => {
  if (!selectedImage.value) return 0
  const index = galleryImages.value.indexOf(selectedImage.value)
  return index >= 0 ? index : 0
})

const setGalleryImageByIndex = (index) => {
  const images = galleryImages.value
  if (!images.length) {
    selectedImage.value = null
    return
  }
  selectedImage.value = images[(index + images.length) % images.length]
}

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

// ---- Description / Specs ----
const rawDescription = computed(() => String(props.product.description ?? '').trim())

const escapeHtml = (value) =>
  String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')

const stripHtml = (value) => {
  if (!value) return ''
  if (typeof DOMParser === 'undefined') {
    return String(value).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
  }
  const doc = new DOMParser().parseFromString(String(value), 'text/html')
  return (doc.body.textContent || '').replace(/\s+/g, ' ').trim()
}

const isSafeUrl = (value) => {
  if (!value) return false
  const trimmed = String(value).trim()
  return /^https?:\/\//i.test(trimmed) || /^mailto:/i.test(trimmed)
}

const sanitizeDescriptionHtml = (value) => {
  if (typeof DOMParser === 'undefined') {
    return escapeHtml(stripHtml(value)).replace(/\n/g, '<br>')
  }

  const doc = new DOMParser().parseFromString(String(value), 'text/html')
  const allowedTags = new Set([
    'P', 'BR', 'UL', 'OL', 'LI', 'STRONG', 'B', 'EM', 'I', 'U',
    'A', 'IMG', 'DIV', 'SPAN',
    'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
    'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD',
  ])

  const walk = (node) => {
    const children = Array.from(node.childNodes)
    for (const child of children) {
      if (child.nodeType === 8) { node.removeChild(child); continue }
      if (child.nodeType !== 1) continue

      const tag = child.tagName.toUpperCase()
      if (!allowedTags.has(tag)) {
        const textNode = doc.createTextNode(child.textContent || '')
        node.replaceChild(textNode, child)
        continue
      }

      const attrs = Array.from(child.attributes)
      const href = tag === 'A' ? child.getAttribute('href') : null
      const src = tag === 'IMG' ? child.getAttribute('src') : null
      const alt = tag === 'IMG' ? child.getAttribute('alt') : null

      for (const attr of attrs) child.removeAttribute(attr.name)

      if (tag === 'A' && isSafeUrl(href)) {
        child.setAttribute('href', href)
        child.setAttribute('target', '_blank')
        child.setAttribute('rel', 'noopener noreferrer')
      }

      if (tag === 'IMG') {
        if (!isSafeUrl(src)) { node.removeChild(child); continue }
        child.setAttribute('src', src)
        if (alt) child.setAttribute('alt', alt)
        child.setAttribute('loading', 'lazy')
        child.setAttribute('style', 'max-width: 100%; height: auto;')
      }

      walk(child)
    }
  }

  walk(doc.body)
  return doc.body.innerHTML
}

const descriptionText = computed(() => stripHtml(rawDescription.value))

const descriptionHtml = computed(() => {
  const raw = rawDescription.value
  if (!raw) return ''
  if (!/<[^>]+>/.test(raw)) return escapeHtml(raw).replace(/\n/g, '<br>')
  return sanitizeDescriptionHtml(raw)
})

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
  if (specs && typeof specs === 'object') return specs
  return {}
})

const productVideos = computed(() => {
  const videos = Array.isArray(props.product.videos) ? props.product.videos : []
  return videos.filter((v) => isSafeUrl(v))
})

// ---- WhatsApp ----
const orderViaWhatsApp = async () => {
  await startWhatsAppCheckout({
    mode: 'product',
    channel: 'web',
    product_id: props.product.id,
    variant_id: selectedVariantId.value,
    quantity: Number(form.quantity || 1),
  })
}

// ---- SEO ----
const metaTitle = computed(() => {
  if (props.product.meta_title) return props.product.meta_title
  const parts = [props.product.name]
  if (props.product.category) parts.push(props.product.category)
  return parts.join(' | ')
})

const metaDescription = computed(() => {
  if (props.product.meta_description) return props.product.meta_description
  const parts = []
  if (descriptionText.value) parts.push(descriptionText.value.substring(0, 150))
  if (props.product.category) parts.push(`Category: ${props.product.category}`)
  return parts.join('. ') || t('Shop this quality product on Simbazu')
})

const productImage = computed(() =>
  props.product.image || (Array.isArray(props.product.media) && props.product.media[0]) || null,
)

const productUrl = computed(() => props.product.url || props.product.href || window.location.href)

const imageAltText = computed(() => {
  const parts = [props.product.name]
  if (props.product.category) parts.push(props.product.category)
  if (selectedVariant.value?.title && selectedVariant.value.title !== props.product.name) {
    parts.push(selectedVariant.value.title)
  }
  return parts.join(' - ')
})

// ---- JSON-LD ----
const productSchema = computed(() => {
  if (!props.product.name) return '{}'

  const schema = {
    '@context': 'https://schema.org/',
    '@type': 'Product',
    name: props.product.name,
    description: metaDescription.value,
    url: productUrl.value,
  }

  if (productImage.value) {
    schema.image = Array.isArray(props.product.media) ? props.product.media : [productImage.value]
  }

  if (props.product.brand) {
    schema.brand = {
      '@type': 'Brand',
      name: props.product.brand,
    }
  }

  if (selectedVariant.value?.gtin) {
    schema.gtin = selectedVariant.value.gtin
  } else if (props.product.gtin) {
    schema.gtin = props.product.gtin
  }

  if (props.product.price) {
    const offerPrice = selectedVariant.value?.price ?? props.product.price
    const compareAt = selectedVariant.value?.compare_at_price ?? props.product.compare_at_price
    const offer = {
      '@type': 'Offer',
      price: offerPrice,
      priceCurrency: props.currency || 'USD',
      availability:
        props.product.stock_on_hand > 0
          ? 'https://schema.org/InStock'
          : 'https://schema.org/OutOfStock',
      url: productUrl.value,
      priceValidUntil: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    }
    if (compareAt && Number(compareAt) > Number(offerPrice)) {
      offer.priceSpecification = {
        '@type': 'UnitPriceSpecification',
        price: offerPrice,
        priceCurrency: props.currency || 'USD',
      }
    }
    schema.offers = offer
  }

  schema.hasMerchantReturnPolicy = {
    '@type': 'MerchantReturnPolicy',
    applicableCountry: 'US',
    returnPolicyCategory: 'https://schema.org/MerchantReturnFiniteReturnWindow',
    merchantReturnDays: 30,
    returnMethod: 'https://schema.org/ReturnByMail',
    returnFees: 'https://schema.org/FreeReturn',
  }

  schema.shippingDetails = {
    '@type': 'OfferShippingDetails',
    shippingDestination: {
      '@type': 'DefinedRegion',
      addressCountry: 'US',
    },
    deliveryTime: {
      '@type': 'ShippingDeliveryTime',
      handlingTime: {
        '@type': 'QuantitativeValue',
        minValue: 1,
        maxValue: 3,
        unitCode: 'DAY',
      },
      transitTime: {
        '@type': 'QuantitativeValue',
        minValue: 7,
        maxValue: 18,
        unitCode: 'DAY',
      },
    },
  }

  if (props.reviewSummary.count > 0) {
    schema.aggregateRating = {
      '@type': 'AggregateRating',
      ratingValue: props.reviewSummary.average,
      reviewCount: props.reviewSummary.count,
      bestRating: 5,
      worstRating: 1,
    }
  }

  if (props.product.category) {
    schema.category = props.product.category
  }

  return JSON.stringify(schema)
})

const breadcrumbSchema = computed(() => {
  const baseUrl = window.location.origin
  const items = breadcrumbs.value.map((crumb, index) => ({
    '@type': 'ListItem',
    position: index + 1,
    name: crumb.label,
    item: crumb.href ? `${baseUrl}${crumb.href}` : productUrl.value,
  }))

  return JSON.stringify({
    '@context': 'https://schema.org/',
    '@type': 'BreadcrumbList',
    itemListElement: items,
  })
})

useMultipleJsonLd([productSchema, breadcrumbSchema])
</script>
