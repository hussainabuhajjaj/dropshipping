<template>
  <div class="fixed inset-x-0 bottom-0 z-[120] border-t border-slate-200 bg-white/95 backdrop-blur lg:hidden">
    <div class="container-base pb-[max(0.875rem,env(safe-area-inset-bottom))] pt-3">
      <div class="rounded-[1.5rem] border border-slate-200 bg-white px-4 py-3 shadow-[0_-14px_40px_rgba(15,23,42,0.12)]">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-slate-900">{{ title }}</p>
            <div class="mt-1 flex flex-wrap items-center gap-2">
              <span class="text-lg font-bold text-slate-900">{{ price }}</span>
              <span v-if="compareAt" class="text-xs text-slate-400 line-through">{{ compareAt }}</span>
              <span
                v-if="stockBadge?.label"
                :class="stockBadge.class"
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.7rem] font-semibold"
              >
                <span class="h-1.5 w-1.5 rounded-full" :class="stockBadge.dot" />
                {{ stockBadge.label }}
              </span>
            </div>
          </div>
          <div class="inline-flex items-center rounded-full border border-slate-200 px-1 py-1">
            <button
              type="button"
              class="h-8 w-8 rounded-full text-slate-600 transition hover:bg-slate-100"
              @click="$emit('decrement')"
            >
              -
            </button>
            <span class="min-w-8 text-center text-sm font-semibold text-slate-900">{{ quantity }}</span>
            <button
              type="button"
              class="h-8 w-8 rounded-full text-slate-600 transition hover:bg-slate-100"
              @click="$emit('increment')"
            >
              +
            </button>
          </div>
        </div>

        <button
          type="button"
          class="btn-primary mt-3 flex min-h-12 w-full items-center justify-center"
          :disabled="disabled"
          @click="$emit('submit')"
        >
          {{ ctaLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  price: { type: String, required: true },
  compareAt: { type: String, default: '' },
  quantity: { type: Number, default: 1 },
  stockBadge: { type: Object, default: () => ({}) },
  ctaLabel: { type: String, required: true },
  disabled: { type: Boolean, default: false },
})

defineEmits(['increment', 'decrement', 'submit'])
</script>
