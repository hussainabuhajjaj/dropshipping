<template>
  <nav v-if="items.length" aria-label="Breadcrumb" class="breadcrumbs-nav">
    <ol class="breadcrumbs-list">
      <li
        v-for="(item, index) in items"
        :key="`${item.href || item.label}-${index}`"
        class="breadcrumbs-item"
      >
        <span v-if="index > 0" class="breadcrumbs-separator" aria-hidden="true">/</span>
        <Link
          v-if="item.href && index < items.length - 1"
          :href="item.href"
          class="breadcrumbs-link"
        >
          {{ item.label }}
        </Link>
        <span
          v-else
          class="breadcrumbs-current"
          :aria-current="index === items.length - 1 ? 'page' : undefined"
        >
          {{ item.label }}
        </span>
      </li>
    </ol>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  items: { type: Array, default: () => [] },
})

const items = computed(() =>
  (Array.isArray(props.items) ? props.items : []).filter((item) => item?.label)
)
</script>
