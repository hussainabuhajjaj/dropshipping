<template>
  <div class="sticky top-20 z-20 rounded-lg border border-[#e7ded1] bg-white/95 p-3 shadow-[0_14px_34px_rgba(15,23,42,0.06)] backdrop-blur">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <span class="rounded-full bg-[#fff4e5] px-3 py-2 text-[0.68rem] font-bold uppercase tracking-[0.14em] text-[#d97706]">
          {{ totalLabel }}
        </span>
        <span
          v-if="activeFilterCount"
          class="rounded-full bg-white px-3 py-2 text-[0.68rem] font-bold uppercase tracking-[0.14em] text-slate-500 ring-1 ring-[#e7ded1]"
        >
          {{ t(':count filters active', { count: activeFilterCount }) }}
        </span>
        <button
          v-if="showFilterButton"
          type="button"
          class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#e7ded1] bg-[#fffaf4] px-4 text-[0.72rem] font-bold uppercase tracking-[0.12em] text-slate-700 lg:hidden"
          @click="$emit('open-filters')"
        >
          {{ filterButtonLabel }}
        </button>
      </div>

      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <input
          :value="search"
          type="search"
          :placeholder="searchPlaceholder"
          class="input-base min-w-0 sm:w-56"
          @input="$emit('update:search', $event.target.value)"
          @keydown.enter.prevent="$emit('submit-search')"
        />
        <div class="flex items-center gap-2">
          <label class="text-xs font-semibold text-slate-600">{{ t('Sort by') }}</label>
          <select
            :value="sort"
            class="input-base min-w-0"
            @change="$emit('update:sort', $event.target.value)"
          >
            <option
              v-for="option in sortOptions"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  totalCount: { type: Number, default: 0 },
  totalLabel: { type: String, default: '' },
  activeFilterCount: { type: Number, default: 0 },
  search: { type: String, default: '' },
  searchPlaceholder: { type: String, default: '' },
  sort: { type: String, default: '' },
  sortOptions: { type: Array, default: () => [] },
  filterButtonLabel: { type: String, default: '' },
  showFilterButton: { type: Boolean, default: true },
})

defineEmits(['update:search', 'update:sort', 'open-filters', 'submit-search'])

const { t } = useTranslations()

const totalLabel = computed(() => props.totalLabel || t(':count items', { count: props.totalCount }))
</script>
