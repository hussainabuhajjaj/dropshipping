<template>
  <StorefrontLayout>
    <div class="space-y-8">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Account</p>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Orders</h1>
          <p class="text-sm text-slate-500">Track your orders by status like Temu.</p>
        </div>
        <Link href="/account" class="btn-ghost text-sm">Back to profile</Link>
      </div>

      <div class="flex flex-wrap gap-2">
        <button v-for="tab in tabs" :key="tab.key" class="pill" :class="{ 'pill-active': currentTab === tab.key }" @click="currentTab = tab.key">
          {{ tab.label }} <span class="ml-1 rounded-full bg-white/70 px-2 text-xs text-slate-700">{{ counts[tab.key] || 0 }}</span>
        </button>
      </div>

      <div class="card space-y-4 p-6">
        <div v-if="filtered.length" class="space-y-3">
          <div v-for="order in filtered" :key="order.id" class="rounded-xl border border-slate-100 p-4 text-sm">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div class="space-y-1">
                <p class="font-semibold text-slate-900">#{{ order.number }}</p>
                <p class="text-slate-500">{{ formatDate(order.placed_at) }} • {{ displayOrderPrice(order.grand_total, order.currency) }}</p>
                <p class="text-slate-500">Status: <span class="font-semibold text-slate-900">{{ order.status }}</span></p>
                <p class="text-slate-500">Payment: {{ order.payment_status }}</p>
              </div>
              <div class="flex flex-col gap-2">
                <Link :href="`/orders/${order.id}`" class="btn-secondary px-3 py-2 text-xs">View</Link>
                <Link :href="`/orders/track?number=${order.number}`" class="btn-ghost text-xs text-blue-700">Track</Link>
              </div>
            </div>
          </div>
        </div>
        <EmptyState
          v-else
          variant="compact"
          eyebrow="Orders"
          title="Nothing in this status"
          message="When orders move to this stage, they will show up here."
        >
          <template #actions>
            <Link href="/products" class="btn-secondary text-xs">Browse products</Link>
            <Link href="/orders/track" class="btn-ghost text-xs">Track order</Link>
          </template>
        </EmptyState>
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { convertCurrency, formatCurrency } from '@/utils/currency.js'

// Helper to display order price in selected currency (default to order's currency if not available)
function displayOrderPrice(amount, orderCurrency) {
  // If you want to always show in user's selected currency, replace 'orderCurrency' with a prop or global currency
  return formatCurrency(convertCurrency(Number(amount), 'USD', orderCurrency), orderCurrency)
}
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'

const props = defineProps({
  orders: { type: Array, default: () => [] },
})

const tabs = [
  { key: 'all', label: 'All' },
  { key: 'pending', label: 'Pending' },
  { key: 'paid', label: 'Paid' },
  { key: 'fulfilling', label: 'Fulfilling' },
  { key: 'fulfilled', label: 'Delivered' },
  { key: 'cancelled', label: 'Cancelled' },
  { key: 'refunded', label: 'Refunded' },
]

const currentTab = ref('all')

const counts = computed(() => {
  const map = {}
  props.orders.forEach((o) => {
    map[o.status] = (map[o.status] || 0) + 1
  })
  map.all = props.orders.length
  return map
})

const filtered = computed(() => {
  if (currentTab.value === 'all') {
    return props.orders
  }
  return props.orders.filter((o) => o.status === currentTab.value)
})

const formatDate = (value) => {
  if (! value) return '-'
  return new Date(value).toLocaleDateString()
}
</script>

<style scoped>
.pill {
  @apply rounded-full border border-slate-200 bg-white px-3 py-1 text-sm text-slate-700 transition;
}
.pill-active {
  @apply border-blue-600 bg-blue-50 text-blue-700;
}
</style>
