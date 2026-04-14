<template>
  <div :class="layoutClass">
    <div
      v-for="item in resolvedItems"
      :key="item.title"
      :class="[
        'rounded-lg border border-slate-200 bg-white',
        compact ? 'flex items-center gap-2 p-2' : 'flex items-center gap-2 p-2',
        tone === 'muted' ? 'bg-slate-50/80' : '',
      ]"
    >
      <div :class="['flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-700', compact ? 'h-6 w-6' : 'h-7 w-7']">
        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon] || icons.shield" />
        </svg>
      </div>
      <div>
        <p :class="['font-medium text-slate-900', compact ? 'text-xs' : 'text-xs']">{{ item.title }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  items: { type: Array, default: null },
  compact: { type: Boolean, default: false },
  columns: { type: Number, default: 0 },
  tone: { type: String, default: 'default' },
})

const { t } = useTranslations()

const icons = {
  shield: 'M12 3l7 4v5c0 5-3.5 9-7 10-3.5-1-7-5-7-10V7l7-4z',
  truck: 'M3 7h10v6h3l3 3v4h-2a2 2 0 01-4 0H9a2 2 0 01-4 0H3v-5h2V7z',
  badge: 'M12 3l3 6 6 1-4 4 1 6-6-3-6 3 1-6-4-4 6-1 3-6z',
}

const resolvedItems = computed(() => {
  if (Array.isArray(props.items) && props.items.length) {
    return props.items
  }
  return [
    {
      icon: 'shield',
      title: t('Secure checkout'),
      subtitle: t('Encrypted payments and fraud monitoring'),
    },
    {
      icon: 'badge',
      title: t('Verified suppliers'),
      subtitle: t('We vet sourcing and availability before dispatch'),
    },
    {
      icon: 'truck',
      title: t('Tracked delivery'),
      subtitle: t('Clear ETAs with updates to Cote d\'Ivoire'),
    },
  ]
})

const layoutClass = computed(() => {
  if (props.columns === 3) return 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3'
  if (props.columns === 2 || props.compact) return 'grid gap-4 sm:grid-cols-2 lg:grid-cols-2'
  return 'grid gap-4'
})
</script>
