<template>
    <StorefrontLayout>
        <div class="max-w-7xl mx-auto grid lg:grid-cols-3 gap-6">

            <!-- LEFT -->
            <div class="lg:col-span-2 space-y-6">

                <!-- HEADER -->
                <section class="flex justify-between items-start">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">
                            Order #{{ order.number }}
                        </h1>

                        <div class="flex gap-2 mt-2">
              <span :class="statusBadge(order.status)">
                {{ order.status }}
              </span>
                            <span :class="paymentBadge(order.payment_status)">
                {{ order.payment_status }}
              </span>
                        </div>

                        <p class="text-sm text-slate-500 mt-2">
                            {{ formatDate(order.placed_at) }}
                        </p>
                    </div>


                </section>

                <!-- ITEMS -->
                <section class="bg-white rounded-2xl shadow-sm border p-5">
                    <h2 class="font-semibold mb-4 text-slate-900">Items</h2>

                    <div
                        v-for="item in order.items"
                        :key="item.id"
                        class="group flex gap-4 p-4 rounded-xl border hover:shadow-md transition"
                    >
                        <!-- IMAGE -->
                        <div class="w-16 h-16 bg-slate-100 rounded-lg overflow-hidden">
                            <img
                                v-if="item.media?.[0]"
                                :src="item.media[0]"
                                class="w-full h-full object-cover group-hover:scale-105 transition"
                            />
                        </div>

                        <!-- INFO -->
                        <div class="flex-1">
                            <h3 class="font-semibold text-slate-900">
                                {{ item.name }}
                            </h3>

                            <p class="text-xs text-slate-500">
                                {{ item.variant }}
                            </p>

                            <div class="flex justify-between mt-2 text-sm">
                <span class="text-slate-500">
                  {{ item.quantity }} × {{ formatMoney(item.unit_price, order.currency) }}
                </span>

                                <span class="font-semibold text-slate-900">
                  {{ formatMoney(item.total, order.currency) }}
                </span>
                            </div>
                        </div>

                        <!-- ACTION -->
                        <div class="flex items-center">
                            <button
                                v-if="item.fulfillment_status === 'fulfilled'"
                                @click="openReturnModal(item)"
                                class="text-xs text-blue-600 hover:underline"
                            >
                                Return
                            </button>
                        </div>
                    </div>
                </section>

                <!-- PAYMENTS -->
                <section class="bg-white rounded-2xl shadow-sm border p-5">
                    <h2 class="font-semibold mb-4">Payments</h2>

                    <div v-if="order.payments.length" class="space-y-3">
                        <div
                            v-for="payment in order.payments"
                            :key="payment.id"
                            class="flex justify-between items-center border rounded-xl p-4 hover:shadow-sm transition"
                        >
                            <div>
                                <p class="font-semibold">{{ payment.provider }}</p>
                                <p class="text-xs text-slate-500">{{ payment.status }}</p>
                            </div>

                            <div class="text-right">
                                <p class="font-semibold">
                                    {{ formatMoney(payment.amount, payment.currency) }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ formatDate(payment.paid_at) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <p v-else class="text-sm text-slate-500">
                        No payments yet
                    </p>
                </section>

            </div>

            <!-- RIGHT SIDEBAR -->
            <div class="space-y-6">

                <!-- SUMMARY (STICKY 🔥) -->
                <div class="bg-white rounded-2xl shadow-sm border p-5 sticky top-6">
                    <h2 class="font-semibold mb-4">Summary</h2>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span>{{ formatMoney(order.subtotal, order.currency) }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span>Shipping</span>
                            <span>{{ formatMoney(order.shipping_total, order.currency) }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span>Tax</span>
                            <span>{{ formatMoney(order.tax_total, order.currency) }}</span>
                        </div>

                        <div class="flex justify-between text-red-500">
                            <span>Discount</span>
                            <span>- {{ formatMoney(order.discount_total, order.currency) }}</span>
                        </div>

                        <div class="border-t pt-3 flex justify-between font-bold text-lg">
                            <span>Total</span>
                            <span>{{ formatMoney(order.grand_total, order.currency) }}</span>
                        </div>
                    </div>
                </div>

                <!-- CUSTOMER -->
                <div class="bg-white rounded-2xl shadow-sm border p-5">
                    <h2 class="font-semibold mb-3">Customer</h2>

                    <p class="font-medium">{{ order.customer?.name }}</p>
                    <p class="text-sm text-slate-500">{{ order.customer?.email }}</p>
                </div>

                <!-- SHIPPING -->
                <div class="bg-white rounded-2xl shadow-sm border p-5">
                    <h2 class="font-semibold mb-3">Shipping</h2>

                    <div class="text-sm text-slate-600">
                        <p>{{ order.shippingAddress?.name }}</p>
                        <p>{{ order.shippingAddress?.line1 }}</p>
                        <p>{{ order.shippingAddress?.city }}</p>
                        <p>{{ order.shippingAddress?.country }}</p>
                    </div>
                </div>

            </div>

        </div>
    </StorefrontLayout>
</template>
<script setup>
import { reactive, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  order: { type: Object, required: true },
})

const page = usePage()
const { t, locale } = useTranslations()
const reviewNotice = ref(page.props.flash?.review_notice ?? '')
const reviewNoticeItemId = ref(null)
const returnNotice = ref(page.props.flash?.return_notice ?? '')
const returnNoticeItemId = ref(null)

const reviewForms = reactive({})
const returnForms = reactive({})
props.order.items.forEach((item) => {
  reviewForms[item.id] = useForm({
    order_item_id: item.id,
    rating: 5,
    title: '',
    body: '',
  })
  returnForms[item.id] = useForm({
    order_item_id: item.id,
    reason: '',
    notes: '',
  })
})
const statusBadge = (status) => {
    return {
        'px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700': status === 'pending',
        'px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700': status === 'fulfilled',
        'px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700': status === 'cancelled',
    }[status] || 'px-2 py-1 bg-slate-100 text-slate-600 rounded-full text-xs'
}

const paymentBadge = (status) => {
    return {
        'px-2 py-1 rounded-full text-xs bg-orange-100 text-orange-700': status === 'pending',
        'px-2 py-1 rounded-full text-xs bg-green-100 text-green-700': status === 'paid',
    }[status] || 'px-2 py-1 bg-slate-100 text-slate-600 rounded-full text-xs'
}
const submitReview = (item) => {
  const form = reviewForms[item.id]
  if (! form || ! item.product_slug) {
    return
  }
  form.post(route('products.reviews.store', { product: item.product_slug }), {
    preserveScroll: true,
    onSuccess: () => {
      reviewNotice.value = page.props.flash?.review_notice ?? t('Review submitted.')
      reviewNoticeItemId.value = item.id
    },
  })
}

// const submitReturn = (item) => {
//   const form = returnForms[item.id]
//   if (! form) {
//     return
//   }
//   form.post(route('returns.store'), {
//     preserveScroll: true,
//     onSuccess: () => {
//       returnNotice.value = page.props.flash?.return_notice ?? t('Return request submitted.')
//       returnNoticeItemId.value = item.id
//     },
//   })
// }

const formatDate = (value) => {
  if (! value) {
    return '-'
  }
  return new Date(value).toLocaleDateString(locale.value || 'en')
}

const formatMoney = (value, currency = 'XOF') => {
  const number = Number(value ?? 0)
  const decimals = currency === 'XOF' ? 0 : 2
  return number.toLocaleString('en-US', {
    style: 'currency',
    currency: currency,
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  })
}

const getItemStatusClass = (status) => {
  const classes = {
    pending: 'bg-slate-100 text-slate-700',
    processing: 'bg-blue-100 text-blue-700',
    fulfilled: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
  }
  return classes[status] || 'bg-slate-100 text-slate-700'
}

const handleImageError = (event) => {
  event.target.src = ''
  event.target.classList.add('hidden')
}

// Return modal state
const showReturnModal = ref(false)
const selectedItem = ref(null)
const returnForm = ref({
  reason: '',
  notes: '',
  processing: false
})

const openReturnModal = (item) => {
  selectedItem.value = item
  returnForm.value = { reason: '', notes: '', processing: false }
  showReturnModal.value = true
}

const closeReturnModal = () => {
  showReturnModal.value = false
  selectedItem.value = null
  returnForm.value = { reason: '', notes: '', processing: false }
}

const submitReturn = async () => {
  if (!returnForm.value.reason) {
    return
  }

  returnForm.value.processing = true

  try {
    await returnForms[selectedItem.value.id].post(route('returns.store'), {
      onSuccess: () => {
        returnNotice.value = page.props.flash?.return_notice ?? t('Return request submitted.')
        returnNoticeItemId.value = selectedItem.value.id
        closeReturnModal()
      },
      onError: (errors) => {
        returnForm.value.processing = false
        console.error('Return request failed:', errors)
      }
    })
  } catch (error) {
    returnForm.value.processing = false
    console.error('Return request error:', error)
  }
}
</script>
