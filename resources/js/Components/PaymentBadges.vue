<template>
  <div class="inline-flex flex-wrap items-center gap-3">
    <span v-if="label" class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">
      {{ label }}
    </span>
    <div class="flex flex-wrap items-center gap-3">
      <div
        v-for="badge in badges"
        :key="badge.key"
        class="flex h-10 w-16 items-center justify-center rounded-xl border border-slate-200 bg-white px-2 shadow-sm"
      >
        <img
          v-if="badge.src"
          :src="badge.src"
          :alt="badge.label"
          class="h-7 w-auto object-contain"
          loading="lazy"
        />
        <span v-else class="text-[11px] font-semibold text-slate-600">{{ badge.label }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  label: { type: String, default: '' },
  showStripe: { type: Boolean, default: false },
  showMobileMoney: { type: Boolean, default: true },
  showCard: { type: Boolean, default: true },
})

const { t } = useTranslations()

const badges = computed(() => {
  const items = []

  if (props.showCard) {
    items.push({ key: 'visa', label: 'Visa', src: '/images/payments/visa.svg' })
    items.push({ key: 'mastercard', label: 'Mastercard', src: '/images/payments/mastercard.svg' })
  }

  if (props.showStripe) {
    items.push({ key: 'stripe', label: 'Stripe', src: '/images/payments/stripe.svg' })
  }

  if (props.showMobileMoney) {
    items.push({ key: 'orange-money', label: 'Orange Money', src: '/images/payments/orange-money.svg' })
    items.push({ key: 'wave', label: 'Wave', src: '/images/payments/wave.svg' })
  }

  return items
})
</script>
