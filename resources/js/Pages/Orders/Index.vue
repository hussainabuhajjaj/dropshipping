<template>
  <StorefrontLayout>
    <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ t('My orders') }}</h1>
        <Link href="/products" class="btn-ghost">{{ t('Continue shopping') }}</Link>
      </div>

      <div v-if="orders.length" class="space-y-3">
        <div
          v-for="order in orders"
          :key="order.id"
          class="card flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="space-y-1">
            <p class="text-sm font-semibold text-slate-900">#{{ order.number }}</p>
            <p class="text-xs text-slate-500">
              {{ formatDate(order.placed_at) }} - {{ order.currency }} {{ Number(order.grand_total).toFixed(2) }}
            </p>
            <p class="text-xs text-slate-500">
              {{ t('Status: :status - Payment: :payment', { status: order.status, payment: order.payment_status }) }}
            </p>
          </div>
          <Link
            :href="`/orders/${order.id}`"
            class="btn-secondary px-4 py-2 text-xs"
          >
            {{ t('View order') }}
          </Link>
        </div>
        <div class="flex items-center justify-between border-t border-slate-100 pt-4 text-xs text-slate-500">
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="btn-ghost px-3 py-2 text-xs"
              :disabled="ordersPager.current_page <= 1"
              @click="goToPage((ordersPager.current_page ?? 1) - 1)"
            >
              {{ t('Previous slide') }}
            </button>
            <button
              type="button"
              class="btn-ghost px-3 py-2 text-xs"
              :disabled="! hasMore"
              @click="goToPage((ordersPager.current_page ?? 1) + 1)"
            >
              {{ t('Next slide') }}
            </button>
          </div>
          <span>
            {{ t('Page') }} {{ ordersPager.current_page ?? 1 }} / {{ ordersPager.last_page ?? 1 }}
          </span>
        </div>
      </div>

      <EmptyState
        v-else
        :eyebrow="t('Orders')"
        :title="t('No orders yet')"
        :message="t('Your first Simbazu order will appear here with status updates and tracking.')"
      >
        <template #actions>
          <Link href="/products" class="btn-primary">{{ t('Start shopping') }}</Link>
          <Link href="/orders/track" class="btn-ghost">{{ t('Track an order') }}</Link>
        </template>
      </EmptyState>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { useTranslations } from '@/i18n'

const { t, locale } = useTranslations()

const props = defineProps({
  orders: { type: Object, default: () => ({ data: [] }) },
})

const ordersPager = computed(() => props.orders ?? { data: [] })
const orders = computed(() => ordersPager.value.data ?? [])
const hasMore = computed(() => (ordersPager.value.current_page ?? 1) < (ordersPager.value.last_page ?? 1))

const goToPage = (page) => {
  if (page < 1 || page > (ordersPager.value.last_page ?? 1)) {
    return
  }
  router.get('/orders', { page }, { preserveState: true })
}

const formatDate = (value) => {
  if (! value) {
    return '-'
  }
  return new Date(value).toLocaleDateString(locale.value || 'en')
}
</script>
