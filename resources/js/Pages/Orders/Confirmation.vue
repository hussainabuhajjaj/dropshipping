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
        <span v-if="order.payment_status === 'paid'">
          {{ t("Your order :number is confirmed. We will share tracking once the supplier dispatches. Delivery to Cote d'Ivoire with transparent customs.", { number: `#${order.number}` }) }}
        </span>
        <span v-else>
          {{ t('We have received your order :number. Payment is pending, and we will confirm once it clears.', { number: `#${order.number}` }) }}
        </span>
      </p>

      <div class="card-muted p-5 text-left">
        <div class="flex items-center justify-between text-sm">
          <span>{{ t('Status') }}</span>
          <span class="font-semibold text-slate-900">{{ order.status }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between text-sm">
          <span>{{ t('Payment') }}</span>
          <span class="font-semibold text-slate-900">{{ order.payment_status }}</span>
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

      <div class="flex flex-col justify-center gap-3 sm:flex-row">
        <Link
          :href="`/orders/track?number=${order.number}&email=${order.email}`"
          class="btn-primary w-full sm:w-auto"
        >
          {{ t('Track order') }}
        </Link>
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
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { useTranslations } from '@/i18n'

defineProps({
  order: { type: Object, required: true },
})

const page = usePage()
const { t } = useTranslations()
const supportWhatsApp = page.props.site?.support_whatsapp ?? '+225 00 00 00 00'
</script>
