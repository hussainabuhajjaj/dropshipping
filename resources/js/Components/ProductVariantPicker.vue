<template>
  <div v-if="useGroupedVariantPicker" class="space-y-5">
    <div v-for="group in optionGroups" :key="group.key" class="space-y-2.5">
      <div class="flex items-center justify-between">
        <label class="text-xs font-bold uppercase tracking-wide text-slate-500">
          {{ group.label }}
        </label>
        <span v-if="selectedOptions[group.key]" class="text-sm font-semibold text-slate-900">
          {{ selectedOptions[group.key] }}
        </span>
      </div>

      <div v-if="group.presentation === 'circle'" class="flex flex-wrap gap-2.5">
        <button
          v-for="choice in getChoices(group.key)"
          :key="`${group.key}-${choice.value}`"
          type="button"
          class="relative h-9 w-9 rounded-full border-2 transition"
          :class="choiceClasses(choice)"
          :style="{ backgroundColor: colorToHex(choice.label) }"
          :title="choice.label"
          :disabled="choice.disabled"
          @click="select(group.key, choice.value)"
        >
          <span
            v-if="choice.label.toLowerCase().trim() === 'white' || colorToHex(choice.label) === '#FFFFFF'"
            class="absolute inset-0 rounded-full border border-slate-200"
          />
          <span v-if="choice.selected" class="absolute inset-0 flex items-center justify-center">
            <svg class="h-3.5 w-3.5" :class="isLightColor(choice.label) ? 'text-slate-900' : 'text-white'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <path d="M5 13l4 4L19 7"/>
            </svg>
          </span>
        </button>
      </div>

      <div v-else-if="group.presentation === 'image'" class="flex flex-wrap gap-3">
        <button
          v-for="choice in getChoices(group.key)"
          :key="`${group.key}-${choice.value}`"
          type="button"
          class="w-20 overflow-hidden rounded-xl border bg-white text-left transition"
          :class="choiceImageClasses(choice)"
          :disabled="choice.disabled"
          @click="select(group.key, choice.value)"
        >
          <div class="aspect-square bg-slate-50">
            <img
              v-if="choice.image"
              :src="choice.image"
              :alt="choice.label"
              class="h-full w-full object-cover"
            />
            <div
              v-else
              class="flex h-full items-center justify-center px-2 text-center text-[0.6rem] font-semibold text-slate-500"
            >
              {{ choice.label }}
            </div>
          </div>
          <div class="border-t border-slate-100 px-2 py-1.5 text-[0.6rem] font-semibold text-slate-800">
            {{ choice.label }}
          </div>
        </button>
      </div>

      <div v-else-if="group.presentation === 'button'" class="flex flex-wrap gap-2">
        <button
          v-for="choice in getChoices(group.key)"
          :key="`${group.key}-${choice.value}`"
          type="button"
          class="rounded-lg border px-4 py-2 text-sm font-semibold transition min-w-[3rem] text-center"
          :class="choicePillClasses(choice)"
          :disabled="choice.disabled"
          @click="select(group.key, choice.value)"
        >
          {{ choice.label }}
        </button>
      </div>

      <select
        v-else
        :value="selectedOptions[group.key] ?? ''"
        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none"
        @change="select(group.key, $event.target.value)"
      >
        <option
          v-for="choice in getChoices(group.key)"
          :key="`${group.key}-${choice.value}`"
          :value="choice.value"
          :disabled="choice.disabled"
        >
          {{ choice.label }}{{ choice.outOfStock ? ` (${t('Out of stock')})` : '' }}
        </option>
      </select>
    </div>
  </div>

  <div v-else-if="product.variants?.length" class="space-y-2.5">
    <label class="text-xs font-bold uppercase tracking-wide text-slate-500">
      {{ t('Variant') }}
    </label>
    <div class="flex flex-wrap gap-2">
      <button
        v-for="variant in product.variants"
        :key="variant.id"
        type="button"
        class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
        :class="variant.id === selectedVariantId ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-800 hover:border-slate-300'"
        @click="$emit('select-variant', variant.id)"
      >
        {{ variant.title }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { useTranslations } from '@/i18n'

const props = defineProps({
  product: { type: Object, required: true },
  optionGroups: { type: Array, default: () => [] },
  selectedOptions: { type: Object, default: () => ({}) },
  useGroupedVariantPicker: { type: Boolean, default: false },
  selectedVariantId: { type: [Number, String], default: null },
  getGroupChoices: { type: Function, required: true },
})

const emit = defineEmits(['update-option-selection', 'select-variant'])

const { t } = useTranslations()

const getChoices = (groupKey) => props.getGroupChoices(groupKey)

const select = (groupKey, value) => {
  emit('update-option-selection', groupKey, value)
}

function colorToHex(name) {
  const map = {
    red: '#EF4444', blue: '#3B82F6', green: '#22C55E', black: '#000000', white: '#FFFFFF',
    gray: '#6B7280', grey: '#6B7280', yellow: '#EAB308', orange: '#F97316', purple: '#A855F7',
    pink: '#EC4899', brown: '#92400E', beige: '#F5F5DC', cream: '#FFFDD0', navy: '#000080',
    maroon: '#800000', teal: '#0D9488', gold: '#D4A017', silver: '#C0C0C0', khaki: '#C3B091',
    turquoise: '#40E0D0', coral: '#FF7F50', burgundy: '#800020', charcoal: '#36454F',
    nude: '#E3BC9A', camel: '#C19A6B', olive: '#808000', mint: '#98FB98', lavender: '#E6E6FA',
    peach: '#FFDAB9', wine: '#722F37', rose: '#FF007F', lilac: '#C8A2C8',
  }
  const lower = name.toLowerCase().trim()
  return map[lower] || '#CBD5E1'
}

function isLightColor(name) {
  const light = ['white', 'cream', 'beige', 'nude', 'ivory', 'peach', 'mint', 'lavender', 'silver', 'yellow', 'coral', 'pink']
  return light.includes(name.toLowerCase().trim())
}

function choiceClasses(choice) {
  return {
    'border-slate-900 ring-2 ring-slate-200 scale-110': choice.selected,
    'cursor-not-allowed border-slate-100 opacity-30': choice.disabled && !choice.selected,
    'border-slate-200 hover:border-slate-400': !choice.selected && !choice.disabled,
  }
}

function choiceImageClasses(choice) {
  return {
    'border-slate-900 ring-2 ring-slate-200': choice.selected,
    'cursor-not-allowed border-slate-200 opacity-40': choice.disabled && !choice.selected,
    'border-slate-200 hover:border-slate-400': !choice.selected && !choice.disabled,
  }
}

function choicePillClasses(choice) {
  return {
    'border-slate-900 bg-slate-900 text-white': choice.selected,
    'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-300': choice.disabled,
    'border-slate-200 bg-slate-50 text-slate-500': !choice.selected && !choice.disabled && choice.outOfStock,
    'border-slate-200 text-slate-800 hover:border-slate-400': !choice.selected && !choice.disabled && !choice.outOfStock,
  }
}
</script>
