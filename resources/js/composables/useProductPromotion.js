import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { usePromoNow, formatCountdown } from '@/composables/usePromoCountdown.js'

function productPromotionForDetails(product, promotions) {
  if (!promotions?.length) return null

  const targeted = promotions.find((p) =>
    (p.targets || []).some((t) => {
      if (t.target_type === 'product') return t.target_id == product.id
      if (t.target_type === 'category') return t.target_id == product.category_id
      return false
    }),
  )
  if (targeted) return targeted

  return promotions.find((p) => p.is_sitewide) ?? null
}

export function useProductPromotion(product, selectedVariant, displayCurrency, formatCurrency, convertCurrency) {
  const page = usePage()
  const now = usePromoNow()

  const activePromotions = computed(() => page.props.promotions || page.props.homepagePromotions || [])

  const productPromotion = computed(() => productPromotionForDetails(product.value, activePromotions.value))

  const promoCountdown = computed(() => formatCountdown(productPromotion.value?.end_at, now.value))

  const promotionPriceDiscountable = computed(() => {
    const promo = productPromotion.value
    if (!promo) return false
    if (promo.value_type !== 'percentage' && promo.value_type !== 'fixed') return false
    if (promo.has_conditions || promo.is_sitewide) return false
    return true
  })

  const basePriceForDisplay = computed(() =>
    Number(selectedVariant.value?.price ?? product.value?.price ?? 0),
  )

  const compareAtForDisplay = computed(() => {
    const compareAt = selectedVariant.value?.compare_at_price
    if (compareAt) return compareAt
    if (promotionPriceDiscountable.value) return basePriceForDisplay.value
    return null
  })

  const displayPrice = computed(() => {
    const base = basePriceForDisplay.value
    if (!promotionPriceDiscountable.value || base <= 0) return base
    if (selectedVariant.value?.compare_at_price) return base

    const promo = productPromotion.value
    if (promo?.value_type === 'percentage') {
      const pct = Number(promo.value ?? 0)
      return Math.max(0, Number((base * (1 - pct / 100)).toFixed(2)))
    }

    const amount = Number(promo?.value ?? 0)
    return Math.max(0, Number((base - amount).toFixed(2)))
  })

  const displayPriceFormatted = computed(() =>
    formatCurrency(convertCurrency(displayPrice.value, 'USD', displayCurrency.value), displayCurrency.value),
  )

  const compareAtFormatted = computed(() =>
    formatCurrency(
      convertCurrency(Number(compareAtForDisplay.value ?? 0), 'USD', displayCurrency.value),
      displayCurrency.value,
    ),
  )

  const displayPromotionValue = (amount) =>
    formatCurrency(convertCurrency(Number(amount ?? 0), 'USD', displayCurrency.value), displayCurrency.value)

  return {
    activePromotions,
    productPromotion,
    promoCountdown,
    promotionPriceDiscountable,
    basePriceForDisplay,
    compareAtForDisplay,
    compareAtFormatted,
    displayPrice,
    displayPriceFormatted,
    displayPromotionValue,
  }
}
