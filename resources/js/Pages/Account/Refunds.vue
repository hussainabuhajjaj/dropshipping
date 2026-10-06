<template>
  <StorefrontLayout>
    <div class="space-y-5 pb-10 sm:space-y-8">
      <section class="rounded-[1.8rem] bg-[#111111] px-5 py-5 text-white shadow-[0_20px_48px_rgba(15,23,42,0.16)]">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#facc15]">Account</p>
            <h1 class="mt-2 text-[1.95rem] font-black tracking-[-0.04em] sm:text-[2.2rem]">Refunds</h1>
            <p class="mt-2 max-w-xl text-sm leading-6 text-white/72">If money moves back to the shopper, it should be obvious, traceable, and low-stress.</p>
          </div>
          <Link href="/account" class="inline-flex min-h-11 items-center justify-center rounded-full border border-white/15 bg-white/8 px-4 text-sm font-semibold text-white transition hover:bg-white/12">Back to profile</Link>
        </div>
      </section>

      <div class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <div class="shrink-0 rounded-full bg-[#fff4e8] px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-[#c55b24]">{{ refunds.length }} refund records</div>
        <div class="shrink-0 rounded-full bg-white px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-slate-500 ring-1 ring-[#eadfce]">Provider references</div>
        <div class="shrink-0 rounded-full bg-white px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-slate-500 ring-1 ring-[#eadfce]">Latest updates first</div>
      </div>

      <section class="rounded-[1.8rem] border border-[#eadfce] bg-[#fffaf4] p-5 shadow-[0_16px_38px_rgba(15,23,42,0.05)] sm:p-6">
        <div v-if="refunds.length" class="space-y-3">
          <div v-for="refund in refunds" :key="refund.id" class="rounded-[1.4rem] border border-[#eadfce] bg-white p-4 text-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="space-y-1">
                <p class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-slate-400">Order #{{ refund.order_number }}</p>
                <p class="text-base font-black tracking-[-0.02em] text-slate-900">{{ displayRefundAmount(refund.amount, refund.currency) }} refunded</p>
                <p class="text-slate-500">Reference: {{ refund.provider_reference || 'N/A' }}</p>
              </div>
              <span class="rounded-full bg-emerald-50 px-3 py-1 text-[0.64rem] font-bold uppercase tracking-[0.16em] text-emerald-700">Refund logged</span>
            </div>
            <p class="mt-2 text-slate-400 text-xs">Updated {{ formatDate(refund.updated_at) }}</p>
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
import { useUserPreferences } from '@/composables/useUserPreferences.js'

const { formatCurrency, convertCurrency } = useUserPreferences()
function displayRefundAmount(amount, currency) {
  return formatCurrency(convertCurrency(Number(amount ?? 0), currency, 'XOF'), 'XOF')
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
