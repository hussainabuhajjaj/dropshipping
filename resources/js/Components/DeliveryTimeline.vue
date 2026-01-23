<template>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Delivery timeline') }}</p>
        <h3 class="text-lg font-semibold text-slate-900">{{ titleText }}</h3>
        <p v-if="subtitle" class="text-sm text-slate-500">{{ subtitle }}</p>
      </div>
    </div>
    <ol class="mt-5 space-y-4">
      <li v-for="(step, index) in resolvedSteps" :key="step.title" class="flex items-start gap-3">
        <div
          :class="[
            'flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-slate-900 text-xs font-semibold text-white',
            compact ? 'h-7 w-7 text-[11px]' : 'h-8 w-8',
          ]"
        >
          {{ index + 1 }}
        </div>
        <div>
          <p :class="['font-semibold text-slate-900', compact ? 'text-sm' : 'text-base']">{{ step.title }}</p>
          <p :class="['text-slate-500', compact ? 'text-xs' : 'text-sm']">{{ step.subtitle }}</p>
        </div>
      </li>
    </ol>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const props = defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  steps: { type: Array, default: null },
  compact: { type: Boolean, default: false },
})

const { t } = useTranslations()
const page = usePage()

const deliveryWindow = computed(() => page?.props?.site?.delivery_window ?? t('7 to 18 business days'))

const resolvedSteps = computed(() => {
  if (Array.isArray(props.steps) && props.steps.length) {
    return props.steps
  }

  return [
    {
      title: t('Order confirmed'),
      subtitle: t('We verify items, pricing, and address details.'),
    },
    {
      title: t('Dispatched in 24-48 hours'),
      subtitle: t('Suppliers prepare and hand off to carriers.'),
    },
    {
      title: t('In transit'),
      subtitle: t('Typical delivery: :window.', { window: deliveryWindow.value }),
    },
    {
      title: t('Delivered'),
      subtitle: t('Tracking updates until handoff or signature.'),
    },
  ]
})

const titleText = computed(() => props.title || t('Delivery built for Cote d\'Ivoire'))
</script>
