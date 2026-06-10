<template>
  <StorefrontLayout>
    <div class="mx-auto max-w-2xl space-y-6 text-center">
      <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Order placed') }}</p>
      <h1 class="text-3xl font-semibold tracking-tight text-slate-900">{{ t('Thank you') }}</h1>
      <p v-if="page.props.flash?.status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
        {{ page.props.flash.status }}
      </p>
      <p v-if="page.props.errors?.payment" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
        {{ page.props.errors.payment }}
      </p>

      <p class="text-sm text-slate-600">
        <span v-if="paymentStatus === 'paid'">
          {{ t("Your order :number is confirmed. We will share tracking once the supplier dispatches. Delivery to Cote d'Ivoire with transparent customs.", { number: `#${order.number}` }) }}
        </span>
        <span v-else-if="paymentStatus === 'verifying'">
          {{ t('Verifying payment for order :number. Please wait...', { number: `#${order.number}` }) }}
        </span>
        <span v-else>
          {{ t('We have received your order :number. Payment is :payment, and we will confirm once it clears.', { number: `#${order.number}` , payment : paymentStatus }) }}
        </span>
      </p>

      <div class="card-muted p-5 text-left">
        <div class="flex items-center justify-between text-sm">
          <span>{{ t('Status') }}</span>
          <span class="font-semibold text-slate-900">{{ order.status }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between text-sm">
          <span>{{ t('Payment') }}</span>
          <div class="flex items-center gap-2">
            <span v-if="isVerifying" class="animate-spin h-4 w-4 border-2 border-blue-600 border-t-transparent rounded-full"></span>
            <span class="font-semibold text-slate-900">{{ formattedPaymentStatus }}</span>
          </div>
        </div>
        <div class="mt-2 flex items-center justify-between text-sm">
          <span>{{ t('Discount') }}</span>
          <span class="font-semibold text-slate-900">{{ order.currency }} -{{ order.discount_total }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between text-sm">
          <span>{{ t('Total') }}</span>
          <span class="font-semibold text-slate-900">{{ order.currency }} {{ order.grand_total }}</span>
        </div>
        <div v-if="order.items?.length" class="mt-4 space-y-2 text-sm text-slate-600">
          <div v-for="item in order.items" :key="item.id" class="flex items-center justify-between">
            <div>
              <p class="font-medium text-slate-900">{{ item.name }}</p>
              <p class="text-xs text-slate-500">
                {{ item.variant ?? t('Default') }} - {{ t('Qty :quantity', { quantity: item.quantity }) }}
              </p>
            </div>
            <span class="text-sm font-semibold text-slate-900">
              {{ order.currency }} {{ Number(item.total).toFixed(2) }}
            </span>
          </div>
        </div>
        <div class="mt-3 text-sm text-slate-600">
          <p>{{ t('Shipping to:') }}</p>
          <p class="font-medium text-slate-900">{{ order.shippingAddress?.name }}</p>
          <p>{{ order.shippingAddress?.line1 }}</p>
          <p>{{ order.shippingAddress?.city }}, {{ order.shippingAddress?.country }}</p>
        </div>
      </div>

      <div v-if="upsellProducts?.length" class="rounded-xl border border-amber-100 bg-amber-50 p-5 text-left">
        <h3 class="text-sm font-semibold text-amber-900">{{ t('Complete your look') }}</h3>
        <p class="mt-1 text-xs text-amber-700">{{ t('Add these items to your order at no extra shipping cost') }}</p>
        <div class="mt-4 space-y-3">
          <div v-for="product in upsellProducts" :key="product.id" class="flex items-center gap-3 rounded-lg border border-amber-200 bg-white p-3">
            <img v-if="product.image" :src="product.image" :alt="product.name" class="h-14 w-14 rounded-lg object-cover" />
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-slate-900 truncate">{{ product.name }}</p>
              <p class="text-xs font-semibold text-amber-700">{{ product.currency }} {{ Number(product.price).toFixed(2) }}</p>
            </div>
            <Link :href="`/cart/add/${product.id}`" class="shrink-0 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-amber-700">
              {{ t('Add') }}
            </Link>
          </div>
        </div>
      </div>

      <div class="flex flex-col justify-center gap-3 sm:flex-row">
        <Link
          :href="`/orders/track?number=${order.number}&email=${order.email}`"
          class="btn-primary w-full sm:w-auto"
        >
          {{ t('Track order') }}
        </Link>
        
        <!-- Show retry button if verification failed -->
        <button
          v-if="verificationError && paymentStatus !== 'paid'"
          @click="verifyPayment"
          :disabled="isVerifying"
          class="btn-secondary w-full sm:w-auto disabled:opacity-50"
        >
          <span v-if="isVerifying" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full mr-2"></span>
          {{ isVerifying ? t('Verifying...') : t('Retry verification') }}
        </button>
        
        <Link href="/support" class="btn-secondary w-full sm:w-auto">
          {{ t('Contact support') }}
        </Link>
        <Link href="/products" class="btn-ghost w-full sm:w-auto">
          {{ t('Keep shopping') }}
        </Link>
      </div>

      <div class="card p-5 text-left text-sm text-slate-600">
        <p class="font-semibold text-slate-900">{{ t('What happens next') }}</p>
        <ul class="mt-2 space-y-1">
          <li>{{ t('We confirm payment and place your order with the supplier.') }}</li>
          <li>{{ t('Tracking is shared once the supplier dispatches.') }}</li>
          <li>{{ t("Delivery to Cote d'Ivoire typically takes 7 to 18 business days.") }}</li>
        </ul>
        <p class="mt-3 text-xs text-slate-500">
          {{ t('Need help? WhatsApp :number with your order number.', { number: supportWhatsApp }) }}
        </p>
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { useTranslations } from '@/i18n'
import axios from 'axios'

defineProps({
  order: { type: Object, required: true },
  upsellProducts: { type: Array, default: () => [] },
})

const page = usePage()
const { t } = useTranslations()
const supportWhatsApp = page.props.site?.support_whatsapp ?? '+225 00 00 00 00'

// Payment verification state
const paymentStatus = ref(page.props.order?.payment_status || 'pending')
const isVerifying = ref(false)
const verificationError = ref(null)

// Computed properties
const formattedPaymentStatus = computed(() => {
  if (isVerifying.value) return t('Verifying...')
  if (verificationError.value) return t('Verification failed')
  return paymentStatus.value
})

// Extract payment reference from URL or order
const getPaymentReference = () => {
  // Try to get reference from URL query parameters first
  const urlParams = new URLSearchParams(window.location.search)
  const reference = urlParams.get('reference') || urlParams.get('trxref')
  
  // If not in URL, try to get from order payment data
  if (!reference && page.props.order?.payments?.length > 0) {
    const payment = page.props.order.payments.find(p => p.provider === 'paystack')
    return payment?.provider_reference
  }
  
  return reference
}

// Verify payment status
const verifyPayment = async () => {
  const reference = getPaymentReference()
  
  if (!reference) {
    console.log('No payment reference found for verification')
    return
  }

  if (paymentStatus.value === 'paid') {
    console.log('Payment already marked as paid')
    return
  }

  isVerifying.value = true
  verificationError.value = null

  try {
    const response = await axios.get('/api/payments/verify', {
      params: { reference }
    })

    if (response.data.success) {
      // Update payment status from verification response
      paymentStatus.value = response.data.data?.payment_status || 'paid'
      
      // Optionally reload page data if status changed
      if (response.data.data?.payment_status !== page.props.order?.payment_status) {
        setTimeout(() => {
          window.location.reload()
        }, 2000)
      }
    } else {
      verificationError.value = response.data.message || 'Verification failed'
    }
  } catch (error) {
    console.error('Payment verification error:', error)
    verificationError.value = error.response?.data?.message || 'Network error'
  } finally {
    isVerifying.value = false
  }
}

// Auto-verify on page load if payment is not paid
onMounted(() => {
  if (paymentStatus.value !== 'paid' && getPaymentReference()) {
    // Small delay to ensure page is fully loaded
    setTimeout(verifyPayment, 1000)
  }
})
</script>
