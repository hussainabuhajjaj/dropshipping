<template>
  <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
    <div v-for="badge in badges" :key="badge.label" class="flex items-start gap-3 text-sm text-slate-600">
      <span v-html="badge.icon" class="mt-0.5 shrink-0 text-emerald-600" />
      <div>
        <p class="font-medium text-slate-900">{{ badge.label }}</p>
        <p class="text-xs text-slate-500">{{ badge.description }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTranslations } from '@/i18n'

const { t } = useTranslations()

const badges = [
  {
    icon: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0 1 12 2.944a11.955 11.955 0 0 1-8.618 3.04A12.02 12.02 0 0 0 3 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
    label: t('Secure payment'),
    description: t('Your payment is encrypted and processed securely via Paystack.'),
  },
  {
    icon: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-4-4 4m0 0-4-4m4 4V4"/></svg>',
    label: t('Easy returns'),
    description: t('Not satisfied? Contact us within 14 days for a return or exchange.'),
  },
  {
    icon: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 0 1 2 2v1a2 2 0 0 0 2 2 2 2 0 0 1 2 2v2.945M8 3.935V5.5A2.5 2.5 0 0 0 10.5 8h.5a2 2 0 0 1 2 2 2 2 0 0 0 2 2h1.064M15 20.488V18a2 2 0 0 1 2-2h3.064M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>',
    label: t('Worldwide shipping'),
    description: t('We ship to multiple countries with tracking and delivery estimates provided.'),
  },
  {
    icon: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
    label: t('Secure packaging'),
    description: t('Products are carefully packaged to ensure they arrive in perfect condition.'),
  },
]
</script>
