<template>
  <StorefrontLayout>
    <div class="space-y-8">
      <section class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Order') }}</p>
          <h1 class="text-2xl font-semibold text-slate-900">#{{ order.number }}</h1>
        </div>
        <div class="text-sm text-slate-500">
          {{ t('Placed :date', { date: formatDate(order.placed_at) }) }}
        </div>
      </section>

      <section class="grid gap-6 lg:grid-cols-3">
        <div class="card p-5">
          <h2 class="text-sm font-semibold text-slate-900">{{ t('Summary') }}</h2>
          <div class="mt-3 space-y-2 text-sm text-slate-600">
            <div class="flex justify-between">
              <span>{{ t('Status') }}</span>
              <span class="font-semibold text-slate-900">{{ order.status }}</span>
            </div>
            <div class="flex justify-between">
              <span>{{ t('Payment') }}</span>
              <span class="font-semibold text-slate-900">{{ order.payment_status }}</span>
            </div>
            <div class="flex justify-between">
              <span>{{ t('Subtotal') }}</span>
              <span>{{ order.currency }} {{ formatMoney(order.subtotal) }}</span>
            </div>
            <div class="flex justify-between">
              <span>{{ t('Shipping') }}</span>
              <span>{{ order.currency }} {{ formatMoney(order.shipping_total) }}</span>
            </div>
            <div class="flex justify-between">
              <span>{{ t('Tax') }}</span>
              <span>{{ order.currency }} {{ formatMoney(order.tax_total) }}</span>
            </div>
            <div class="flex justify-between">
              <span>{{ t('Discount') }}</span>
              <span>- {{ order.currency }} {{ formatMoney(order.discount_total) }}</span>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-2 font-semibold text-slate-900">
              <span>{{ t('Total') }}</span>
              <span>{{ order.currency }} {{ formatMoney(order.grand_total) }}</span>
            </div>
          </div>
        </div>

        <div class="card p-5">
          <h2 class="text-sm font-semibold text-slate-900">{{ t('Shipping') }}</h2>
          <div v-if="order.shippingAddress" class="mt-3 text-sm text-slate-600">
            <p class="font-semibold text-slate-900">{{ order.shippingAddress.name }}</p>
            <p>{{ order.shippingAddress.line1 }} <span v-if="order.shippingAddress.line2">, {{ order.shippingAddress.line2 }}</span></p>
            <p>
              {{ order.shippingAddress.city }}
              <span v-if="order.shippingAddress.state">, {{ order.shippingAddress.state }}</span>
              <span v-if="order.shippingAddress.postal_code"> {{ order.shippingAddress.postal_code }}</span>
            </p>
            <p>{{ order.shippingAddress.country }}</p>
            <p v-if="order.shippingAddress.phone">{{ t('Phone: :phone', { phone: order.shippingAddress.phone }) }}</p>
          </div>
          <p v-else class="mt-3 text-sm text-slate-500">{{ t('No shipping address on file.') }}</p>
        </div>

        <div class="card p-5">
          <h2 class="text-sm font-semibold text-slate-900">{{ t('Billing') }}</h2>
          <div v-if="order.billingAddress" class="mt-3 text-sm text-slate-600">
            <p class="font-semibold text-slate-900">{{ order.billingAddress.name }}</p>
            <p>{{ order.billingAddress.line1 }} <span v-if="order.billingAddress.line2">, {{ order.billingAddress.line2 }}</span></p>
            <p>
              {{ order.billingAddress.city }}
              <span v-if="order.billingAddress.state">, {{ order.billingAddress.state }}</span>
              <span v-if="order.billingAddress.postal_code"> {{ order.billingAddress.postal_code }}</span>
            </p>
            <p>{{ order.billingAddress.country }}</p>
          </div>
          <p v-else class="mt-3 text-sm text-slate-500">{{ t('Billing matches shipping.') }}</p>
        </div>
      </section>

      <section class="card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-sm font-semibold text-slate-900">{{ t('Items') }}</h2>
          <Link href="/orders" class="text-sm font-semibold text-slate-600 hover:text-slate-900">{{ t('Back to orders') }}</Link>
        </div>

        <div class="mt-4 space-y-4">
          <div v-for="item in order.items" :key="item.id" class="rounded-xl border border-slate-100 p-4 text-sm">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <p class="font-semibold text-slate-900">{{ item.name }}</p>
                <p v-if="item.variant" class="text-slate-500">{{ t('Variant: :variant', { variant: item.variant }) }}</p>
                <p class="text-slate-500">{{ t('Qty: :quantity', { quantity: item.quantity }) }}</p>
              </div>
              <div class="text-right">
                <p class="font-semibold text-slate-900">{{ order.currency }} {{ formatMoney(item.total) }}</p>
                <p class="text-xs text-slate-500">{{ t('Status: :status', { status: item.fulfillment_status }) }}</p>
              </div>
            </div>

            <div v-if="item.review" class="mt-3 rounded-lg border border-slate-100 bg-white p-3">
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Your review') }}</p>
              <div class="mt-2 flex items-center gap-1 text-xs text-slate-600">
                <span v-for="n in 5" :key="n" class="text-slate-300" :class="n <= item.review.rating ? 'text-slate-900' : ''">
                  ★
                </span>
                <span class="ml-2">{{ item.review.rating }}/5</span>
              </div>
              <p v-if="item.review.title" class="mt-2 text-sm font-semibold text-slate-900">{{ item.review.title }}</p>
              <p class="mt-1 text-sm text-slate-600">{{ item.review.body }}</p>
              <p v-if="item.review.status === 'pending'" class="mt-2 text-xs text-slate-500">
                {{ t('Pending approval.') }}
              </p>
            </div>
            <div v-if="item.return_request" class="mt-3 rounded-lg border border-slate-100 bg-white p-3">
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Return request') }}</p>
              <p class="mt-2 text-xs text-slate-500">{{ t('Status: :status', { status: item.return_request.status }) }}</p>
              <p v-if="item.return_request.reason" class="mt-2 text-sm text-slate-600">
                {{ t('Reason: :reason', { reason: item.return_request.reason }) }}
              </p>
              <p v-if="item.return_request.notes" class="mt-1 text-sm text-slate-600">
                {{ t('Notes: :notes', { notes: item.return_request.notes }) }}
              </p>
              
              <!-- Return Label Actions -->
              <div v-if="item.return_request.status === 'approved' && item.return_request.return_label_url" class="mt-4 space-y-2">
                <p class="text-xs font-semibold text-slate-600">{{ t('Return shipping label available') }}</p>
                <div class="flex gap-2">
                  <a
                    :href="`/returns/${item.return_request.id}/label/preview`"
                    target="_blank"
                    class="btn-ghost text-xs"
                  >
                    {{ t('Preview Label') }}
                  </a>
                  <a
                    :href="`/returns/${item.return_request.id}/label/download`"
                    class="btn-secondary text-xs"
                  >
                    {{ t('Download Label') }}
                  </a>
                </div>
                <p class="text-xs text-slate-500">
                  {{ t('Print this label and attach it to your return package') }}
                </p>
              </div>
              <div v-else-if="item.return_request.status === 'approved'" class="mt-3">
                <p class="text-xs text-slate-500">
                  {{ t('Your return has been approved. A shipping label will be generated shortly.') }}
                </p>
              </div>
            </div>
            <div
              v-else-if="item.fulfillment_status === 'fulfilled'"
              class="mt-3 rounded-lg border border-slate-100 bg-white p-3"
            >
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Request a return') }}</p>
              <form class="mt-3 grid gap-3" @submit.prevent="submitReturn(item)">
                <div>
                  <label class="text-xs font-semibold text-slate-600">{{ t('Reason') }}</label>
                  <input v-model="returnForms[item.id].reason" type="text" class="input-base mt-1 w-full" :placeholder="t('Item arrived damaged')" />
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600">{{ t('Notes') }}</label>
                  <textarea v-model="returnForms[item.id].notes" rows="3" class="input-base mt-1 w-full" :placeholder="t('Share any useful details.')" />
                </div>
                <button type="submit" class="btn-secondary w-full sm:w-auto" :disabled="returnForms[item.id].processing">
                  {{ returnForms[item.id].processing ? t('Submitting...') : t('Submit return request') }}
                </button>
                <p v-if="returnNotice && returnNoticeItemId === item.id" class="text-xs text-emerald-600">
                  {{ returnNotice }}
                </p>
              </form>
            </div>
            <div
              v-else-if="item.fulfillment_status === 'fulfilled' && item.product_slug"
              class="mt-3 rounded-lg border border-slate-100 bg-white p-3"
            >
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Review this item') }}</p>
              <form class="mt-3 grid gap-3" @submit.prevent="submitReview(item)">
                <div>
                  <label class="text-xs font-semibold text-slate-600">{{ t('Rating') }}</label>
                  <select v-model="reviewForms[item.id].rating" class="input-base mt-1 w-full">
                    <option v-for="rating in [5,4,3,2,1]" :key="rating" :value="rating">
                      {{ t(':count stars', { count: rating }) }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600">{{ t('Title') }}</label>
                  <input v-model="reviewForms[item.id].title" type="text" class="input-base mt-1 w-full" :placeholder="t('Loved the quality')" />
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600">{{ t('Review') }}</label>
                  <textarea v-model="reviewForms[item.id].body" rows="3" class="input-base mt-1 w-full" :placeholder="t('Share how the item arrived and fit.')" />
                </div>
                <button type="submit" class="btn-primary w-full sm:w-auto" :disabled="reviewForms[item.id].processing">
                  {{ reviewForms[item.id].processing ? t('Submitting...') : t('Submit review') }}
                </button>
                <p v-if="reviewNotice && reviewNoticeItemId === item.id" class="text-xs text-emerald-600">
                  {{ reviewNotice }}
                </p>
              </form>
            </div>
            <div v-if="item.shipments.length" class="mt-3 space-y-2">
              <div v-for="shipment in item.shipments" :key="shipment.id" class="rounded-lg border border-slate-100 p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div class="text-xs text-slate-500">
                    {{ shipment.carrier || t('Carrier') }} · {{ shipment.tracking_number || t('Tracking pending') }}
                  </div>
                  <a v-if="shipment.tracking_url" :href="shipment.tracking_url" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
                    {{ t('Track') }}
                  </a>
                </div>
                <div v-if="shipment.events.length" class="mt-2 space-y-1 text-xs text-slate-500">
                  <div v-for="event in shipment.events" :key="event.id">
                    {{ formatDate(event.occurred_at) }} · {{ event.status_label || t('Update') }} · {{ event.location || t('Unknown') }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="card p-5">
        <h2 class="text-sm font-semibold text-slate-900">{{ t('Payments') }}</h2>
        <div v-if="order.payments.length" class="mt-4 space-y-3">
          <div v-for="payment in order.payments" :key="payment.id" class="rounded-xl border border-slate-100 p-4 text-sm">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <p class="font-semibold text-slate-900">{{ payment.provider }}</p>
                <p class="text-slate-500">{{ t('Status: :status', { status: payment.status }) }}</p>
                <p v-if="payment.provider_reference" class="text-slate-500">{{ t('Ref: :ref', { ref: payment.provider_reference }) }}</p>
              </div>
              <div class="text-right">
                <p class="font-semibold text-slate-900">{{ payment.currency }} {{ formatMoney(payment.amount) }}</p>
                <p class="text-xs text-slate-500">{{ t('Paid :date', { date: formatDate(payment.paid_at) }) }}</p>
              </div>
            </div>
          </div>
        </div>
        <p v-else class="mt-3 text-sm text-slate-500">{{ t('No payments recorded yet.') }}</p>
      </section>
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

const submitReturn = (item) => {
  const form = returnForms[item.id]
  if (! form) {
    return
  }
  form.post(route('returns.store'), {
    preserveScroll: true,
    onSuccess: () => {
      returnNotice.value = page.props.flash?.return_notice ?? t('Return request submitted.')
      returnNoticeItemId.value = item.id
    },
  })
}

const formatDate = (value) => {
  if (! value) {
    return '-'
  }
  return new Date(value).toLocaleDateString(locale.value || 'en')
}

const formatMoney = (value) => {
  const number = Number(value ?? 0)
  return number.toFixed(2)
}
</script>
