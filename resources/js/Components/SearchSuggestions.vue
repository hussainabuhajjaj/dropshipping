<template>
  <div
    class="absolute left-0 right-0 top-full mt-2 overflow-hidden rounded-[1.35rem] border border-[#eadfce] bg-[#fefcf7] shadow-[0_20px_48px_rgba(15,23,42,0.12)]"
  >
    <!-- Loading -->
    <div v-if="isFetching" class="space-y-3 p-4">
      <div class="flex items-center gap-3">
        <div class="h-8 w-8 animate-pulse rounded-lg bg-[#eadfce]/60"/>
        <div class="flex-1 space-y-1.5">
          <div class="h-3 w-3/4 animate-pulse rounded bg-[#eadfce]/60"/>
          <div class="h-2 w-1/2 animate-pulse rounded bg-[#eadfce]/40"/>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <div class="h-8 w-8 animate-pulse rounded-lg bg-[#eadfce]/60"/>
        <div class="flex-1 space-y-1.5">
          <div class="h-3 w-2/3 animate-pulse rounded bg-[#eadfce]/60"/>
          <div class="h-2 w-1/3 animate-pulse rounded bg-[#eadfce]/40"/>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div v-else class="py-2">
      <!-- Recent header -->
      <div v-if="isShowingRecent" class="flex items-center justify-between px-4 py-1.5">
        <span class="text-[0.6rem] font-bold uppercase tracking-[0.18em] text-[#c55b24]">{{ t('Recent searches') }}</span>
        <button
          type="button"
          class="rounded-full px-2.5 py-1 text-[0.6rem] font-bold uppercase tracking-[0.12em] text-slate-400 transition hover:bg-[#eadfce]/50 hover:text-slate-600"
          @click="$emit('clear-recent')"
        >
          {{ t('Clear') }}
        </button>
      </div>

      <!-- Items -->
      <button
        v-for="(item, index) in items"
        :key="`${item.type}-${item.id || item.href}-${index}`"
        type="button"
        class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors duration-100"
        :class="selectedIndex === index
          ? 'bg-[#fff4e8]'
          : 'hover:bg-[#f7f3eb]/70'"
        @mousedown.prevent
        @click="$emit('select', item)"
        @mouseenter="$emit('hover', index)"
      >
        <!-- Image / icon -->
        <img
          v-if="item.image"
          :src="item.image"
          :alt="item.label"
          class="h-9 w-9 shrink-0 rounded-lg border border-[#eadfce] object-cover"
        />
        <span
          v-else
          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#eadfce] bg-[#fffaf4] text-xs font-bold text-[#c55b24]"
        >
          {{ item.type === 'category' ? 'C' : item.type === 'view_all' ? '↵' : 'P' }}
        </span>

        <!-- Label + meta -->
        <span class="min-w-0 flex-1">
          <span class="block truncate text-sm font-semibold text-slate-800">{{ item.label }}</span>
          <span v-if="item.meta" class="block truncate text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-slate-400">{{ item.meta }}</span>
        </span>
      </button>

      <!-- No results -->
      <div v-if="showNoResults" class="px-4 py-5 text-center">
        <p class="text-sm text-slate-500">{{ t('No quick matches') }}</p>
        <p class="mt-0.5 text-xs text-slate-400">{{ t('Press Enter to see full results.') }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTranslations } from '@/i18n'

defineProps({
  items: { type: Array, default: () => [] },
  isFetching: { type: Boolean, default: false },
  showNoResults: { type: Boolean, default: false },
  isShowingRecent: { type: Boolean, default: false },
  selectedIndex: { type: Number, default: -1 },
})

defineEmits(['select', 'hover', 'clear-recent'])

const { t } = useTranslations()
</script>
