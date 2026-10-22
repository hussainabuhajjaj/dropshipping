<template>
  <span class="flex items-center gap-1.5">
    <span
      v-if="promotion"
      class="inline-flex items-center gap-1 rounded bg-red-500 px-2 py-0.5 text-[0.65rem] font-bold text-white"
    >
      {{ promotion.badge_text || promotion.name }}
      <span v-if="promotion.value_type === 'percentage'">-{{ promotion.value }}%</span>
      <span v-else-if="promotion.value_type === 'fixed'">-{{ displayValue(promotion.value) }}</span>
    </span>
    <span v-if="countdown" class="text-[0.65rem] font-semibold text-slate-500">
      {{ t('Ends in') }} {{ countdown }}
    </span>
  </span>
</template>

<script setup>
import { useTranslations } from '@/i18n'

defineProps({
  promotion: { type: Object, default: null },
  countdown: { type: String, default: '' },
  displayValue: { type: Function, default: (v) => v },
})

const { t } = useTranslations()
</script>
