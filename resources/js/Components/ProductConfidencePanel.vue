<template>
  <section class="rounded-[1.25rem] border border-[#eadfce] bg-[#fffaf4] p-4">
    <div class="grid gap-3 sm:grid-cols-3">
      <div v-for="item in confidenceItems" :key="item.title" class="flex gap-3">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-slate-800 ring-1 ring-[#eadfce]">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
          </svg>
        </span>
        <span>
          <span class="block text-sm font-black text-slate-950">{{ item.title }}</span>
          <span class="mt-0.5 block text-xs leading-5 text-slate-500">{{ item.subtitle }}</span>
        </span>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  leadTimeDays: { type: [Number, String], default: 7 },
})

const { t } = useTranslations()

const confidenceItems = computed(() => [
  {
    title: t('Secure payment'),
    subtitle: t('Encrypted checkout with supported card, mobile money, and bank options.'),
    icon: 'M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6l7-3z',
  },
  {
    title: t('Tracked delivery'),
    subtitle: t('Estimated dispatch: :days days, then carrier tracking follows.', { days: props.leadTimeDays }),
    icon: 'M3 7h10v7h3l3 3v3h-2a2 2 0 01-4 0H9a2 2 0 01-4 0H3V7z',
  },
  {
    title: t('Clear total'),
    subtitle: t('Shipping and customs notes stay visible before payment.'),
    icon: 'M9 11h6M9 15h6M7 3h10a2 2 0 012 2v14l-3-2-3 2-3-2-3 2V5a2 2 0 012-2z',
  },
])
</script>
