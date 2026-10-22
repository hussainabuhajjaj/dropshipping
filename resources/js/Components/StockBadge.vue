<script setup>
import { computed } from 'vue'

const props = defineProps({
  stock: { type: Number, default: null },
  threshold: { type: Number, default: 5 },
  size: { type: String, default: 'sm' }, // 'xs', 'sm', 'md'
})

const status = computed(() => {
  if (props.stock === null || props.stock === undefined) {
    return null
  }
  
  if (props.stock <= 0) {
    return 'out'
  }
  
  if (props.stock <= props.threshold) {
    return 'low'
  }
  
  return 'in'
})

const badge = computed(() => {
  if (!status.value) return null
  
  const sizeClasses = {
    xs: 'px-1.5 py-0.5 text-[0.65rem]',
    sm: 'px-2 py-1 text-xs',
    md: 'px-3 py-1.5 text-sm',
  }
  
  const configs = {
    out: {
      label: 'Out of stock',
      class: 'bg-red-50 text-red-700 border-red-200',
    },
    low: {
      label: `Low stock (${props.stock})`,
      class: 'bg-amber-50 text-amber-700 border-amber-200',
    },
    in: {
      label: 'In stock',
      class: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    },
  }
  
  const config = configs[status.value]
  
  return {
    label: config.label,
    class: `inline-flex items-center rounded-full border font-semibold ${sizeClasses[props.size]} ${config.class}`,
  }
})
</script>

<template>
  <span v-if="badge" :class="badge.class">
    {{ badge.label }}
  </span>
</template>
