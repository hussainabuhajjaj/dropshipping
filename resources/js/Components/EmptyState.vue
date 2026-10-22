<template>
  <div :class="wrapperClass">
    <div :class="haloTopClass" />
    <div :class="haloBottomClass" />
    <div :class="innerClass">
      <div class="flex flex-wrap items-center gap-4">
        <div :class="iconClass">
          <slot name="icon">
            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h10" />
            </svg>
          </slot>
        </div>
        <div class="space-y-1">
          <p v-if="eyebrow" class="text-[0.7rem] font-semibold uppercase tracking-[0.35em] text-slate-400">
            {{ eyebrow }}
          </p>
          <h2 class="text-2xl font-semibold tracking-tight text-slate-900">
            {{ title }}
          </h2>
          <p class="max-w-xl text-sm text-slate-600">
            {{ message }}
          </p>
        </div>
      </div>
      <div v-if="$slots.actions" class="flex flex-wrap gap-3">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  message: { type: String, required: true },
  variant: { type: String, default: 'default' },
})

const wrapperClass = computed(() => [
  'relative overflow-hidden border border-slate-200',
  props.variant === 'compact' ? 'rounded-2xl bg-slate-50/60' : 'rounded-3xl bg-white',
].join(' '))

const innerClass = computed(() => [
  'relative flex flex-col gap-4',
  props.variant === 'compact' ? 'p-6' : 'p-8 sm:p-10',
].join(' '))

const iconClass = computed(() => [
  'flex items-center justify-center border border-slate-200 bg-slate-50 text-slate-600',
  props.variant === 'compact' ? 'h-12 w-12 rounded-xl' : 'h-14 w-14 rounded-2xl',
].join(' '))

const haloTopClass = computed(() => [
  'pointer-events-none absolute rounded-full bg-gradient-to-br blur-2xl',
  props.variant === 'compact'
    ? '-right-14 -top-16 h-36 w-36 from-amber-200/50 via-rose-100/30 to-transparent'
    : '-right-16 -top-20 h-48 w-48 from-amber-200/60 via-rose-100/40 to-transparent',
].join(' '))

const haloBottomClass = computed(() => [
  'pointer-events-none absolute rounded-full bg-gradient-to-br blur-2xl',
  props.variant === 'compact'
    ? '-bottom-16 -left-12 h-40 w-40 from-sky-200/40 via-cyan-100/30 to-transparent'
    : '-bottom-20 -left-12 h-56 w-56 from-sky-200/50 via-cyan-100/40 to-transparent',
].join(' '))
</script>
