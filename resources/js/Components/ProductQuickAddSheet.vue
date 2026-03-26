<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-350 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-250 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-[160] bg-slate-950/45 backdrop-blur-[2px]"
        @click="requestClose"
      />
    </Transition>

    <Transition
      enter-active-class="transition-all duration-450 ease-[cubic-bezier(0.16,1,0.3,1)]"
      enter-from-class="translate-y-[100%] opacity-0 scale-[0.992]"
      enter-to-class="translate-y-0 opacity-100 scale-100"
      leave-active-class="transition-all duration-300 ease-[cubic-bezier(0.4,0,1,1)]"
      leave-from-class="translate-y-0 opacity-100 scale-100"
      leave-to-class="translate-y-[100%] opacity-0 scale-[0.992]"
    >
      <section
        v-if="isOpen"
        class="fixed inset-x-0 bottom-0 z-[170] mx-auto flex max-h-[92dvh] w-full max-w-2xl flex-col overflow-hidden rounded-t-[28px] bg-white shadow-[0_-24px_60px_rgba(15,23,42,0.24)] will-change-transform"
        :style="sheetStyle"
        @click.stop
        @touchstart.passive="onTouchStart"
        @touchmove.passive="onTouchMove"
        @touchend="onTouchEnd"
      >
        <div class="sticky top-0 z-10 border-b border-slate-100 bg-white/95 px-4 pb-3 pt-3 backdrop-blur sm:px-6">
          <div class="flex items-center justify-center">
            <button
              type="button"
              class="h-1.5 w-14 rounded-full bg-slate-300"
              :aria-label="t('Close quick add')"
              @click="requestClose"
            />
          </div>

          <div class="mt-4 flex items-start justify-between gap-4">
            <div>
              <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">{{ t('Quick add') }}</p>
              <h2 class="mt-1 line-clamp-2 text-lg font-semibold tracking-tight text-slate-900 sm:text-xl">{{ product.name }}</h2>
              <p v-if="product.category" class="mt-1 text-xs text-slate-500">{{ product.category }}</p>
            </div>
            <button
              type="button"
              class="rounded-full border border-slate-200 p-2 text-slate-500 transition hover:border-slate-300 hover:text-slate-900"
              :aria-label="t('Close')"
              @click="requestClose"
            >
              <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
              </svg>
            </button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto overscroll-contain px-4 py-3 sm:px-6 sm:py-4">
          <div class="mx-auto grid w-full max-w-xl gap-4 sm:grid-cols-[180px,1fr] sm:gap-5">
            <div class="mx-auto w-full max-w-[11rem] sm:mx-0 sm:max-w-none">
              <div class="overflow-hidden rounded-2xl bg-slate-100">
                <img
                  v-if="selectedImage"
                  :src="selectedImage"
                  :alt="product.name"
                  class="h-36 w-full object-cover sm:aspect-[4/5] sm:h-full"
                />
                <div class="flex h-36 items-center justify-center text-xs text-slate-400 sm:aspect-[4/5] sm:h-full">
                  {{ t('Image coming soon') }}
                </div>
              </div>

              <div v-if="product.media?.length > 1" class="mt-2 flex justify-center gap-2 overflow-x-auto pb-1 sm:justify-start">
                <button
                  v-for="(image, index) in product.media"
                  :key="`${product.id}-media-${index}`"
                  type="button"
                  class="h-12 w-12 shrink-0 overflow-hidden rounded-xl border transition sm:h-14 sm:w-14"
                  :class="image === selectedImage ? 'border-slate-900' : 'border-slate-200 hover:border-slate-300'"
                  @click="selectedImage = image"
                >
                  <img :src="image" :alt="product.name" class="h-full w-full object-cover" />
                </button>
              </div>
            </div>

            <div class="space-y-3 sm:space-y-4">
              <div class="flex flex-wrap items-center gap-3">
                <span class="text-xl font-semibold text-slate-900 sm:text-2xl">{{ displayPriceFormatted }}</span>
                <span v-if="compareAtFormatted" class="text-sm text-slate-400 line-through">{{ compareAtFormatted }}</span>
                <span
                  v-if="stockBadge.label"
                  :class="stockBadge.class"
                  class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold"
                >
                  <span class="h-2 w-2 rounded-full" :class="stockBadge.dot" />
                  {{ stockBadge.label }}
                </span>
              </div>

              <p class="line-clamp-3 text-sm leading-5 text-slate-600 sm:line-clamp-none sm:leading-6">
                {{ descriptionText || t('Product details are available on the full product page.') }}
              </p>

              <div v-if="product.variants?.length" class="space-y-3">
                <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Variant') }}</label>
                <div class="flex gap-2 overflow-x-auto pb-1 sm:flex-wrap">
                  <button
                    v-for="variant in product.variants"
                    :key="variant.id"
                    type="button"
                    class="min-h-10 shrink-0 rounded-full border px-3 py-2 text-xs font-semibold transition"
                    :class="variant.id === selectedVariantId ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-800 hover:border-slate-300'"
                    @click="selectVariant(variant.id)"
                  >
                    {{ variant.title }}
                  </button>
                </div>
                <p v-if="requiresVariantSelection && !selectedVariantId" class="text-xs text-amber-700">
                  {{ t('Select a variant before adding this item to your cart.') }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="sticky bottom-0 border-t border-slate-100 bg-white/95 px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur sm:px-6">
          <div class="mx-auto flex w-full max-w-xl flex-col gap-3">
            <div class="flex items-center gap-3">
              <div class="inline-flex min-w-0 items-center rounded-full border border-slate-200 px-2 py-1">
                <button
                  type="button"
                  class="h-9 w-9 rounded-full text-slate-600 transition hover:bg-slate-100"
                  @click="decrementQty"
                >
                  -
                </button>
                <input
                  v-model.number="form.quantity"
                  type="number"
                  min="1"
                  class="w-12 border-0 bg-transparent text-center text-sm font-semibold text-slate-900 focus:ring-0"
                />
                <button
                  type="button"
                  class="h-9 w-9 rounded-full text-slate-600 transition hover:bg-slate-100"
                  @click="incrementQty"
                >
                  +
                </button>
              </div>

              <button
                type="button"
                class="btn-primary min-h-11 flex-1"
                :disabled="!canSubmit"
                @click="submit"
              >
                {{ form.processing ? t('Adding...') : isOutOfStock ? t('Out of stock') : t('Add to cart') }}
              </button>
            </div>

            <Link
              :href="product.href || `/products/${product.slug}`"
              class="btn-secondary min-h-11 w-full justify-center text-center"
              @click="requestClose"
            >
              {{ t('View details') }}
            </Link>

            <p v-if="successMessage" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
              {{ successMessage }}
            </p>
          </div>
        </div>
      </section>
    </Transition>

    <LoginRequiredModal :show="showLoginPrompt" @close="showLoginPrompt = false" />
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import LoginRequiredModal from '@/Components/LoginRequiredModal.vue'
import { useTranslations } from '@/i18n'
import { useUserPreferences } from '@/composables/useUserPreferences.js'
import { useProductCartForm } from '@/composables/useProductCartForm.js'

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  product: { type: Object, required: true },
  currency: { type: String, default: 'USD' },
})

const emit = defineEmits(['close'])

const { t } = useTranslations()
const { currentCurrency, formatCurrency, convertCurrency } = useUserPreferences()
const displayCurrency = computed(() => currentCurrency.value || props.currency)

const selectedImage = ref(props.product.media?.[0] ?? null)
const touchStartY = ref(0)
const dragOffset = ref(0)
const hasHistoryEntry = ref(false)

const {
  canSubmit,
  decrementQty,
  form,
  incrementQty,
  isOutOfStock,
  requiresVariantSelection,
  selectVariant,
  selectedVariant,
  selectedVariantId,
  showLoginPrompt,
  stockBadge,
  submit: submitToCart,
  successMessage,
} = useProductCartForm({
  product: props.product,
  t,
  requireExplicitVariantSelection: true,
  onAdded: () => {
    window.setTimeout(() => {
      requestClose()
    }, 300)
  },
})

const basePrice = computed(() => Number(selectedVariant.value?.price ?? props.product.price ?? 0))
const compareAtPrice = computed(() => selectedVariant.value?.compare_at_price ?? props.product.compare_at_price ?? null)

const displayPriceFormatted = computed(() =>
  formatCurrency(convertCurrency(basePrice.value, 'USD', displayCurrency.value), displayCurrency.value)
)

const compareAtFormatted = computed(() => {
  if (!compareAtPrice.value) return null
  return formatCurrency(convertCurrency(Number(compareAtPrice.value), 'USD', displayCurrency.value), displayCurrency.value)
})

const descriptionText = computed(() =>
  String(props.product.description ?? '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
)

const sheetStyle = computed(() => ({
  transform: `translateY(${dragOffset.value}px)`,
}))

const closeFromHistory = () => {
  hasHistoryEntry.value = false
  emit('close')
}

const requestClose = () => {
  if (hasHistoryEntry.value && typeof window !== 'undefined') {
    window.history.back()
    return
  }
  closeFromHistory()
}

const onPopState = () => {
  if (!props.isOpen) return
  closeFromHistory()
}

const onTouchStart = (event) => {
  touchStartY.value = event.touches?.[0]?.clientY ?? 0
  dragOffset.value = 0
}

const onTouchMove = (event) => {
  const currentY = event.touches?.[0]?.clientY ?? 0
  dragOffset.value = Math.max(0, currentY - touchStartY.value)
}

const onTouchEnd = () => {
  if (dragOffset.value > 120) {
    requestClose()
  }
  dragOffset.value = 0
}

const submit = () => {
  submitToCart()
}

watch(
  () => props.isOpen,
  (isOpen) => {
    if (typeof window === 'undefined') return

    if (isOpen) {
      selectedImage.value = props.product.media?.[0] ?? null
      if (!hasHistoryEntry.value) {
        window.history.pushState({ quickAdd: props.product.slug }, '', window.location.href)
        hasHistoryEntry.value = true
      }
      document.body.style.overflow = 'hidden'
      return
    }

    dragOffset.value = 0
    document.body.style.overflow = ''
  }
)

onMounted(() => {
  window.addEventListener('popstate', onPopState)
})

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    document.body.style.overflow = ''
    window.removeEventListener('popstate', onPopState)
  }
})
</script>
