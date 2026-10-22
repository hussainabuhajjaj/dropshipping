<template>
  <div class="border-t border-slate-200 bg-white mt-6">
    <div class="mx-auto flex max-w-5xl flex-col gap-2 px-4 py-3">
      <div class="flex items-center justify-center gap-3 text-sm font-semibold text-slate-900">
        <button
          type="button"
          class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-700 disabled:opacity-40"
          :disabled="currentPage <= 1 || loading"
          @click="$emit('prev')"
        >
          ‹
        </button>
        <span>{{ currentPage }} / {{ lastPage }}</span>
        <button
          type="button"
          class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-700 disabled:opacity-40"
          :disabled="!canNext || loading"
          @click="$emit('next')"
        >
          ›
        </button>
      </div>
      <div class="flex items-center gap-2">
        <button
          v-if="showSort"
          type="button"
          class="flex flex-1 items-center justify-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900"
          @click="$emit('sort')"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M6 8h12M9 12h6m-4 4h2M10 20h4" />
          </svg>
          {{ sortLabel }}
        </button>
        <button
          v-if="showFilter"
          type="button"
          class="flex flex-1 items-center justify-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900"
          @click="$emit('filter')"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M7 12h10M10 19h4" />
          </svg>
          {{ filterLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  currentPage: { type: Number, required: true },
  lastPage: { type: Number, required: true },
  canNext: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  showSort: { type: Boolean, default: true },
  showFilter: { type: Boolean, default: true },
  sortLabel: { type: String, default: 'Sort By' },
  filterLabel: { type: String, default: 'Filter' },
})
</script>
