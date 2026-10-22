<script setup>
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  href: { type: String, default: '' },
  actionLabel: { type: String, default: '' },
  icon: { type: String, default: 'spark' },
})

const { t } = useTranslations()
</script>

<template>
  <div class="mb-3 flex items-end justify-between gap-4">
    <div class="min-w-0">
      <p v-if="eyebrow" class="mb-1 text-[0.62rem] font-black uppercase tracking-[0.22em] text-[#d97706]">
        {{ eyebrow }}
      </p>
      <div class="flex items-center gap-2">
        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-[#d97706] shadow-sm ring-1 ring-[#eee6da]">
          <svg v-if="icon === 'bolt'" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
            <path d="M13 2L3 14h8l-1 8 11-13h-8l0-7z" />
          </svg>
          <svg v-else-if="icon === 'value'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m4.5-8.25c0-1.24-1.6-2.25-3.57-2.25S9.36 8.51 9.36 9.75 10.96 12 12.93 12s3.57 1.01 3.57 2.25-1.6 2.25-3.57 2.25-3.57-1.01-3.57-2.25" />
          </svg>
          <svg v-else-if="icon === 'new'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M3 12h18M5.64 5.64l12.72 12.72M18.36 5.64 5.64 18.36" />
          </svg>
          <svg v-else-if="icon === 'star'" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 3.5l2.6 5.4 6 .9-4.3 4.1 1 5.8L12 16.9 6.7 19.7l1-5.8-4.3-4.1 6-.9L12 3.5z" />
          </svg>
          <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
          </svg>
        </span>
        <div class="min-w-0">
          <h2 class="truncate text-base font-black text-slate-950 sm:text-lg">{{ title }}</h2>
          <p v-if="subtitle" class="mt-0.5 line-clamp-1 text-xs font-medium text-slate-500">{{ subtitle }}</p>
        </div>
      </div>
    </div>

    <Link
      v-if="href"
      :href="href"
      class="shrink-0 rounded-full px-3 py-1.5 text-xs font-bold text-[#d97706] transition hover:bg-[#fff4e5] hover:text-slate-950"
    >
      {{ actionLabel || t('View all') }}
    </Link>
  </div>
</template>
