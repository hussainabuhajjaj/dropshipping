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
        <span v-if="categoryHasPromotion(category, promotions)" class="ml-2 px-2 py-0.5 rounded bg-yellow-200 text-yellow-900 font-bold">Promo!</span>
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

const props = defineProps({
  categories: { type: Array, default: () => [] },
  level: { type: Number, default: 1 },
  promotions: { type: Array, default: () => [] },
})

function categoryHasPromotion(category, promotions) {
  if (!promotions || !promotions.length) return false
  return promotions.some(p =>
    (p.targets || []).some(t => t.target_type === 'category' && (t.target_value === category.name || t.target_id == category.id))
  )
}

const categoryHref = (category) => {
  if (category?.slug) {
    return `/categories/${encodeURIComponent(category.slug)}`
  }
  return `/products?category=${encodeURIComponent(category?.name ?? '')}`
}
</script>
