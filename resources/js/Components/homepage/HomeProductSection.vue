<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import CompactProductCard from '@/Components/homepage/CompactProductCard.vue'
import HomeSectionHeader from '@/Components/homepage/HomeSectionHeader.vue'

const props = defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  href: { type: String, default: '' },
  actionLabel: { type: String, default: '' },
  icon: { type: String, default: 'spark' },
  products: { type: Array, default: () => [] },
  currency: { type: String, default: 'USD' },
  limit: { type: Number, default: 8 },
})

const emit = defineEmits(['quick-add'])
const visibleProducts = computed(() => props.products.slice(0, props.limit))
</script>

<template>
  <section v-if="visibleProducts.length">
    <HomeSectionHeader
      :eyebrow="eyebrow"
      :title="title"
      :subtitle="subtitle"
      :href="href"
      :action-label="actionLabel"
      :icon="icon"
    />

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
      <CompactProductCard
        v-for="product in visibleProducts"
        :key="product.id"
        :product="product"
        :currency="currency"
        @quick-add="emit('quick-add', $event)"
      />
    </div>

    <div v-if="href" class="mt-3 flex justify-center sm:hidden">
      <Link :href="href" class="inline-flex h-10 items-center rounded-md border border-[#eee6da] bg-white px-4 text-sm font-bold text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-slate-950">
        {{ actionLabel }}
      </Link>
    </div>
  </section>
</template>
