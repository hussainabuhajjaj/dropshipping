<template>
  <StorefrontLayout>
    <div class="max-w-3xl mx-auto py-10">
      <h1 class="text-2xl font-bold mb-6">{{ t('Current Promotions') }}</h1>
      <div v-if="promotions.length">
        <div v-for="promo in promotions" :key="promo.id" class="mb-8 p-5 border rounded-lg bg-white shadow-sm">
          <h2 class="text-lg font-semibold mb-1">{{ promo.name }}</h2>
          <p class="text-slate-600 mb-2">{{ promo.description }}</p>
          <div class="text-xs text-slate-500 mb-1">
            <span v-if="promo.start_at">{{ t('Starts') }}: {{ formatDate(promo.start_at) }}</span>
            <span v-if="promo.end_at"> | {{ t('Ends') }}: {{ formatDate(promo.end_at) }}</span>
          </div>
          <div class="text-sm">
            <span class="font-medium">{{ t('Type') }}:</span> {{ promo.type }}
            <span class="ml-4 font-medium">{{ t('Value') }}:</span> {{ promo.value_type === 'percentage' ? promo.value + '%' : (promo.value_type === 'fixed' ? displayPrice(promo.value) : t('Special')) }}
          </div>
          <div v-if="promo.targets.length" class="mt-2 text-xs">
            <span class="font-medium">{{ t('Applies to') }}:</span>
            <span v-for="target in promo.targets" :key="target.id" class="ml-2">
              {{ target.target_type }} #{{ target.target_id }}
            </span>
          </div>
          <div v-if="promo.conditions.length" class="mt-2 text-xs">
            <span class="font-medium">{{ t('Conditions') }}:</span>
            <span v-for="cond in promo.conditions" :key="cond.id" class="ml-2">
              {{ cond.condition_type }}: {{ cond.condition_value }}
            </span>
          </div>
        </div>
      </div>
      <div v-else class="text-center text-slate-500 py-10">{{ t('No active promotions at this time.') }}</div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { useTranslations } from '@/i18n'
import { computed } from 'vue'
import { formatCurrency } from '@/utils/currency.js'

const props = defineProps({
  promotions: { type: Array, default: () => [] },
})
const { t } = useTranslations()

function displayPrice(amount) {
  return formatCurrency(amount, 'USD')
}
function formatDate(date) {
  return new Date(date).toLocaleString()
}
</script>
