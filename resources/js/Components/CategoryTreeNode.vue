<template>
  <div :style="{ paddingLeft: `${level * 12}px` }" class="space-y-1">
    <div
      v-for="category in categories"
      :key="category.name"
      class="space-y-1"
    >
      <!-- Category item -->
      <Link
        :href="categoryHref(category)"
        class="flex items-center gap-2 rounded-lg border border-transparent px-2 py-1.5 text-xs font-medium text-slate-700 transition hover:border-slate-200 hover:bg-slate-50"
      >
        <span
          v-if="level <= 2"
          class="flex h-6 w-6 items-center justify-center rounded border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500"
        >
          {{ category.short }}
        </span>
        <span v-else class="text-slate-500">→</span>
        {{ category.name }}
      </Link>

      <!-- Nested children -->
      <CategoryTreeNode
        v-if="category.children && category.children.length"
        :categories="category.children"
        :level="level + 1"
      />
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  categories: { type: Array, default: () => [] },
  level: { type: Number, default: 1 },
})

const categoryHref = (category) => {
  if (category?.slug) {
    return `/categories/${encodeURIComponent(category.slug)}`
  }
  return `/products?category=${encodeURIComponent(category?.name ?? '')}`
}
</script>
