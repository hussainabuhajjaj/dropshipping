<template>
  <StorefrontLayout>
    <div class="space-y-8">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Account</p>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Refunds</h1>
          <p class="text-sm text-slate-500">See your refunds and references.</p>
        </div>
        <Link href="/account" class="btn-ghost text-sm">Back to profile</Link>
      </div>

      <section class="card space-y-4 p-6">
        <div v-if="refunds.length" class="space-y-3">
          <div v-for="refund in refunds" :key="refund.id" class="rounded-xl border border-slate-100 p-4 text-sm">
            <p class="font-semibold text-slate-900">Order #{{ refund.order_number }}</p>
            <p class="text-slate-500">
              {{ displayRefundAmount(refund.amount, refund.currency) }} refunded
            </p>
            <p class="text-slate-500">Reference: {{ refund.provider_reference || 'N/A' }}</p>
            <p class="text-slate-400 text-xs">Updated {{ formatDate(refund.updated_at) }}</p>
          </div>
        </div>
        <EmptyState
          v-else
          variant="compact"
          eyebrow="Refunds"
          title="No refunds yet"
          message="If a refund is issued, it will appear here with its reference."
        />
      </section>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { convertCurrency, formatCurrency } from '@/utils/currency.js'

function displayRefundAmount(amount, currency) {
  return formatCurrency(convertCurrency(Number(amount ?? 0), 'USD', currency), currency)
}
import { Link } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'

const props = defineProps({
  refunds: { type: Array, default: () => [] },
})

const formatDate = (value) => {
  if (! value) return '-'
  return new Date(value).toLocaleDateString()
}
</script>
