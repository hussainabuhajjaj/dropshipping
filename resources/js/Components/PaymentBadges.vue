<script setup>
defineProps({
  label: { type: String, default: '' },
  showStripe: { type: Boolean, default: false },
  showMobileMoney: { type: Boolean, default: true },
  showCard: { type: Boolean, default: true },
  showPaystack: { type: Boolean, default: true },
})

const badge = {
  label: 'Cards, Mobile Money and bank payments secured by Paystack',
  src: '/images/payments/paystack-badge-cards-xof.svg',
}
</script>

<template>
  <div class="inline-flex max-w-full flex-wrap items-center gap-3">
    <span v-if="label" class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">
      {{ label }}
    </span>

    <div class="flex max-w-full items-center">
      <img
        :src="badge.src"
        :alt="badge.label"
        class="h-auto max-h-12 w-auto max-w-[min(100%,18rem)] object-contain"
        loading="lazy"
      />
    </div>
  </div>
</template>
