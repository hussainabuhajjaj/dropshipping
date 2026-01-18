<template>
  <StorefrontLayout>
    <div class="max-w-6xl mx-auto py-10 space-y-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">{{ t('Promoted products') }}</h1>
          <p class="text-slate-500 text-sm">{{ t('Products currently highlighted in active promotions.') }}</p>
        </div>
        <Link href="/promotions/categories" class="text-sm text-[#29ab87] font-semibold">
          {{ t('View promoted categories') }}
        </Link>
      </div>

      <div v-if="products.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <ProductCard
          v-for="product in products"
          :key="product.id"
          :product="product"
          :currency="currency"
          :promotions="promotions"
        />
      </div>
      <div v-else class="text-center text-slate-500 py-10">{{ t('No promoted products right now.') }}</div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ProductCard from '@/Components/ProductCard.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  products: { type: Array, default: () => [] },
  promotions: { type: Array, default: () => [] },
  currency: { type: String, default: 'USD' },
})

const { t } = useTranslations()
</script>
