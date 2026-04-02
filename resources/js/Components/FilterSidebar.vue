<template>
  <form :class="wrapperClass" @submit.prevent="$emit('apply')">
    <div class="space-y-2">
      <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Filters') }}</p>
      <p class="text-sm text-slate-600">{{ description || t('Narrow results by category, price, rating, brand, or attributes.') }}</p>
    </div>

    <div class="space-y-2">
      <label class="text-xs font-semibold text-slate-600">{{ t('Category') }}</label>
      <div v-if="hasCategoryTree" class="tree-container">
        <CategoryTreeCheckbox
          :nodes="categoryTree"
          :selected="selectedCategories"
          :expanded="expandedCategories"
          @toggle="$emit('toggle-category', $event)"
          @toggle-expand="$emit('toggle-expand', $event)"
        />
      </div>
      <p v-else class="text-xs text-slate-500">{{ t('No category filters available') }}</p>
    </div>

    <div class="space-y-2">
      <label class="text-xs font-semibold text-slate-600">{{ t('Search') }}</label>
      <input :value="modelValue.q" type="search" :placeholder="t('Search products')" class="input-base" @input="updateField('q', $event.target.value)" />
    </div>

    <div class="space-y-2">
      <label class="text-xs font-semibold text-slate-600">{{ t('Price range') }}</label>
      <div class="flex gap-2">
        <input :value="modelValue.min_price" type="number" min="0" :placeholder="t('Min')" class="input-base" @input="updateField('min_price', $event.target.value)" />
        <input :value="modelValue.max_price" type="number" min="0" :placeholder="t('Max')" class="input-base" @input="updateField('max_price', $event.target.value)" />
      </div>
    </div>

    <div class="space-y-2">
      <label class="text-xs font-semibold text-slate-600">{{ t('Rating') }}</label>
      <select :value="modelValue.rating" class="input-base" @change="updateField('rating', $event.target.value)">
        <option value="">{{ t('Any rating') }}</option>
        <option v-for="r in [5, 4, 3, 2, 1]" :key="r" :value="r">{{ t('At least :r stars', { r }) }}</option>
      </select>
    </div>

    <div class="space-y-2">
      <label class="text-xs font-semibold text-slate-600">{{ t('Stock') }}</label>
      <select :value="modelValue.in_stock" class="input-base" @change="updateField('in_stock', $event.target.value)">
        <option value="">{{ t('All') }}</option>
        <option value="1">{{ t('In stock only') }}</option>
      </select>
    </div>

    <div class="space-y-2">
      <label class="text-xs font-semibold text-slate-600">{{ t('Brand') }}</label>
      <select :value="modelValue.brand" class="input-base" @change="updateField('brand', $event.target.value)">
        <option value="">{{ t('All brands') }}</option>
        <option v-for="brand in brands" :key="brand" :value="brand">{{ brand }}</option>
      </select>
    </div>

    <div v-for="attr in attributes" :key="attr.key" class="space-y-2">
      <label class="text-xs font-semibold text-slate-600">{{ attr.label }}</label>
      <select :value="modelValue[attr.key]" class="input-base" @change="updateField(attr.key, $event.target.value)">
        <option value="">{{ t('Any') }}</option>
        <option v-for="option in attr.options" :key="option" :value="option">{{ option }}</option>
      </select>
    </div>

    <div class="flex gap-2">
      <button type="submit" class="btn-secondary flex-1">{{ applyLabel || t('Apply') }}</button>
      <button type="button" class="btn-ghost flex-1" @click="$emit('reset')">{{ resetLabel || t('Reset') }}</button>
    </div>
  </form>
</template>

<script setup>
import { computed, defineComponent } from 'vue'
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
})

const emit = defineEmits(['update:modelValue', 'apply', 'reset', 'toggle-category', 'toggle-expand'])
const { t } = useTranslations()

const hasCategoryTree = computed(() => (props.categoryTree?.length ?? 0) > 0)

const updateField = (key, value) => {
  emit('update:modelValue', {
    ...props.modelValue,
    [key]: value,
  })
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
    return {
      isExpanded,
      nodeId,
      toggleSelect: (id) => localEmit('toggle', id),
      toggleOpen: (id) => localEmit('toggle-expand', id),
    }
  },
  template: `
    <div class="space-y-1">
      <div v-for="node in nodes" :key="nodeId(node)" class="space-y-1">
        <div class="flex items-center gap-2" :style="{ paddingLeft: (level * 14) + 'px' }">
          <button
            v-if="node.children && node.children.length"
            type="button"
            class="tree-expander"
            @click="toggleOpen(nodeId(node))"
          >
            <span v-if="isExpanded(nodeId(node))">−</span>
            <span v-else>+</span>
          </button>
          <span v-else class="tree-expander placeholder"></span>
          <input
            type="checkbox"
            class="tree-checkbox"
            :value="nodeId(node)"
            :checked="selected.includes(nodeId(node))"
            @change="toggleSelect(nodeId(node))"
          />
          <span class="tree-label">{{ node.name }}</span>
        </div>
        <CategoryTreeCheckbox
          v-if="node.children && node.children.length && isExpanded(nodeId(node))"
          :nodes="node.children"
          :level="level + 1"
          :selected="selected"
          :expanded="expanded"
          @toggle="toggleSelect"
          @toggle-expand="toggleOpen"
        />
      </div>
    </div>
  `,
})
</script>

<style scoped>
.tree-container {
  max-height: 260px;
  overflow-y: auto;
  padding: 6px 4px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}

.tree-expander {
  width: 22px;
  height: 22px;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
  background: #f8fafc;
  font-weight: 800;
  color: #111827;
  line-height: 1;
}

.tree-expander.placeholder {
  border: none;
  background: transparent;
}

.tree-checkbox {
  width: 16px;
  height: 16px;
  border: 1px solid #cbd5e1;
}

.tree-label {
  font-weight: 700;
  color: #0f172a;
  font-size: 13px;
}
</style>
