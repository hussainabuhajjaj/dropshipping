<template>
  <div class="space-y-3">
    <div class="text-center text-xs font-medium text-slate-500">{{ t('Express checkout') }}</div>
    <div class="text-center text-sm font-semibold text-slate-700">{{ displayAmount }}</div>
    
    <div class="flex items-center gap-3">
      <!-- Apple Pay Button -->
      <button
        v-if="canUseApplePay"
        ref="applePayButton"
        type="button"
        class="apple-pay-button apple-pay-button-black flex-1"
        @click="handleApplePay"
      />
      
      <!-- Google Pay Button -->
      <button
        v-if="canUseGooglePay"
        ref="googlePayButton"
        type="button"
        class="google-pay-button flex-1"
        @click="handleGooglePay"
      />

      <!-- Paystack Payment Request Button (for mobile money) -->
      <button
        v-if="showPaystackButton"
        type="button"
        class="btn-secondary flex-1"
        @click="handlePaystackExpress"
      >
        <span class="text-sm">{{ t('Mobile Money') }}</span>
      </button>
    </div>

    <div class="relative">
      <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-slate-200"></div>
      </div>
      <div class="relative flex justify-center text-xs">
        <span class="bg-white px-2 text-slate-500">{{ t('or continue below') }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { convertCurrency, formatCurrency } from '@/utils/currency.js'
import { ref, onMounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const props = defineProps({
  amount: { type: Number, required: true },
  currency: { type: String, required: true },
  displayCurrency: { type: String, default: null },
  stripeKey: { type: String, default: '' },
  paystackKey: { type: String, default: '' },
})

const { t } = useTranslations()
const effectiveDisplayCurrency = computed(() => props.displayCurrency || props.currency)
const displayAmount = computed(() =>
  formatCurrency(convertCurrency(props.amount, 'USD', effectiveDisplayCurrency.value), effectiveDisplayCurrency.value)
)

const canUseApplePay = ref(false)
const canUseGooglePay = ref(false)
const showPaystackButton = computed(() => !!props.paystackKey)

const applePayButton = ref(null)
const googlePayButton = ref(null)

let stripe = null
let googlePayClient = null

onMounted(async () => {
  // Initialize Stripe for Apple Pay/Google Pay
  if (props.stripeKey && window.Stripe) {
    stripe = window.Stripe(props.stripeKey)
    
    // Check Apple Pay availability
    if (window.ApplePaySession && ApplePaySession.canMakePayments()) {
      canUseApplePay.value = true
    }

    // Check Google Pay availability
    const paymentRequest = stripe.paymentRequest({
      country: 'US',
      currency: props.currency.toLowerCase(),
      total: {
        label: 'Total',
        amount: Math.round(props.amount * 100),
      },
      requestPayerName: true,
      requestPayerEmail: true,
    })

    const result = await paymentRequest.canMakePayment()
    if (result && result.googlePay) {
      canUseGooglePay.value = true
    }
  }
})

const handleApplePay = async () => {
  if (!stripe) return

  try {
    // Request payment intent from backend
    const response = await fetch('/express-checkout/payment-intent', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({
        provider: 'stripe',
      }),
    })

    const { clientSecret } = await response.json()

    const paymentRequest = stripe.paymentRequest({
      country: 'US',
      currency: props.currency.toLowerCase(),
      total: {
        label: 'Total',
        amount: Math.round(props.amount * 100),
      },
      requestPayerName: true,
      requestPayerEmail: true,
      requestShipping: true,
    })

    paymentRequest.on('paymentmethod', async (ev) => {
      const { error } = await stripe.confirmCardPayment(
        clientSecret,
        { payment_method: ev.paymentMethod.id },
        { handleActions: false }
      )

      if (error) {
        ev.complete('fail')
        return
      }

      ev.complete('success')

      // Complete order on backend
      await completeOrder('stripe', {
        payment_intent_id: clientSecret.split('_secret')[0],
        email: ev.payerEmail,
        phone: ev.payerPhone || '',
        shipping_address: {
          name: ev.shippingAddress.recipient,
          line1: ev.shippingAddress.addressLine[0],
          line2: ev.shippingAddress.addressLine[1] || '',
          city: ev.shippingAddress.city,
          state: ev.shippingAddress.region,
          postal_code: ev.shippingAddress.postalCode,
          country: ev.shippingAddress.country,
        },
      })
    })

    paymentRequest.show()
  } catch (error) {
    console.error('Apple Pay error:', error)
  }
}

const handleGooglePay = async () => {
  if (!stripe) return

  try {
    const response = await fetch('/express-checkout/payment-intent', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({
        provider: 'stripe',
      }),
    })

    const { clientSecret } = await response.json()

    const paymentRequest = stripe.paymentRequest({
      country: 'US',
      currency: props.currency.toLowerCase(),
      total: {
        label: 'Total',
        amount: Math.round(props.amount * 100),
      },
      requestPayerName: true,
      requestPayerEmail: true,
      requestShipping: true,
    })

    paymentRequest.on('paymentmethod', async (ev) => {
      const { error } = await stripe.confirmCardPayment(
        clientSecret,
        { payment_method: ev.paymentMethod.id },
        { handleActions: false }
      )

      if (error) {
        ev.complete('fail')
        return
      }

      ev.complete('success')

      await completeOrder('stripe', {
        payment_intent_id: clientSecret.split('_secret')[0],
        email: ev.payerEmail,
        phone: ev.payerPhone || '',
        shipping_address: {
          name: ev.shippingAddress.recipient,
          line1: ev.shippingAddress.addressLine[0],
          line2: ev.shippingAddress.addressLine[1] || '',
          city: ev.shippingAddress.city,
          state: ev.shippingAddress.region,
          postal_code: ev.shippingAddress.postalCode,
          country: ev.shippingAddress.country,
        },
      })
    })

    paymentRequest.show()
  } catch (error) {
    console.error('Google Pay error:', error)
  }
}

const handlePaystackExpress = async () => {
  try {
    const response = await fetch('/express-checkout/payment-intent', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({
        provider: 'paystack',
      }),
    })

    const { public_key, amount, currency } = await response.json()

    // Use Paystack Popup
    const handler = window.PaystackPop.setup({
      key: public_key,
      email: 'customer@example.com', // Get from form or user
      amount: amount,
      currency: currency,
      onClose: () => {
        console.log('Payment cancelled')
      },
      callback: async (response) => {
        await completeOrder('paystack', {
          reference: response.reference,
          email: 'customer@example.com', // Get from form
          phone: '', // Get from form
          shipping_address: {
            // Get from form or prompt user
            name: 'Customer Name',
            line1: 'Address',
            city: 'City',
            country: 'CI',
          },
        })
      },
    })

    handler.openIframe()
  } catch (error) {
    console.error('Paystack express error:', error)
  }
}

const completeOrder = async (provider, data) => {
  try {
    const response = await fetch('/express-checkout/complete', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({
        provider,
        ...data,
      }),
    })

    const result = await response.json()

    if (result.success) {
      router.visit(result.redirect_url)
    }
  } catch (error) {
    console.error('Order completion error:', error)
  }
}
</script>

<style scoped>
.apple-pay-button {
  -webkit-appearance: -apple-pay-button;
  -apple-pay-button-type: buy;
  height: 44px;
  border-radius: 8px;
  cursor: pointer;
}

.apple-pay-button-black {
  -apple-pay-button-style: black;
}

.google-pay-button {
  height: 44px;
  background-color: #000;
  color: #fff;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
}
</style>
