<template>
  <div v-if="threshold > 0 && remaining > 0" class="rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3">
    <div class="flex items-center justify-between text-sm">
      <span class="text-emerald-800">
        <svg viewBox="0 0 24 24" class="-mt-0.5 mr-1 inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0-4-4m4 4-4 4m0 6H4m0 0 4 4m-4-4 4-4" />
        </svg>
        {{ t('Add') }}
        <span class="font-semibold">{{ formatRemaining }}</span>
        {{ t('more for free shipping') }}
      </span>
    </div>
    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-emerald-200">
      <div
        class="h-full rounded-full bg-emerald-500 transition-all duration-500"
        :style="{ width: `${progressPercent}%` }"
      />
    </div>
  </div>
  <div v-else-if="threshold > 0 && remaining <= 0" class="rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3">
    <p class="text-sm font-semibold text-emerald-800">
      <svg viewBox="0 0 24 24" class="-mt-0.5 mr-1 inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
      </svg>
      {{ t('You qualify for free shipping!') }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  threshold: { type: Number, default: 0 },
  subtotal: { type: Number, default: 0 },
  currency: { type: String, default: 'XOF' },
})

const { t } = useTranslations()

const remaining = computed(() => Math.max(0, props.threshold - props.subtotal))

const progressPercent = computed(() => {
  if (props.threshold <= 0) return 100
  return Math.min(100, (props.subtotal / props.threshold) * 100)
})

const formatRemaining = computed(() => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: props.currency,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(remaining.value)
})
</script>
