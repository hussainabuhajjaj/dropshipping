<template>
  <div :class="wrapperClass">
    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
      <h3 class="text-sm font-bold text-slate-900">{{ t('Filters') }}</h3>
      <button type="button" class="inline-flex min-h-10 items-center text-xs font-semibold text-red-500 transition hover:text-red-600" @click="$emit('reset')">{{ t('Reset') }}</button>
    </div>

    <div class="mt-4 space-y-5">
      <!-- Category tree -->
      <div v-if="hasCategoryTree">
        <button type="button" class="flex min-h-10 w-full items-center justify-between text-xs font-bold uppercase tracking-wide text-slate-500" @click="toggleSection('category')">
          {{ t('Category') }}
          <svg class="h-3.5 w-3.5 transition" :class="openSections.category ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div v-if="openSections.category" class="mt-3 tree-container">
          <CategoryTreeCheckbox
            :nodes="categoryTree"
            :selected="selectedCategories"
            :expanded="expandedCategories"
            @toggle="$emit('toggle-category', $event)"
            @toggle-expand="$emit('toggle-expand', $event)"
          />
        </div>
      </div>

      <!-- Search -->
      <div>
        <button type="button" class="flex min-h-10 w-full items-center justify-between text-xs font-bold uppercase tracking-wide text-slate-500" @click="toggleSection('search')">
          {{ t('Search') }}
          <svg class="h-3.5 w-3.5 transition" :class="openSections.search ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div v-if="openSections.search" class="mt-3">
          <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z"/></svg>
            <input :value="modelValue.q" type="search" :placeholder="t('Search...')" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 placeholder-slate-400 focus:border-slate-500 focus:outline-none" @input="updateField('q', $event.target.value)" />
          </div>
        </div>
      </div>

      <!-- Price range -->
      <div>
        <button type="button" class="flex min-h-10 w-full items-center justify-between text-xs font-bold uppercase tracking-wide text-slate-500" @click="toggleSection('price')">
          {{ t('Price') }}
          <svg class="h-3.5 w-3.5 transition" :class="openSections.price ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div v-if="openSections.price" class="mt-3">
          <div class="flex items-center gap-2">
            <input :value="modelValue.min_price" type="number" min="0" :placeholder="t('Min')" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none" @input="updateField('min_price', $event.target.value)" />
            <span class="text-xs text-slate-400">—</span>
            <input :value="modelValue.max_price" type="number" min="0" :placeholder="t('Max')" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none" @input="updateField('max_price', $event.target.value)" />
          </div>
        </div>
      </div>

      <!-- Dynamic attributes (Size/Color shown as buttons, others as select) -->
      <div v-for="attr in attributes" :key="attr.key">
        <button type="button" class="flex min-h-10 w-full items-center justify-between text-xs font-bold uppercase tracking-wide text-slate-500" @click="toggleSection(attr.key)">
          <span class="flex items-center gap-2">
            {{ attr.label }}
            <span v-if="isVariantAttribute(attr)" class="rounded bg-slate-200 px-1.5 py-0.5 text-[0.55rem] font-bold uppercase tracking-normal text-slate-500">{{ t('Variant') }}</span>
          </span>
          <svg class="h-3.5 w-3.5 transition" :class="openSections[attr.key] ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div v-if="openSections[attr.key]" class="mt-3">
          <template v-if="isSizeAttribute(attr)">
            <div class="flex flex-wrap gap-2">
              <button
                v-for="option in attr.options"
                :key="option"
                type="button"
                class="rounded-lg border px-4 py-2 text-xs font-semibold transition"
                :class="modelValue[attr.key] === option ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'"
                @click="toggleAttrValue(attr.key, option)"
              >
                {{ option }}
              </button>
            </div>
          </template>
          <template v-else-if="isColorAttribute(attr)">
            <div class="flex flex-wrap gap-2">
              <button
                v-for="option in attr.options"
                :key="option"
                type="button"
                class="flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-semibold transition"
                :class="modelValue[attr.key] === option ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'"
                @click="toggleAttrValue(attr.key, option)"
              >
                <span class="h-3.5 w-3.5 rounded-full border border-slate-200" :style="{ backgroundColor: colorToHex(option) }" />
                {{ option }}
              </button>
            </div>
          </template>
          <template v-else>
            <select :value="modelValue[attr.key]" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none" @change="updateField(attr.key, $event.target.value)">
              <option value="">{{ t('All') }}</option>
              <option v-for="option in attr.options" :key="option" :value="option">{{ option }}</option>
            </select>
          </template>
        </div>
      </div>

      <!-- Apply button -->
      <button type="button" class="btn-red w-full" @click="$emit('apply')">{{ applyLabel || t('Apply') }}</button>
    </div>
  </div>
</template>

<script setup>
import { computed, defineComponent, reactive } from 'vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  modelValue: { type: Object, required: true },
  brands: { type: Array, default: () => [] },
  attributes: { type: Array, default: () => [] },
  categoryTree: { type: Array, default: () => [] },
  expandedCategories: { type: Object, required: true },
  selectedCategories: { type: Array, default: () => [] },
  wrapperClass: { type: String, default: '' },
  description: { type: String, default: '' },
  applyLabel: { type: String, default: '' },
  resetLabel: { type: String, default: '' },
  variantAttributeKeys: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'apply', 'reset', 'toggle-category', 'toggle-expand'])
const { t } = useTranslations()

const hasCategoryTree = computed(() => (props.categoryTree?.length ?? 0) > 0)

const openSections = reactive({
  category: true,
  search: false,
  price: true,
})

for (const attr of props.attributes) {
  openSections[attr.key] = true
}

const sizeKeys = ['size', 'taille', 's']
const colorKeys = ['color', 'colour', 'couleur']

const isSizeAttribute = (attr) => sizeKeys.includes(attr.key.toLowerCase())
const isColorAttribute = (attr) => colorKeys.includes(attr.key.toLowerCase())
const isVariantAttribute = (attr) => props.variantAttributeKeys.includes(attr.key)

const colorToHex = (name) => {
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

const toggleSection = (key) => {
  openSections[key] = !openSections[key]
}

const toggleAttrValue = (key, value) => {
  const current = props.modelValue[key]
  const newVal = current === value ? '' : value
  emit('update:modelValue', { ...props.modelValue, [key]: newVal })
}

const updateField = (key, value) => {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

const CategoryTreeCheckbox = defineComponent({
  name: 'CategoryTreeCheckbox',
  props: {
    nodes: { type: Array, default: () => [] },
    level: { type: Number, default: 0 },
    selected: { type: Array, default: () => [] },
    expanded: { type: Object, required: true },
  },
  emits: ['toggle', 'toggle-expand'],
  setup(localProps, { emit: localEmit }) {
    const isExpanded = (id) => localProps.expanded.has(id)
    const nodeId = (node) => node.id || node.slug || node.name
    return { isExpanded, nodeId, toggleSelect: (id) => localEmit('toggle', id), toggleOpen: (id) => localEmit('toggle-expand', id) }
  },
  template: `
    <div class="space-y-0.5">
      <div v-for="node in nodes" :key="nodeId(node)" class="space-y-0.5">
        <div class="flex items-center gap-1.5" :style="{ paddingLeft: (level * 12) + 'px' }">
          <button v-if="node.children && node.children.length" type="button" class="flex h-4 w-4 items-center justify-center rounded text-[0.55rem] font-bold text-slate-500 hover:bg-slate-100" @click="toggleOpen(nodeId(node))">
            <span v-if="isExpanded(nodeId(node))">−</span>
            <span v-else>+</span>
          </button>
          <span v-else class="w-4" />
          <input type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-slate-900 focus:ring-0" :value="nodeId(node)" :checked="selected.includes(nodeId(node))" @change="toggleSelect(nodeId(node))" />
          <span class="text-xs font-medium text-slate-700">{{ node.name }}</span>
        </div>
        <CategoryTreeCheckbox v-if="node.children && node.children.length && isExpanded(nodeId(node))" :nodes="node.children" :level="level + 1" :selected="selected" :expanded="expanded" @toggle="toggleSelect" @toggle-expand="toggleOpen" />
      </div>
    </div>
  `,
})
</script>

<style scoped>
.tree-container {
  max-height: 220px;
  overflow-y: auto;
}
</style>
