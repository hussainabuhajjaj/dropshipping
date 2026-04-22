import { computed, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { toastAlert } from '@/utils/toast'

const readMetaCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

const writeMetaCsrfToken = (token) => {
  if (!token) return

  const existing = document.querySelector('meta[name="csrf-token"]')
  if (existing) {
    existing.setAttribute('content', token)
    return
  }

  const meta = document.createElement('meta')
  meta.setAttribute('name', 'csrf-token')
  meta.setAttribute('content', token)
  document.head.appendChild(meta)
}

const refreshCsrfToken = async () => {
  if (typeof window === 'undefined') {
    return ''
  }

  const response = await fetch(window.location.href, {
    method: 'GET',
    credentials: 'same-origin',
    cache: 'no-store',
    headers: {
      Accept: 'text/html,application/xhtml+xml',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })

  const html = await response.text()
  const match = html.match(/<meta\s+name=["']csrf-token["']\s+content=["']([^"']+)["']/i)
  const token = match?.[1] ?? ''

  if (token) {
    writeMetaCsrfToken(token)
  }

  return token
}

export function useProductCartForm(options) {
  const {
    product,
    t,
    requireExplicitVariantSelection = false,
    onAdded = null,
  } = options

  const page = usePage()
  const selectedVariantId = ref(requireExplicitVariantSelection ? null : product.variants?.[0]?.id ?? null)
  const showLoginPrompt = ref(false)
  const successMessage = ref(page.props.flash?.cart_notice ?? '')
  let successTimeout = null

  const form = useForm({
    product_id: product.id,
    variant_id: selectedVariantId.value,
    quantity: 1,
  })

  const selectedVariant = computed(() =>
    product.variants?.find((variant) => variant.id === selectedVariantId.value) ?? null
  )

  const stockStatus = computed(() => {
    const stock = selectedVariant.value?.stock_on_hand
    const threshold = selectedVariant.value?.low_stock_threshold ?? 5

    if (!selectedVariant.value && product.variants?.length) {
      return { label: '', status: 'pending' }
    }
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
  const requiresVariantSelection = computed(() => Boolean(product.variants?.length))
  const canSubmit = computed(() => {
    if (form.processing || isOutOfStock.value) return false
    if (requiresVariantSelection.value && !selectedVariantId.value) return false
    return true
  })

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

  const selectVariant = (id) => {
    selectedVariantId.value = id
  }

  const submit = async () => {
    form.product_id = product.id
    form.variant_id = selectedVariantId.value

    if (requireExplicitVariantSelection && requiresVariantSelection.value && !selectedVariantId.value) {
      toastAlert('error', t('Select a variant before adding this item to your cart.'))
      return
    }

    let csrfToken = readMetaCsrfToken()
    try {
      csrfToken = await refreshCsrfToken() || csrfToken
    } catch {
      // Fall back to the current token if refreshing the page token fails.
    }

    form.post('/cart', {
      preserveScroll: true,
      preserveState: false, // Force refresh of shared data (including cart)
      headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
      onSuccess: () => {
        successMessage.value = t('Added to cart.')
        clearSuccessSoon()
        if (typeof onAdded === 'function') {
          onAdded()
        }
      },
      onError: (errors) => {
        const message = errors?.cart
          || errors?.message
          || Object.values(errors ?? {}).find((value) => typeof value === 'string')

        if (typeof message === 'string' && message.toLowerCase().includes('log in')) {
          showLoginPrompt.value = true
          return
        }

        toastAlert('error', message || t('Unable to add this item to your cart right now.'))
      },
    })
  }

  return {
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
    stockStatus,
    submit,
    successMessage,
  }
}
