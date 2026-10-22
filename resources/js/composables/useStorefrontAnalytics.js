const isBrowser = () => typeof window !== 'undefined'

const toNumber = (value, fallback = 0) => {
  const number = Number(value)
  return Number.isFinite(number) ? number : fallback
}

const firstPresent = (...values) => values.find((value) => value !== null && value !== undefined && value !== '')

const normalizeProductItem = (product, options = {}) => {
  const variant = options.variant ?? null
  const quantity = Math.max(1, toNumber(options.quantity, product?.quantity ?? 1))
  const price = toNumber(
    firstPresent(
      options.price,
      variant?.price,
      variant?.selling_price,
      product?.price,
      product?.selling_price,
      product?.unit_price,
      product?.total ? toNumber(product.total) / quantity : null,
    ),
  )

  return {
    item_id: String(firstPresent(variant?.sku, variant?.id, product?.sku, product?.id, product?.product_id, product?.variant_id, 'unknown')),
    item_name: String(firstPresent(product?.name, product?.title, product?.snapshot?.name, 'Product')),
    item_variant: firstPresent(variant?.title, product?.variant, product?.variant_title, product?.snapshot?.variant, null),
    item_category: firstPresent(product?.category, product?.category_name, null),
    price,
    quantity,
  }
}

const normalizeLineItem = (line) => {
  const product = line?.product ?? line
  const variant = line?.variant ?? null
  const quantity = Math.max(1, toNumber(line?.quantity, 1))
  const price = toNumber(
    firstPresent(
      line?.price,
      line?.unit_price,
      line?.selling_price,
      line?.total ? toNumber(line.total) / quantity : null,
      product?.price,
      product?.selling_price,
      variant?.price,
    ),
  )

  return normalizeProductItem(product, {
    variant,
    quantity,
    price,
  })
}

const buildCartPayload = (cart = {}) => {
  const lines = Array.isArray(cart.lines) ? cart.lines : (Array.isArray(cart.items) ? cart.items : [])
  const items = lines.map(normalizeLineItem)
  const value = toNumber(firstPresent(cart.value, cart.total, cart.estimated_total, cart.subtotal))
  const currency = String(firstPresent(cart.currency, 'XOF'))

  return {
    currency,
    value,
    items,
    num_items: items.reduce((sum, item) => sum + toNumber(item.quantity), 0),
  }
}

const buildOrderPayload = (order = {}) => {
  const items = Array.isArray(order.items) ? order.items.map(normalizeLineItem) : []

  return {
    transaction_id: String(firstPresent(order.number, order.id, '')),
    order_id: String(firstPresent(order.number, order.id, '')),
    currency: String(firstPresent(order.currency, 'XOF')),
    value: toNumber(firstPresent(order.grand_total, order.total)),
    tax: toNumber(order.tax_total),
    shipping: toNumber(order.shipping_total),
    discount: toNumber(order.discount_total),
    items,
    num_items: items.reduce((sum, item) => sum + toNumber(item.quantity), 0),
  }
}

const contentIds = (items) => items.map((item) => item.item_id).filter(Boolean)

const payloadKey = (eventName, payload) => [
  eventName,
  payload.currency,
  payload.value,
  contentIds(payload.items ?? []).join(','),
].filter(Boolean).join('.')

const metaPayload = (payload) => ({
  currency: payload.currency,
  value: payload.value,
  content_type: 'product',
  content_ids: contentIds(payload.items ?? []),
  contents: (payload.items ?? []).map((item) => ({
    id: item.item_id,
    quantity: item.quantity,
    item_price: item.price,
  })),
  num_items: payload.num_items,
})

const tiktokPayload = (payload) => {
  const firstItem = payload.items?.[0] ?? {}

  return {
    currency: payload.currency,
    value: payload.value,
    quantity: payload.num_items,
    content_id: firstItem.item_id,
    content_name: firstItem.item_name,
    content_type: 'product',
    contents: payload.items,
  }
}

const once = (key, callback) => {
  if (!isBrowser() || !key) return callback()

  const storageKey = `simbazu.analytics.${key}`
  try {
    if (window.sessionStorage?.getItem(storageKey)) return

    callback()
    window.sessionStorage?.setItem(storageKey, '1')
  } catch {
    callback()
  }
}

export function useStorefrontAnalytics() {
  const pushDataLayer = (eventName, payload = {}) => {
    if (!isBrowser()) return

    window.dataLayer = window.dataLayer || []
    try {
      window.dataLayer.push({
        event: eventName,
        ecommerce: payload,
      })
    } catch {
      // Tracking should never block the shopping flow.
    }
  }

  const trackGtag = (eventName, payload = {}) => {
    if (!isBrowser() || typeof window.gtag !== 'function') return
    try {
      window.gtag('event', eventName, payload)
    } catch {
      // Tracking should never block the shopping flow.
    }
  }

  const trackMeta = (eventName, payload = {}) => {
    if (!isBrowser() || typeof window.fbq !== 'function') return

    const events = {
      view_item: 'ViewContent',
      add_to_cart: 'AddToCart',
      begin_checkout: 'InitiateCheckout',
      add_payment_info: 'AddPaymentInfo',
      purchase: 'Purchase',
    }

    const metaEvent = events[eventName]
    try {
      if (metaEvent) {
        window.fbq('track', metaEvent, metaPayload(payload))
        return
      }

      window.fbq('trackCustom', eventName, payload)
    } catch {
      // Tracking should never block the shopping flow.
    }
  }

  const trackTikTok = (eventName, payload = {}) => {
    if (!isBrowser() || typeof window.ttq?.track !== 'function') return

    const events = {
      view_item: 'ViewContent',
      add_to_cart: 'AddToCart',
      begin_checkout: 'InitiateCheckout',
      add_payment_info: 'AddPaymentInfo',
      purchase: 'CompletePayment',
    }

    const tiktokEvent = events[eventName]
    if (!tiktokEvent) return

    try {
      window.ttq.track(tiktokEvent, tiktokPayload(payload))
    } catch {
      // Tracking should never block the shopping flow.
    }
  }

  const trackEvent = (eventName, payload = {}) => {
    pushDataLayer(eventName, payload)
    trackGtag(eventName, payload)
    trackMeta(eventName, payload)
    trackTikTok(eventName, payload)
  }

  const trackViewItem = (product, options = {}) => {
    const item = normalizeProductItem(product, options)
    const payload = {
      currency: String(firstPresent(options.currency, product?.currency, 'XOF')),
      value: toNumber(firstPresent(options.value, item.price * item.quantity)),
      items: [item],
      num_items: item.quantity,
    }

    once(`view_item.${item.item_id}`, () => trackEvent('view_item', payload))
  }

  const trackAddToCart = (product, options = {}) => {
    const item = normalizeProductItem(product, options)
    trackEvent('add_to_cart', {
      currency: String(firstPresent(options.currency, product?.currency, 'XOF')),
      value: toNumber(firstPresent(options.value, item.price * item.quantity)),
      items: [item],
      num_items: item.quantity,
    })
  }

  const trackViewCart = (cart = {}) => {
    const payload = buildCartPayload(cart)
    if (!payload.items.length) return
    trackEvent('view_cart', payload)
  }

  const trackBeginCheckout = (cart = {}) => {
    const payload = buildCartPayload(cart)
    if (!payload.items.length) return
    once(payloadKey('begin_checkout', payload), () => trackEvent('begin_checkout', payload))
  }

  const trackAddPaymentInfo = (cart = {}, options = {}) => {
    const payload = {
      ...buildCartPayload(cart),
      payment_type: options.paymentType ?? null,
    }
    if (!payload.items.length) return
    trackEvent('add_payment_info', payload)
  }

  const trackPurchase = (order = {}) => {
    const payload = buildOrderPayload(order)
    if (!payload.transaction_id || payload.value <= 0) return

    once(`purchase.${payload.transaction_id}`, () => trackEvent('purchase', payload))
  }

  return {
    trackEvent,
    trackViewItem,
    trackAddToCart,
    trackViewCart,
    trackBeginCheckout,
    trackAddPaymentInfo,
    trackPurchase,
  }
}
