<template>
  <StorefrontLayout>
    <div class="mx-auto max-w-3xl space-y-6">
      <div class="space-y-3 text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Track') }}</p>
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">{{ t('Order tracking') }}</h1>
        <p class="text-sm text-slate-600">
          {{ t('Enter your order number and email to see delivery status. Tracking updates appear once the supplier ships.') }}
        </p>
      </div>

      <form class="card space-y-4 p-5" @submit.prevent="submit">
        <input v-model="form.number" required :placeholder="t('Order number')" class="input-base" />
        <input v-model="form.email" required type="email" :placeholder="t('Email')" class="input-base" />
        <button type="submit" class="btn-primary w-full">
          {{ t('Track order') }}
        </button>
      </form>

      <p v-if="error" class="card border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ error }}
      </p>

      <div v-if="tracking" class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="card-muted p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Order status') }}</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">{{ tracking.status }}</p>
            <p class="text-xs text-slate-500">{{ t('Payment: :status', { status: tracking.payment_status }) }}</p>
          </div>
          <div class="card-muted p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Tracking numbers') }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
              <span
                v-for="shipment in tracking.shipments ?? []"
                :key="shipment.tracking_number ?? shipment.order_item_id"
                class="chip"
              >
                {{ shipment.tracking_number ?? t('Pending') }}
              </span>
              <span v-if="!tracking.shipments?.length" class="text-xs text-slate-500">{{ t('Pending') }}</span>
            </div>
          </div>
        </div>

        <div v-if="tracking.shipments?.length" class="space-y-4">
          <div
            v-for="shipment in tracking.shipments"
            :key="shipment.tracking_number ?? shipment.order_item_id"
            class="card p-5"
          >
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
              <div class="font-semibold text-slate-900">
                {{ t('Tracking :number', { number: shipment.tracking_number ?? t('Pending') }) }}
              </div>
              <a
                v-if="shipment.tracking_url"
                :href="shipment.tracking_url"
                target="_blank"
                rel="noreferrer"
                class="text-xs font-semibold text-slate-600 hover:text-slate-900"
              >
                {{ t('Open carrier') }}
              </a>
            </div>
            <div class="mt-2 text-xs text-slate-500">
              {{ t('Carrier: :carrier - Shipped: :shipped - Delivered: :delivered', {
                carrier: shipment.carrier ?? t('TBD'),
                shipped: formatDate(shipment.shipped_at),
                delivered: formatDate(shipment.delivered_at),
              }) }}
            </div>
            <div v-if="shipment.events?.length" class="mt-4 border-l border-slate-200 pl-4 text-sm text-slate-600">
              <div v-for="(event, idx) in shipment.events" :key="idx" class="mb-3">
                <p class="font-semibold text-slate-900">
                  {{ event.status_label ?? event.status_code }}
                </p>
                <p class="text-xs text-slate-500">
                  {{ formatDate(event.occurred_at) }}
                  <span v-if="event.location"> - {{ event.location }}</span>
                </p>
              </div>
            </div>
            <p v-else class="mt-4 text-sm text-slate-500">
              {{ t('Tracking updates will appear once the supplier ships your order.') }}
            </p>
          </div>
        </div>

        <p v-else class="card p-5 text-sm text-slate-500">
          {{ t('No tracking numbers yet. We will send an update once the supplier ships.') }}
        </p>

        <p class="text-xs text-slate-500">
          {{ t('Your order may arrive in multiple packages and tracking numbers may update separately.') }}
        </p>
        <p class="text-xs text-slate-500">
          {{ t('Customs: if additional duties are needed, we will notify you before delivery. Standard delivery timelines apply after clearance.') }}
        </p>
        <p class="text-xs text-slate-500">
          {{ t('Need help?') }}
          <Link href="/support" class="font-semibold text-slate-700 hover:text-slate-900">{{ t('Contact support') }}</Link>.
        </p>
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { reactive, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  tracking: { type: Object, default: null },
  lookup: { type: Object, default: () => ({}) },
  error: { type: String, default: null },
})

const { t, locale } = useTranslations()

const form = reactive({
  number: props.lookup?.number ?? '',
  email: props.lookup?.email ?? '',
})

watch(
  () => props.lookup,
  (value) => {
    if (value) {
      form.number = value.number ?? ''
      form.email = value.email ?? ''
    }
  },
  { immediate: true }
)

const submit = () => {
  router.get(
    '/orders/track',
    { number: form.number, email: form.email },
    { preserveScroll: true, preserveState: true, replace: true }
  )
}

const formatDate = (value) => {
  if (! value) {
    return '-'
  }
  return new Date(value).toLocaleString(locale.value || 'en')
}
</script>
