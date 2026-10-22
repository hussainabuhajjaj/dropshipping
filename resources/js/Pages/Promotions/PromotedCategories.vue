<template>
  <StorefrontLayout>
    <div class="max-w-5xl mx-auto py-10 space-y-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">{{ t('Promoted categories') }}</h1>
          <p class="text-slate-500 text-sm">{{ t('Categories currently highlighted in active promotions.') }}</p>
        </div>
        <Link href="/promotions/products" class="text-sm text-[#29ab87] font-semibold">
          {{ t('View promoted products') }}
        </Link>
      </div>

      <div v-if="categories.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <Link
          v-for="category in categories"
          :key="category.id"
          :href="`/categories/${category.slug}`"
          class="card p-4 group"
        >
          <div class="aspect-[4/3] w-full overflow-hidden rounded-lg bg-slate-100">
            <img
              v-if="category.image"
              :src="category.image"
              :alt="category.name"
              class="h-full w-full object-cover transition group-hover:scale-[1.02]"
              loading="lazy"
            />
          </div>
          <div class="mt-4">
            <h3 class="text-sm font-semibold text-slate-900">{{ category.name }}</h3>
            <p class="text-xs text-slate-500">{{ t(':count products', { count: category.count || 0 }) }}</p>
          </div>
        </Link>
      </div>
      <div v-else class="text-center text-slate-500 py-10">{{ t('No promoted categories right now.') }}</div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  promotions: { type: Array, default: () => [] },
})

const { t } = useTranslations()
</script>
